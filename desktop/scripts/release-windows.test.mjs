import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { after, before, test } from 'node:test'
import { buildUpdateManifest, createBuildEnvironment, publishWindowsRelease } from './release-windows.mjs'

let server
let serverUrl
let temporaryDirectory
let uploadedInstaller = Buffer.alloc(0)
let publishedManifest
let requestOrder = []
let serverError
let failInstallerUpload = false
let expectedUploadLength = 0
const storageApiKey = 'test-secret-key'

before(async () => {
  temporaryDirectory = await mkdtemp(path.join(os.tmpdir(), 'crm-spa-updater-test-'))
  server = createServer((request, response) => {
    void (async () => {
      requestOrder.push(request.url)

      if (request.url === '/storage/v1/upload/resumable' && request.method === 'POST') {
        assert.equal(request.headers.authorization, `Bearer ${storageApiKey}`)
        assert.equal(request.headers.apikey, storageApiKey)
        assert.equal(request.headers['x-upsert'], 'true')
        assert.equal(request.headers['upload-length'], String(expectedUploadLength))

        if (failInstallerUpload) {
          response.writeHead(503)
          response.end()
          return
        }

        response.writeHead(201, { location: '/storage/v1/upload/resumable/session-1' })
        response.end()
        return
      }

      if (request.url === '/storage/v1/upload/resumable/session-1' && request.method === 'PATCH') {
        assert.equal(request.headers.authorization, `Bearer ${storageApiKey}`)
        assert.equal(request.headers['tus-resumable'], '1.0.0')
        assert.equal(request.headers['content-type'], 'application/offset+octet-stream')
        assert.equal(request.headers['upload-offset'], String(uploadedInstaller.length))

        const chunks = []

        for await (const chunk of request) {
          chunks.push(chunk)
        }

        uploadedInstaller = Buffer.concat([uploadedInstaller, ...chunks])
        response.writeHead(204, { 'upload-offset': String(uploadedInstaller.length) })
        response.end()
        return
      }

      if (request.url === '/storage/v1/object/desktop-updates/latest.json' && request.method === 'POST') {
        assert.equal(request.headers.authorization, `Bearer ${storageApiKey}`)
        assert.equal(request.headers.apikey, storageApiKey)
        assert.equal(request.headers['x-upsert'], 'true')
        assert.equal(request.headers['cache-control'], '0')

        const chunks = []

        for await (const chunk of request) {
          chunks.push(chunk)
        }

        publishedManifest = JSON.parse(Buffer.concat(chunks).toString('utf8'))
        response.writeHead(200, { 'content-type': 'application/json' })
        response.end('{}')
        return
      }

      response.writeHead(404)
      response.end()
    })().catch((error) => {
      serverError = error
      response.writeHead(500)
      response.end()
    })
  })

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
  serverUrl = `http://127.0.0.1:${server.address().port}`
})

after(async () => {
  await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())))
  await rm(temporaryDirectory, { recursive: true, force: true })
})

test('builds a valid static Tauri updater manifest for Windows x64', () => {
  const manifest = buildUpdateManifest({
    version: '0.1.1',
    notes: 'Sửa lỗi lịch hẹn.',
    signature: 'signature-content',
    installerUrl: 'https://example.com/releases/0.1.1/setup.exe',
    publishedAt: new Date('2026-10-19T00:00:00.000Z'),
  })

  assert.deepEqual(manifest, {
    version: '0.1.1',
    notes: 'Sửa lỗi lịch hẹn.',
    pub_date: '2026-10-19T00:00:00.000Z',
    platforms: {
      'windows-x86_64': {
        signature: 'signature-content',
        url: 'https://example.com/releases/0.1.1/setup.exe',
      },
    },
  })
})

test('requires the signed updater artifact to bind its release version', async () => {
  const configPath = new URL('../src-tauri/tauri.conf.json', import.meta.url)
  const config = JSON.parse(await readFile(configPath, 'utf8'))

  assert.equal(config.plugins.updater.requireSignedVersion, true)
})

test('does not expose the Supabase publishing key to the native build process', () => {
  const environment = createBuildEnvironment({
    SUPABASE_SECRET_KEY: 'secret-key',
    SUPABASE_SERVICE_ROLE_KEY: 'legacy-secret-key',
    TAURI_SIGNING_PRIVATE_KEY: 'updater-signing-key',
  })

  assert.deepEqual(environment, { TAURI_SIGNING_PRIVATE_KEY: 'updater-signing-key' })
})

test('uploads the installer before publishing latest.json with a hash-versioned URL', async () => {
  const installerPath = path.join(temporaryDirectory, 'setup.exe')
  const installerBytes = Buffer.from('verified test Windows installer')
  uploadedInstaller = Buffer.alloc(0)
  publishedManifest = undefined
  requestOrder = []
  serverError = undefined
  expectedUploadLength = installerBytes.byteLength
  await writeFile(installerPath, installerBytes)

  const release = await publishWindowsRelease({
    projectUrl: serverUrl,
    apiKey: storageApiKey,
    version: '0.1.0',
    notes: 'Bản phát hành thử nghiệm.',
    signature: 'test-updater-signature',
    installerPath,
    publishedAt: new Date('2026-10-19T00:00:00.000Z'),
  })

  assert.deepEqual(uploadedInstaller, installerBytes)
  assert.ok(release.installerUrl.includes(`/releases/0.1.0/${release.contentHash}/CRM-Spa-0.1.0-windows-x86_64-setup.exe`))
  assert.equal(release.latestManifestUrl, `${serverUrl}/storage/v1/object/public/desktop-updates/latest.json`)
  assert.equal(publishedManifest.version, '0.1.0')
  assert.equal(serverError, undefined)
  assert.equal(publishedManifest.platforms['windows-x86_64'].url, release.installerUrl)
  assert.equal(publishedManifest.platforms['windows-x86_64'].signature, 'test-updater-signature')
  assert.equal(requestOrder.at(-1), '/storage/v1/object/desktop-updates/latest.json')
  assert.ok(requestOrder.indexOf('/storage/v1/object/desktop-updates/latest.json') > requestOrder.indexOf('/storage/v1/upload/resumable/session-1'))
})

test('does not publish latest.json when installer upload fails', async () => {
  const installerPath = path.join(temporaryDirectory, 'failed-setup.exe')
  requestOrder = []
  serverError = undefined
  failInstallerUpload = true
  const installerBytes = Buffer.from('verified test Windows installer')
  expectedUploadLength = installerBytes.byteLength
  await writeFile(installerPath, installerBytes)

  try {
    await assert.rejects(
      publishWindowsRelease({
        projectUrl: serverUrl,
        apiKey: storageApiKey,
        version: '0.1.1',
        notes: 'Bản phát hành thử nghiệm.',
        signature: 'test-updater-signature',
        installerPath,
      }),
      /HTTP 503/,
    )
  } finally {
    failInstallerUpload = false
  }

  assert.equal(requestOrder.includes('/storage/v1/object/desktop-updates/latest.json'), false)
  assert.equal(serverError, undefined)
})

