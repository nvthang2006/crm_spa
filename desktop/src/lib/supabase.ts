import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type { Database } from './database.types'

export type AppSupabaseClient = SupabaseClient<Database>

const url = import.meta.env.VITE_SUPABASE_URL?.trim()
const publishableKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY?.trim()
let client: AppSupabaseClient | null = null

export function hasDesktopConfiguration(): boolean { return Boolean(url && publishableKey) }

export function getSupabase(): AppSupabaseClient | null {
  if (!hasDesktopConfiguration()) return null
  if (client) return client
  client = createClient<Database>(url!, publishableKey!, {
    auth: { storage: window.localStorage, persistSession: true, autoRefreshToken: true, detectSessionInUrl: false },
    global: { headers: { 'x-application-name': 'crm-spa-desktop' } },
  })
  return client
}