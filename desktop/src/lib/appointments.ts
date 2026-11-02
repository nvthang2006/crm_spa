import type { AppSupabaseClient } from './supabase'

export type AppointmentStatus =
  | 'scheduled'
  | 'arrived'
  | 'in_service'
  | 'completed_pending_payment'
  | 'paid'
  | 'package_used'
  | 'cancelled'
  | 'no_show'
  | 'refunded'

export type AppointmentRecord = {
  id: number
  code: string
  customer_id: number
  offering_id: number
  primary_employee_id: number | null
  support_employee_id: number | null
  service_resource_id: number | null
  customer_package_id: number | null
  appointment_date: string
  start_time: string | null
  end_time: string | null
  status: AppointmentStatus
  source: 'booked' | 'walkin'
  payment_type: 'service' | 'package'
  checked_in_at: string | null
  started_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  override_conflict_reason: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export type AppointmentRow = AppointmentRecord & {
  customer: { id: number; name: string; phone: string | null } | null
  offering: { id: number; name: string; type: 'service' | 'package' } | null
  primary_employee: { id: number; name: string; job_title: string } | null
  support_employee: { id: number; name: string; job_title: string } | null
  resource: { id: number; name: string; status: string } | null
}

export type AppointmentCustomerOption = { id: number; name: string; phone: string | null }
export type AppointmentOfferingOption = { id: number; name: string; type: 'service' | 'package'; duration_minutes: number; price: number | null }
export type AppointmentEmployeeOption = { id: number; name: string; job_title: string }
export type AppointmentResourceOption = { id: number; name: string; status: string }
export type AppointmentPackageOption = { id: number; code: string; customer_id: number; offering_id: number; expires_on: string | null; starts_on: string | null; sessions_used: number; sessions_total: number }

export type AppointmentOptions = {
  customers: AppointmentCustomerOption[]
  offerings: AppointmentOfferingOption[]
  employees: AppointmentEmployeeOption[]
  resources: AppointmentResourceOption[]
}

export type AppointmentDraft = {
  id: number | null
  customerId: number
  offeringId: number
  primaryEmployeeId: number | null
  supportEmployeeId: number | null
  resourceId: number | null
  date: string
  startTime: string | null
  endTime: string | null
  paymentType: 'service' | 'package'
  packageId: number | null
  source: 'booked' | 'walkin'
  notes: string
  overrideReason: string | null
}

export type AppointmentCompletion = {
  appointment: AppointmentRecord
  invoice?: { id: number; code: string; status: string } | null
  package?: { id: number; sessions_used: number; sessions_total: number; status: string } | null
  usage?: { id: number; session_number: number; used_at: string } | null
}

const appointmentSelection = `
  id, code, customer_id, offering_id, primary_employee_id, support_employee_id,
  service_resource_id, customer_package_id, appointment_date, start_time, end_time,
  status, source, payment_type, checked_in_at, started_at, completed_at, cancelled_at,
  cancel_reason, override_conflict_reason, notes, created_at, updated_at,
  customer:customers(id, name, phone),
  offering:offerings(id, name, type),
  primary_employee:employees!appointments_primary_employee_id_fkey(id, name, job_title),
  support_employee:employees!appointments_support_employee_id_fkey(id, name, job_title),
  resource:service_resources(id, name, status)
`

export async function loadAppointments(client: AppSupabaseClient, date: string): Promise<AppointmentRow[]> {
  const pageSize = 500
  const rows: AppointmentRow[] = []

  for (let from = 0; ; from += pageSize) {
    const { data, error } = await client.from('appointments')
      .select(appointmentSelection)
      .eq('appointment_date', date)
      .order('start_time')
      .range(from, from + pageSize - 1)
    if (error) throw error
    const page = (data ?? []) as unknown as AppointmentRow[]
    rows.push(...page)
    if (page.length < pageSize) return rows
  }
}

export async function loadAppointmentOptions(client: AppSupabaseClient): Promise<AppointmentOptions> {
  const [customers, offerings, employees, resources] = await Promise.all([
    client.from('customers').select('id, name, phone').eq('status', 'active').is('deleted_at', null).order('name').limit(1000),
    client.from('offerings').select('id, name, type, duration_minutes, price').eq('status', 'active').is('deleted_at', null).order('name').limit(500),
    client.from('employees').select('id, name, job_title').eq('status', 'active').is('deleted_at', null).order('name').limit(500),
    client.from('service_resources').select('id, name, status').eq('status', 'ready').is('deleted_at', null).order('name').limit(500),
  ])
  const error = customers.error ?? offerings.error ?? employees.error ?? resources.error
  if (error) throw error
  return {
    customers: (customers.data ?? []) as AppointmentCustomerOption[],
    offerings: (offerings.data ?? []) as AppointmentOfferingOption[],
    employees: (employees.data ?? []) as AppointmentEmployeeOption[],
    resources: (resources.data ?? []) as AppointmentResourceOption[],
  }
}

export async function loadAppointmentPackages(
  client: AppSupabaseClient,
  customerId: number,
  offeringId: number,
  date: string,
): Promise<AppointmentPackageOption[]> {
  const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
  const expiryDate = date > today ? date : today
  const { data, error } = await client.from('customer_packages')
    .select('id, code, customer_id, offering_id, expires_on, starts_on, sessions_used, sessions_total')
    .eq('customer_id', customerId)
    .eq('offering_id', offeringId)
    .eq('status', 'active')
    .lte('purchased_on', date)
    .or('expires_on.is.null,expires_on.gte.' + expiryDate)
    .order('expires_on', { nullsFirst: false }).order('purchased_on').order('id')
  if (error) throw error
  return ((data ?? []) as AppointmentPackageOption[]).filter((item) => item.sessions_used < item.sessions_total && (!item.starts_on || item.starts_on <= date))
}

export async function saveAppointment(client: AppSupabaseClient, draft: AppointmentDraft): Promise<AppointmentRecord> {
  const { data, error } = await client.rpc('save_appointment', {
    p_appointment_id: draft.id,
    p_customer_id: draft.customerId,
    p_offering_id: draft.offeringId,
    p_primary_employee_id: draft.primaryEmployeeId,
    p_support_employee_id: draft.supportEmployeeId,
    p_service_resource_id: draft.resourceId,
    p_appointment_date: draft.date,
    p_start_time: draft.startTime,
    p_end_time: draft.endTime,
    p_payment_type: draft.paymentType,
    p_customer_package_id: draft.packageId,
    p_source: draft.source,
    p_notes: draft.notes.trim() || null,
    p_override_reason: draft.overrideReason?.trim() || null,
  })
  if (error) throw error
  return data as unknown as AppointmentRecord
}

export async function transitionAppointment(
  client: AppSupabaseClient,
  id: number,
  status: 'arrived' | 'in_service' | 'cancelled' | 'no_show',
  reason?: string,
): Promise<AppointmentRecord> {
  const { data, error } = await client.rpc('transition_appointment', {
    p_appointment_id: id,
    p_status: status,
    p_reason: reason?.trim() || null,
  })
  if (error) throw error
  return data as unknown as AppointmentRecord
}

export async function completeAppointment(client: AppSupabaseClient, id: number): Promise<AppointmentCompletion> {
  const { data, error } = await client.rpc('complete_appointment', { p_appointment_id: id })
  if (error) throw error
  return data as unknown as AppointmentCompletion
}

export async function consumePackageSession(client: AppSupabaseClient, id: number, note?: string): Promise<AppointmentCompletion> {
  const { data, error } = await client.rpc('use_package_session', {
    p_appointment_id: id,
    p_note: note?.trim() || null,
  })
  if (error) throw error
  return data as unknown as AppointmentCompletion
}

export function parseAppointmentDate(value: string): string | null {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value)
  if (!match) return null
  const iso = `${match[3]}-${match[2]}-${match[1]}`
  const date = new Date(iso + 'T12:00:00Z')
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === iso ? iso : null
}
