import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import { invoke, isTauri } from '@tauri-apps/api/core'
import type { Database } from './database.types'

export type AppSupabaseClient = SupabaseClient<Database>

const url = import.meta.env.VITE_SUPABASE_URL?.trim()
const publishableKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY?.trim()
let client: AppSupabaseClient | null = null

export function hasDesktopConfiguration(): boolean { return Boolean(url && publishableKey) }

export function getSupabase(): AppSupabaseClient | null {
  if (!hasDesktopConfiguration()) return null
  if (client) return client
  const authStorage = {
    getItem: async (key: string): Promise<string | null> => isTauri() ? invoke<string | null>('credential_read', { key }) : window.sessionStorage.getItem(key),
    setItem: async (key: string, value: string): Promise<void> => {
      if (isTauri()) await invoke('credential_write', { key, value })
      else window.sessionStorage.setItem(key, value)
    },
    removeItem: async (key: string): Promise<void> => {
      if (isTauri()) await invoke('credential_delete', { key })
      else window.sessionStorage.removeItem(key)
    },
  }
  client = createClient<Database>(url!, publishableKey!, {
    auth: { storage: authStorage, persistSession: true, autoRefreshToken: true, detectSessionInUrl: false },
    global: { headers: { 'x-application-name': 'crm-spa-desktop' } },
  })
  return client
}
