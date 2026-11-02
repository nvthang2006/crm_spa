import { createHash } from 'node:crypto'
import { createReadStream } from 'node:fs'
import { open, readFile, readdir } from 'node:fs/promises'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.resolve(scriptDirectory, '..')
const tauriConfigPath = path.join(projectRoot, 'src-tauri', 'tauri.conf.json')
const bucketName = 'desktop-updates'
const uploadChunkSize = 6 * 1024 * 1024
const retryDelays = [1000, 3000, 5000]
const retryableStatuses = new Set([408, 409, 425, 429, 500, 502, 503, 504])
const semverPattern = /^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/

function assertVersion(version) {
  if (typeof version !== 'string' || !semverPattern.test(version)) {
    throw new Error(`Phiên bản Tauri không phải SemVer hợp lệ: ${version ?? '(trống)'}`)
  }
}

function getProjectUrl(value) {
  if (!value) {
    throw new Error('Thiếu VITE_SUPABASE_URL.')
  }

  const url = new URL(value)
  const isLocalHost = ['localhost', '127.0.0.1', '::1', '[::1]'].includes(url.hostname)

  if ((url.protocol !== 'https:' && !(url.protocol === 'http:' && isLocalHost)) || url.username || url.password || url.search || url.hash) {
    throw new Error('VITE_SUPABASE_URL phải là URL HTTPS hợp lệ của dự án Supabase.')
  }

  return url.origin
}

function getAuthHeaders(apiKey) {
  return {
    apikey: apiKey,
    authorization: `Bearer ${apiKey}`,
  }
}

function getStorageObjectPath(pathSegments) {
  return pathSegments.map((segment) => encodeURIComponent(segment)).join('/')
}

function encodeTusMetadata(metadata) {
  return Object.entries(metadata)
    .map(([key, value]) => `${key} ${Buffer.from(String(value), 'utf8').toString('base64')}`)
    .join(',')
}

function getPublicObjectUrl(projectUrl, objectPath) {
  const segments = Array.isArray(objectPath) ? objectPath : objectPath.split('/')
  const encodedPath = getStorageObjectPath(segments)

  return `${projectUrl}/storage/v1/object/public/${bucketName}/${encodedPath}`
}

function getTusEndpoint(projectUrl) {
  const url = new URL(projectUrl)
  const supabaseDomainSuffix = '.supabase.co'

  if (url.hostname.endsWith(supabaseDomainSuffix) && !url.hostname.endsWith('.storage.supabase.co')) {
    const projectReference = url.hostname.slice(0, -supabaseDomainSuffix.length)
    url.hostname = `${projectReference}.storage.supabase.co`
  }

  return `${url.origin}/storage/v1/upload/resumable`
}

export function buildUpdateManifest({ version, notes, signature, installerUrl, publishedAt = new Date() }) {
  assertVersion(version)

  if (!notes?.trim()) {
    throw new Error('Thiếu ghi chú phát hành RELEASE_NOTES.')
  }

  if (!signature?.trim()) {
    throw new Error('Chữ ký updater trống; không thể tạo manifest an toàn.')
  }

  const url = new URL(installerUrl)

  if (url.protocol !== 'https:' && url.hostname !== 'localhost' && url.hostname !== '127.0.0.1' && url.hostname !== '[::1]') {
    throw new Error('Đường dẫn bộ cài trong manifest phải sử dụng HTTPS.')
  }

  return {
    version,
    notes: notes.trim(),
    pub_date: publishedAt.toISOString(),
    platforms: {
      'windows-x86_64': {
        signature: signature.trim(),
        url: url.toString(),
      },
    },
  }
}

async function getFileSha256(filePath) {
  const hash = createHash('sha256')

  for await (const chunk of createReadStream(filePath)) {
    hash.update(chunk)
  }

  return hash.digest('hex')
}

async function getUploadOffset(uploadUrl, headers, totalSize, fetchImpl) {
  const response = await fetchImpl(uploadUrl, {
    method: 'HEAD',
    headers: {
      ...headers,
      'tus-resumable': '1.0.0',
    },
  })

  if (!response.ok) {
    throw new Error(`Không thể kiểm tra tiến độ tải lên TUS (HTTP ${response.status}).`)
  }

  const offset = Number(response.headers.get('upload-offset'))

  if (!Number.isSafeInteger(offset) || offset < 0 || offset > totalSize) {
    throw new Error('Supabase trả về vị trí tải lên TUS không hợp lệ.')
  }

  return offset
}

