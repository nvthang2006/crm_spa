import { spawnSync } from 'node:child_process'
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const githubUpdaterEndpoint = 'https://github.com/nvthang2006/crm_spa/releases/latest/download/latest.json'

export function createWindowsReleaseConfig({ tauriConfig, updaterPublicKey, signingPrivateKey }) {
  const updater = tauriConfig.plugins?.updater
  if (!updater?.pubkey?.trim() || !signingPrivateKey?.trim()) {
    throw new Error('Cần public key trong tauri.conf.json và TAURI_SIGNING_PRIVATE_KEY trước khi đóng gói.')
  }
  if (updaterPublicKey && updaterPublicKey.trim() !== updater.pubkey.trim()) {
    throw new Error('TAURI_UPDATER_PUBKEY khác khóa đã cấu hình; không thể đổi khóa của các bản đang cài.')
  }
  if (updater.endpoints?.length !== 1 || updater.endpoints[0] !== githubUpdaterEndpoint) {
    throw new Error('Bản Windows phải giữ endpoint GitHub Releases tương thích với bản 0.1.2.')
  }
  return {
    bundle: { createUpdaterArtifacts: true },
    plugins: {
      updater: {
        ...updater,
        requireSignedVersion: true,
        windows: { ...updater.windows, installMode: 'passive' },
      },
    },
  }
}

function buildWindows() {
  const tauriConfig = JSON.parse(readFileSync(path.join(projectRoot, 'src-tauri', 'tauri.conf.json'), 'utf8'))
  const releaseConfig = createWindowsReleaseConfig({
    tauriConfig,
    updaterPublicKey: process.env.TAURI_UPDATER_PUBKEY,
    signingPrivateKey: process.env.TAURI_SIGNING_PRIVATE_KEY,
  })
  const tempRoot = mkdtempSync(path.join(os.tmpdir(), 'crm-spa-tauri-'))
  const releaseConfigPath = path.join(tempRoot, 'tauri.release.conf.json')
  try {
    writeFileSync(releaseConfigPath, JSON.stringify(releaseConfig), { mode: 0o600 })
    const tauriCliPath = path.join(projectRoot, 'node_modules', '@tauri-apps', 'cli', 'tauri.js')
    if (!existsSync(tauriCliPath)) throw new Error('Không tìm thấy Tauri CLI. Hãy chạy npm install trong thư mục desktop trước.')
    const result = spawnSync(process.execPath, [tauriCliPath, 'build', '--bundles', 'nsis', '--config', releaseConfigPath], {
      cwd: projectRoot,
      stdio: 'inherit',
      env: process.env,
    })
    if (result.error) throw result.error
    process.exitCode = result.status ?? 1
  } finally {
    rmSync(tempRoot, { recursive: true, force: true })
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    buildWindows()
  } catch (error) {
    console.error(error.message)
    process.exitCode = 1
  }
}
