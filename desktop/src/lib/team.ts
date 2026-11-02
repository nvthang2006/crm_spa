import type { AppSupabaseClient } from './supabase'

export type Employee = {
  id: number
  code: string
  name: string
  phone: string | null
  job_title: string
  shift_name: string | null
  base_salary: number | null
  status: string
  deleted_at: string | null
}

export type EmployeeDraft = {
  code: string
  name: string
  job_title: string
  base_salary: string
}

export type OfferingOption = { id: number; code: string; name: string; type: string; status: string }

export type CommissionRule = {
  id: number
  offering_id: number
  beneficiary_employee_id: number | null
  beneficiary_type: string
  basis: string
  calculation_type: string
  value: number
  starts_on: string
  ends_on: string | null
  is_active: boolean
  notes: string | null
  offering: OfferingOption | null
  beneficiary_employee: Pick<Employee, 'id' | 'code' | 'name' | 'status'> | null
}

export type CommissionRuleDraft = {
  offering_id: string
  beneficiary_type: string
  beneficiary_employee_id: string
  basis: string
  calculation_type: string
  value: string
  starts_on: string
  ends_on: string
  is_active: boolean
  notes: string
}

export type BusinessSettings = {
  id: number
  business_name: string
  address: string
  phone: string | null
  time_zone: string
}

export type BusinessSettingsDraft = Pick<BusinessSettings, 'business_name' | 'address'> & { phone: string }

export async function loadEmployees(client: AppSupabaseClient): Promise<Employee[]> {
  const { data, error } = await client.from('employees')
    .select('id, code, name, phone, job_title, shift_name, base_salary, status, deleted_at')
    .is('deleted_at', null).order('name').limit(500)
  if (error) throw error
  return (data ?? []) as Employee[]
}

export async function saveEmployee(client: AppSupabaseClient, draft: EmployeeDraft, employeeId: number | null): Promise<number> {
  const { data, error } = await client.rpc('save_employee', {
    p_employee_id: employeeId,
    p_code: employeeId === null ? '' : draft.code.trim(),
    p_name: draft.name.trim(),
    p_phone: null,
    p_job_title: draft.job_title.trim(),
    p_shift_name: null,
    p_base_salary: draft.base_salary.trim() === '' ? null : Number(draft.base_salary),
  })
  if (error) throw error
  return Number(data)
}

export async function setEmployeeStatus(client: AppSupabaseClient, employeeId: number): Promise<string> {
  const { data, error } = await client.rpc('set_employee_status', { p_employee_id: employeeId })
  if (error) throw error
  return String(data)
}

export async function loadCommissionOptions(client: AppSupabaseClient): Promise<{ offerings: OfferingOption[]; employees: Employee[] }> {
  const [offerings, employees] = await Promise.all([
    client.from('offerings').select('id, code, name, type, status').is('deleted_at', null).order('name').limit(500),
    client.from('employees').select('id, code, name, phone, job_title, shift_name, base_salary, status, deleted_at').is('deleted_at', null).order('name').limit(500),
  ])
  if (offerings.error) throw offerings.error
  if (employees.error) throw employees.error
  return { offerings: (offerings.data ?? []) as OfferingOption[], employees: (employees.data ?? []) as Employee[] }
}

export async function loadCommissionRules(client: AppSupabaseClient): Promise<CommissionRule[]> {
  const [{ data: rules, error }, options] = await Promise.all([
    client.from('commission_rules').select('id, offering_id, beneficiary_employee_id, beneficiary_type, basis, calculation_type, value, starts_on, ends_on, is_active, notes')
      .order('starts_on', { ascending: false }).order('id', { ascending: false }).limit(500),
    loadCommissionOptions(client),
  ])
  if (error) throw error
  const offeringsById = new Map(options.offerings.map((row) => [row.id, row]))
  const employeesById = new Map(options.employees.map((row) => [row.id, row]))
  return (rules ?? []).map((rule) => ({
    ...rule,
    value: Number(rule.value),
    offering: offeringsById.get(Number(rule.offering_id)) ?? null,
    beneficiary_employee: rule.beneficiary_employee_id ? employeesById.get(Number(rule.beneficiary_employee_id)) ?? null : null,
  })) as CommissionRule[]
}

export async function saveCommissionRule(client: AppSupabaseClient, draft: CommissionRuleDraft, ruleId: number | null): Promise<number> {
  const { data, error } = await client.rpc('save_commission_rule', {
    p_rule_id: ruleId,
    p_offering_id: Number(draft.offering_id),
    p_beneficiary_type: draft.beneficiary_type,
    p_beneficiary_employee_id: draft.beneficiary_employee_id ? Number(draft.beneficiary_employee_id) : null,
    p_basis: draft.basis,
    p_calculation_type: draft.calculation_type,
    p_value: Number(draft.value),
    p_starts_on: draft.starts_on,
    p_ends_on: draft.ends_on || null,
    p_is_active: draft.is_active,
    p_notes: draft.notes.trim() || null,
  })
  if (error) throw error
  return Number(data)
}

export async function setCommissionRuleActive(client: AppSupabaseClient, ruleId: number): Promise<boolean> {
  const { data, error } = await client.rpc('set_commission_rule_active', { p_rule_id: ruleId })
  if (error) throw error
  return Boolean(data)
}

export async function loadBusinessSettings(client: AppSupabaseClient): Promise<BusinessSettings> {
  const { data, error } = await client.from('business_settings')
    .select('id, business_name, address, phone, time_zone').eq('id', 1).single()
  if (error) throw error
  return data as BusinessSettings
}

export async function saveBusinessSettings(client: AppSupabaseClient, draft: BusinessSettingsDraft): Promise<void> {
  const { error } = await client.rpc('save_business_settings', {
    p_business_name: draft.business_name.trim(),
    p_address: draft.address.trim(),
    p_phone: draft.phone.trim() || null,
  })
  if (error) throw error
}

export function todayInBusinessTimeZone(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
}
