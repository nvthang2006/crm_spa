import type { AppSupabaseClient } from './supabase'

export type CustomerRecord = {
  id: number
  code: string
  name: string
  phone: string | null
  email: string | null
  membership_tier: string
  birthday: string | null
  address: string | null
  total_spend: number
  last_visit_at: string | null
  status: string
  notes: string | null
  created_at: string
}

export type CustomerDraft = Pick<CustomerRecord, 'code' | 'name' | 'email' | 'membership_tier' | 'birthday' | 'address' | 'notes'> & { phone: string }
export type CustomerHealthProfile = {
  customer_id: number
  preferred_employee_id: number | null
  allergy_notes: string | null
  current_condition: string | null
  contraindications: string | null
  care_notes: string | null
}
export type CustomerPackage = {
  id: number
  customer_id: number
  offering_id: number
  code: string
  purchased_on: string
  starts_on: string | null
  expires_on: string | null
  sessions_total: number
  sessions_used: number
  unit_price: number
  status: string
  notes: string | null
  offering: { id: number; name: string } | null
  usages: PackageUsage[]
}
export type PackageUsage = {
  id: number
  session_number: number
  direction: 'use' | 'restore'
  verification_note: string | null
  used_at: string
  performed_by: number | null
  performer: { name: string } | null
}
export type CustomerEmployeeOption = { id: number; name: string; job_title: string }

const customerFields = 'id, code, name, phone, email, membership_tier, birthday, address, total_spend, last_visit_at, status, notes, created_at'

export async function loadCustomers(
  client: AppSupabaseClient,
  options: { search: string; tier: string; page: number; pageSize?: number },
): Promise<{ rows: CustomerRecord[]; total: number }> {
  const pageSize = options.pageSize ?? 12
  let query = client.from('customers').select(customerFields, { count: 'exact' }).is('deleted_at', null).order('name')
  if (options.tier !== 'all') query = query.eq('membership_tier', options.tier)
  const term = options.search.trim().replace(/[,%()\\]/g, ' ').replace(/\s+/g, ' ')
  if (term) query = query.or(`name.ilike.%${term}%,code.ilike.%${term}%,phone.ilike.%${term}%`)
  const from = options.page * pageSize
  const { data, error, count } = await query.range(from, from + pageSize - 1)
  if (error) throw error
  return { rows: (data ?? []) as CustomerRecord[], total: count ?? 0 }
}

export async function loadCustomerTiers(client: AppSupabaseClient): Promise<string[]> {
  const { data, error } = await client.from('customers').select('membership_tier').is('deleted_at', null).order('membership_tier')
  if (error) throw error
  return [...new Set((data ?? []).map((row) => row.membership_tier as string))]
}

export async function loadCustomerEmployees(client: AppSupabaseClient): Promise<CustomerEmployeeOption[]> {
  const { data, error } = await client.from('employees').select('id, name, job_title').eq('status', 'active').is('deleted_at', null).order('name').limit(500)
  if (error) throw error
  return (data ?? []) as CustomerEmployeeOption[]
}

export async function loadCustomerPackages(client: AppSupabaseClient, customerId: number): Promise<CustomerPackage[]> {
  const { data, error } = await client.from('customer_packages')
    .select('id, customer_id, offering_id, code, purchased_on, starts_on, expires_on, sessions_total, sessions_used, unit_price, status, notes, offering:offerings(id, name), usages:package_usages(id, session_number, direction, verification_note, used_at, performed_by, performer:employees!package_usages_performed_by_fkey(name))')
    .eq('customer_id', customerId).order('purchased_on', { ascending: false })
  if (error) throw error
  return (data ?? []) as unknown as CustomerPackage[]
}

export async function loadCustomerHealth(client: AppSupabaseClient, customerId: number): Promise<CustomerHealthProfile> {
  const { data, error } = await client.rpc('get_customer_health_profile', { p_customer_id: customerId })
  if (error) throw error
  return data as CustomerHealthProfile
}

export async function saveCustomer(
  client: AppSupabaseClient,
  draft: CustomerDraft,
  customerId: number | null,
  health?: CustomerHealthProfile,
): Promise<CustomerRecord> {
  const { data, error } = await client.rpc('save_customer', {
    p_customer_id: customerId,
    p_code: customerId === null ? '' : draft.code,
    p_name: draft.name.trim(),
    p_phone: draft.phone.trim() || null,
    p_email: null,
    p_membership_tier: draft.membership_tier,
    p_birthday: draft.birthday || null,
    p_address: null,
    p_notes: draft.notes?.trim() || null,
    p_preferred_employee_id: health?.preferred_employee_id ?? null,
    p_allergy_notes: health?.allergy_notes ?? null,
    p_current_condition: health?.current_condition ?? null,
    p_contraindications: health?.contraindications ?? null,
    p_care_notes: health?.care_notes ?? null,
    p_update_health: Boolean(health),
  })
  if (error) throw error
  return data as CustomerRecord
}

export async function consumeCustomerPackage(
  client: AppSupabaseClient,
  packageId: number,
  performedBy: number | null,
  note: string,
): Promise<void> {
  const { error } = await client.rpc('consume_customer_package_session', {
    p_customer_package_id: packageId,
    p_performed_by: performedBy,
    p_note: note.trim() || null,
  })
  if (error) throw error
}

export async function restoreCustomerPackageSession(
  client: AppSupabaseClient,
  packageId: number,
  sessionNumber: number,
  reason: string,
): Promise<void> {
  const { error } = await client.rpc('restore_customer_package_session', {
    p_customer_package_id: packageId,
    p_session_number: sessionNumber,
    p_reason: reason.trim(),
  })
  if (error) throw error
}

export async function exportCustomers(client: AppSupabaseClient, search: string, tier: string): Promise<CustomerRecord[]> {
  const pageSize = 500
  const rows: CustomerRecord[] = []
  const term = search.trim().replace(/[,%()\\]/g, ' ').replace(/\s+/g, ' ')
  for (let from = 0; ; from += pageSize) {
    let query = client.from('customers').select(customerFields).is('deleted_at', null).order('name').range(from, from + pageSize - 1)
    if (tier !== 'all') query = query.eq('membership_tier', tier)
    if (term) query = query.or(`name.ilike.%${term}%,code.ilike.%${term}%,phone.ilike.%${term}%`)
    const { data, error } = await query
    if (error) throw error
    const page = (data ?? []) as CustomerRecord[]
    rows.push(...page)
    if (page.length < pageSize) return rows
  }
}

export function customerDraft(customer?: CustomerRecord): CustomerDraft {
  return {
    code: customer?.code ?? '',
    name: customer?.name ?? '',
    phone: customer?.phone ?? '',
    email: customer?.email ?? '',
    membership_tier: customer?.membership_tier ?? 'Thành viên',
    birthday: customer?.birthday ?? '',
    address: customer?.address ?? '',
    notes: customer?.notes ?? '',
  }
}

export function downloadCustomersCsv(rows: CustomerRecord[]): void {
  const fields = ['Mã khách hàng', 'Họ và tên', 'Số điện thoại', 'Email', 'Hạng thẻ', 'Tổng chi tiêu', 'Trạng thái']
  const lines = [fields, ...rows.map((row) => [row.code, row.name, row.phone ?? '', row.email ?? '', row.membership_tier, String(row.total_spend), row.status])]
  const csv = '\ufeff' + lines.map((line) => line.map((cell) => `"${cell.replaceAll('"', '""')}"`).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = `khach-hang-${new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })}.csv`
  anchor.click()
  URL.revokeObjectURL(url)
}