async function patchChunk({ uploadUrl, headers, chunk, offset, totalSize, fetchImpl }) {
  let lastError

  for (let attempt = 0; attempt <= retryDelays.length; attempt += 1) {
    let response

    try {
      response = await fetchImpl(uploadUrl, {
        method: 'PATCH',
        headers: {
          ...headers,
          'content-type': 'application/offset+octet-stream',
          'tus-resumable': '1.0.0',
          'upload-offset': String(offset),
        },
        body: chunk,
      })
    } catch (error) {
      lastError = error
    }

    if (response?.ok) {
      const nextOffset = Number(response.headers.get('upload-offset'))

      if (nextOffset !== offset + chunk.byteLength) {
        throw new Error('Supabase trả về vị trí tải lên TUS không khớp với phần dữ liệu vừa gửi.')
      }

      return nextOffset
    }

    if (response && !retryableStatuses.has(response.status)) {
      throw new Error(`Tải bộ cài lên Supabase thất bại (HTTP ${response.status}).`)
    }

    if (attempt === retryDelays.length) {
      break
    }

    await new Promise((resolve) => setTimeout(resolve, retryDelays[attempt]))

    try {
      const currentOffset = await getUploadOffset(uploadUrl, headers, totalSize, fetchImpl)

      if (currentOffset === offset + chunk.byteLength) {
        return currentOffset
      }

      if (currentOffset !== offset) {
        throw new Error('Vị trí tải lên TUS đã thay đổi ngoài phần dữ liệu vừa gửi.')
      }
    } catch (error) {
      lastError = error
    }
  }

  throw new Error(`Không thể tiếp tục tải bộ cài lên Supabase: ${lastError?.message ?? 'hết số lần thử lại.'}`)
}

export async function uploadInstallerTus({ projectUrl, apiKey, installerPath, objectPath, fetchImpl = fetch }) {
  const file = await open(installerPath, 'r')

  try {
    const { size } = await file.stat()

    if (!Number.isSafeInteger(size) || size <= 0) {
      throw new Error('Bộ cài không tồn tại hoặc có kích thước không hợp lệ.')
    }

    const endpoint = getTusEndpoint(projectUrl)
    const authHeaders = getAuthHeaders(apiKey)
    const createResponse = await fetchImpl(endpoint, {
      method: 'POST',
      headers: {
        ...authHeaders,
        'cache-control': 'no-store',
        'tus-resumable': '1.0.0',
        'upload-length': String(size),
        'upload-metadata': encodeTusMetadata({
          bucketName,
          objectName: objectPath,
          contentType: 'application/octet-stream',
          cacheControl: '31536000',
        }),
        'x-upsert': 'true',
      },
    })

    if (!createResponse.ok) {
      throw new Error(`Không thể khởi tạo tải lên TUS (HTTP ${createResponse.status}).`)
    }

    const location = createResponse.headers.get('location')

    if (!location) {
      throw new Error('Supabase không trả về URL tải lên TUS.')
    }

    const uploadUrl = new URL(location, endpoint)

    if (uploadUrl.origin !== new URL(endpoint).origin) {
      throw new Error('URL tải lên TUS nằm ngoài máy chủ Supabase đã cấu hình.')
    }

    const uploadHeaders = {
      ...authHeaders,
      'tus-resumable': '1.0.0',
    }
    let offset = 0

    while (offset < size) {
      const chunkSize = Math.min(uploadChunkSize, size - offset)
      const chunkBuffer = Buffer.allocUnsafe(chunkSize)
      const { bytesRead } = await file.read(chunkBuffer, 0, chunkSize, offset)

      if (bytesRead !== chunkSize) {
        throw new Error('Không thể đọc đầy đủ bộ cài trong lúc tải lên.')
      }

      offset = await patchChunk({
        uploadUrl,
        headers: uploadHeaders,
        chunk: chunkBuffer,
        offset,
        totalSize: size,
        fetchImpl,
      })

      console.log(`Đã tải lên ${Math.round((offset / size) * 100)}% bộ cài.`)
    }

    return getPublicObjectUrl(projectUrl, objectPath)
  } finally {
    await file.close()
  }
}

export async function uploadLatestManifest({ projectUrl, apiKey, manifest, fetchImpl = fetch }) {
  const manifestUrl = `${projectUrl}/storage/v1/object/${bucketName}/latest.json`
  const response = await fetchImpl(manifestUrl, {
    method: 'POST',
    headers: {
      ...getAuthHeaders(apiKey),
      'cache-control': '0',
      'content-type': 'application/json',
      'x-upsert': 'true',
    },
    body: JSON.stringify(manifest, null, 2),
  })

  if (!response.ok) {
    throw new Error(`Không thể cập nhật latest.json trên Supabase (HTTP ${response.status}).`)
  }

  return getPublicObjectUrl(projectUrl, ['latest.json'])
}

