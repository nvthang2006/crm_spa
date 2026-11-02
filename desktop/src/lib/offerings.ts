import type { AppSupabaseClient } from './supabase'

export type OfferingCommissionConfig = {
  enabled: boolean
  ruleId: number | null
  calculationType: 'fixed' | 'percentage'
  value: number
  startsOn?: string
}

export type Offering = {
  id: number
  service_category_id: number | null
  code: string
  name: string
  type: 'service' | 'package'
  duration_minutes: number
  price: number | null
  sessions_total: number
  status: 'active' | 'inactive'
  featured: boolean
  description: string | null
  created_at: string
  updated_at: string
  commission?: OfferingCommissionConfig | null
}

export type OfferingDraft = Pick<
  Offering,
  'code' | 'name' | 'type' | 'duration_minutes' | 'price' | 'sessions_total' | 'status' | 'featured'
> & {
  description: string
  commission: OfferingCommissionConfig
}

export async function loadOfferings(client: AppSupabaseClient): Promise<Offering[]> {
  const [{ data: offerings, error: offeringsError }, { data: rules, error: rulesError }] = await Promise.all([
    client
      .from('offerings')
      .select('id, service_category_id, code, name, type, duration_minutes, price, sessions_total, status, featured, description, created_at, updated_at')
      .is('deleted_at', null)
      .order('name'),
    client
      .from('commission_rules')
      .select('id, offering_id, beneficiary_type, calculation_type, value, starts_on, ends_on, is_active')
      .eq('beneficiary_type', 'primary_technician')
      .eq('is_active', true)
      .order('starts_on', { ascending: false })
      .order('id', { ascending: false }),
  ])

  if (offeringsError) throw offeringsError
  if (rulesError) throw rulesError

  const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
  const rulesMap = new Map<number, OfferingCommissionConfig>()

  for (const rule of rules ?? []) {
    if (!rule.offering_id || rulesMap.has(rule.offering_id)) continue
    if (rule.starts_on <= today && (!rule.ends_on || rule.ends_on >= today)) {
      rulesMap.set(rule.offering_id, {
        enabled: true,
        ruleId: rule.id,
        calculationType: rule.calculation_type as 'fixed' | 'percentage',
        value: Number(rule.value),
        startsOn: rule.starts_on,
      })
    }
  }

  return (offerings ?? []).map((offering) => ({
    ...(offering as Offering),
    commission: rulesMap.get(offering.id) ?? null,
  }))
}

export async function saveOffering(client: AppSupabaseClient, draft: OfferingDraft, existing: Offering | null): Promise<number> {
  const { data: savedId, error: saveError } = await client.rpc('save_offering', {
    p_offering_id: existing?.id ?? null,
    p_code: existing?.code ?? '',
    p_name: draft.name.trim(),
    p_type: draft.type,
    p_duration_minutes: draft.duration_minutes,
    p_price: draft.price,
    p_sessions_total: draft.sessions_total,
    p_status: draft.status,
    p_featured: draft.featured,
    p_description: draft.description.trim() || null,
  })

  if (saveError) throw saveError

  const offeringId = Number(savedId ?? existing?.id)
  const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })

  if (draft.commission.enabled && draft.commission.value > 0) {
    const { error: ruleError } = await client.rpc('save_commission_rule', {
      p_rule_id: draft.commission.ruleId,
      p_offering_id: offeringId,
      p_beneficiary_type: 'primary_technician',
      p_beneficiary_employee_id: null,
      p_basis: 'completion',
      p_calculation_type: draft.commission.calculationType,
      p_value: draft.commission.value,
      p_starts_on: draft.commission.startsOn || today,
      p_ends_on: null,
      p_is_active: true,
      p_notes: 'Hoa hồng KTV thực hiện',
    })
    if (ruleError) throw ruleError
  } else if (!draft.commission.enabled && draft.commission.ruleId) {
    const { error: ruleError } = await client.rpc('save_commission_rule', {
      p_rule_id: draft.commission.ruleId,
      p_offering_id: offeringId,
      p_beneficiary_type: 'primary_technician',
      p_beneficiary_employee_id: null,
      p_basis: 'completion',
      p_calculation_type: draft.commission.calculationType,
      p_value: draft.commission.value || 0,
      p_starts_on: draft.commission.startsOn || today,
      p_ends_on: null,
      p_is_active: false,
      p_notes: 'Tắt hoa hồng KTV',
    })
    if (ruleError) throw ruleError
  }

  return offeringId
}

export function formatVnd(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(amount)
}
