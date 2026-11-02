import { spawnSync } from 'node:child_process'
import { existsSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const supabaseUrl = process.env.VITE_SUPABASE_URL?.replace(/\/$/, '')
const updaterPublicKey = process.env.TAURI_UPDATER_PUBKEY
const signingPrivateKey = process.env.TAURI_SIGNING_PRIVATE_KEY

if (!supabaseUrl || !supabaseUrl.startsWith('https://') || !updaterPublicKey || !signingPrivateKey) {
  console.error('Cần cấu hình VITE_SUPABASE_URL, TAURI_UPDATER_PUBKEY và TAURI_SIGNING_PRIVATE_KEY trước khi đóng gói.')
  process.exit(1)
}

const tempRoot = mkdtempSync(path.join(os.tmpdir(), 'crm-spa-tauri-'))
const releaseConfigPath = path.join(tempRoot, 'tauri.release.conf.json')
const releaseConfig = {
  bundle: { createUpdaterArtifacts: true },
  plugins: {
    updater: {
      pubkey: updaterPublicKey,
      endpoints: [`${supabaseUrl}/storage/v1/object/public/desktop-updates/latest.json`],
      requireSignedVersion: true,
      windows: { installMode: 'passive' },
    },
  },
}

try {
  writeFileSync(releaseConfigPath, JSON.stringify(releaseConfig), { mode: 0o600 })
  const tauriCliPath = path.join(projectRoot, 'node_modules', '@tauri-apps', 'cli', 'tauri.js')

  if (!existsSync(tauriCliPath)) {
    console.error('Không tìm thấy Tauri CLI. Hãy chạy npm install trong thư mục desktop trước.')
    process.exitCode = 1
  } else {
    const result = spawnSync(process.execPath, [tauriCliPath, 'build', '--bundles', 'nsis', '--config', releaseConfigPath], {
      cwd: projectRoot,
      stdio: 'inherit',
      env: process.env,
    })

    if (result.error) {
      console.error(`Không thể chạy Tauri CLI: ${result.error.message}`)
      process.exitCode = 1
    } else {
      process.exitCode = result.status ?? 1
    }
  }
} finally {
  rmSync(tempRoot, { recursive: true, force: true })
}