export async function publishWindowsRelease({
  projectUrl,
  apiKey,
  version,
  notes,
  signature,
  installerPath,
  publishedAt = new Date(),
  fetchImpl = fetch,
}) {
  const normalizedProjectUrl = getProjectUrl(projectUrl)
  assertVersion(version)

  if (!apiKey?.trim()) {
    throw new Error('Thiếu SUPABASE_SECRET_KEY hoặc SUPABASE_SERVICE_ROLE_KEY.')
  }

  if (!notes?.trim()) {
    throw new Error('Thiếu RELEASE_NOTES.')
  }

  if (!signature?.trim()) {
    throw new Error('Không tìm thấy chữ ký .sig của bộ cài.')
  }

  const contentHash = await getFileSha256(installerPath)
  const objectPath = ['releases', version, contentHash, `CRM-Spa-${version}-windows-x86_64-setup.exe`]
  const installerUrl = await uploadInstallerTus({
    projectUrl: normalizedProjectUrl,
    apiKey,
    installerPath,
    objectPath: objectPath.join('/'),
    fetchImpl,
  })
  const manifest = buildUpdateManifest({
    version,
    notes,
    signature,
    installerUrl,
    publishedAt,
  })
  const latestManifestUrl = await uploadLatestManifest({
    projectUrl: normalizedProjectUrl,
    apiKey,
    manifest,
    fetchImpl,
  })

  return { contentHash, installerUrl, latestManifestUrl, manifest }
}

export function createBuildEnvironment(environment) {
  const buildEnvironment = { ...environment }
  delete buildEnvironment.SUPABASE_SECRET_KEY
  delete buildEnvironment.SUPABASE_SERVICE_ROLE_KEY

  return buildEnvironment
}

async function getInstallerArtifacts() {
  const config = JSON.parse(await readFile(tauriConfigPath, 'utf8'))
  const version = config.version
  assertVersion(version)

  const cargoTargetDirectory = process.env.CARGO_TARGET_DIR
    ? path.resolve(projectRoot, process.env.CARGO_TARGET_DIR)
    : path.join(projectRoot, 'src-tauri', 'target')
  const bundleDirectory = path.join(cargoTargetDirectory, 'release', 'bundle', 'nsis')
  const bundleFiles = await readdir(bundleDirectory, { withFileTypes: true })
  const installers = bundleFiles.filter((file) => {
    return file.isFile() && file.name.toLowerCase().endsWith('.exe') && !file.name.toLowerCase().includes('uninstall')
  })
  const escapedVersion = version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const versionPattern = new RegExp(`(^|[^0-9A-Za-z])${escapedVersion}($|[^0-9A-Za-z])`)
  const matchingVersionInstallers = installers.filter((file) => versionPattern.test(file.name))
  const installerCandidates = matchingVersionInstallers.length > 0 ? matchingVersionInstallers : installers

  if (installerCandidates.length !== 1) {
    throw new Error(`Cần xác định đúng một bộ cài NSIS phiên bản ${version} trong ${bundleDirectory}; tìm thấy ${installerCandidates.length}.`)
  }

  const installerPath = path.join(bundleDirectory, installerCandidates[0].name)
  const signaturePath = `${installerPath}.sig`
  const signature = (await readFile(signaturePath, 'utf8')).trim()

  if (!signature) {
    throw new Error('Tệp chữ ký .sig của bộ cài đang trống.')
  }

  return { installerPath, signature, version }
}

function runWindowsBuild() {
  if (process.platform !== 'win32' || process.arch !== 'x64') {
    throw new Error('Phát hành bộ cài này phải chạy trên Windows x64 để tạo và kiểm thử NSIS.')
  }

  const buildScriptPath = path.join(scriptDirectory, 'build-windows.mjs')
  const result = spawnSync(process.execPath, [buildScriptPath], {
    cwd: projectRoot,
    stdio: 'inherit',
    env: createBuildEnvironment(process.env),
  })

  if (result.error) {
    throw result.error
  }

  if (result.status !== 0) {
    throw new Error(`Tạo bộ cài Windows thất bại với mã ${result.status ?? 'không xác định'}.`)
  }
}

async function runRelease() {
  const projectUrl = getProjectUrl(process.env.VITE_SUPABASE_URL)
  const apiKey = process.env.SUPABASE_SECRET_KEY?.trim() || process.env.SUPABASE_SERVICE_ROLE_KEY?.trim()

  if (!apiKey) {
    throw new Error('Thiếu SUPABASE_SECRET_KEY hoặc SUPABASE_SERVICE_ROLE_KEY.')
  }

  if (!process.env.TAURI_UPDATER_PUBKEY || !process.env.TAURI_SIGNING_PRIVATE_KEY) {
    throw new Error('Thiếu TAURI_UPDATER_PUBKEY hoặc TAURI_SIGNING_PRIVATE_KEY.')
  }

  if (!process.env.RELEASE_NOTES?.trim()) {
    throw new Error('Thiếu RELEASE_NOTES, hãy nhập ghi chú cho phiên bản sắp phát hành.')
  }

  runWindowsBuild()

  const { installerPath, signature, version } = await getInstallerArtifacts()
  const release = await publishWindowsRelease({
    projectUrl,
    apiKey,
    version,
    notes: process.env.RELEASE_NOTES,
    signature,
    installerPath,
  })

  console.log(`Đã phát hành CRM Spa ${version}.`)
  console.log(`Bộ cài: ${release.installerUrl}`)
  console.log(`Manifest: ${release.latestManifestUrl}`)
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runRelease().catch((error) => {
    console.error(error.message)
    process.exitCode = 1
  })
}
