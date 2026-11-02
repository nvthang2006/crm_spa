import type { AppSupabaseClient } from './supabase'
import type { Database } from './database.types'

export type InvoiceStatus = 'draft' | 'pending' | 'paid' | 'void' | 'refunded'
export type InvoiceMethod = 'cash' | 'vietqr' | 'pos'
export type InvoiceRecord = {
  id: number
  customer_id: number
  appointment_id: number | null
  customer_package_id: number | null
  code: string
  type: 'service' | 'package'
  status: InvoiceStatus
  subtotal: number
  discount_amount: number
  total_received: number
  payment_method: InvoiceMethod | null
  issued_at: string | null
  paid_at: string | null
  voided_at: string | null
  notes: string | null
  customer: { id: number; code: string; name: string; phone: string | null } | null
  items: InvoiceItem[]
  payments: InvoicePayment[]
  refund: InvoiceRefund | null
  customer_package: { id: number; code: string; sessions_total: number; expires_on: string | null } | null
}
export type InvoiceItem = {
  id: number; offering_id: number | null; employee_id: number | null; description: string; quantity: number
  unit_price: number; line_total: number; package_sessions: number; offering: { name: string } | null
  employee: { name: string } | null
}
export type InvoicePayment = { id: number; receipt_code: string; method: InvoiceMethod; amount: number; reference: string | null; paid_at: string }
export type InvoiceRefund = { id: number; code: string; method: InvoiceMethod; amount: number; reference: string | null; reason: string; refunded_at: string }
export type InvoiceCustomerOption = { id: number; code: string; name: string; phone: string | null }
export type InvoiceOfferingOption = { id: number; code: string; name: string; type: 'service' | 'package'; price: number | null; sessions_total: number }
export type InvoiceEmployeeOption = { id: number; name: string; job_title: string }
export type InvoiceAppointmentOption = { id: number; code: string; customer_id: number; offering_id: number; appointment_date: string; start_time: string | null; customer: { name: string } | null; offering: { name: string } | null }
export type InvoiceFilters = { search: string; status: string; method: string; dateFrom: string; dateTo: string; page: number }
export type InvoicePage = { rows: InvoiceRecord[]; total: number }

type PendingInvoiceRequest = { requestId: string; storageKey: string }
type InvoiceOperation = 'create_invoice' | 'pay_invoice' | 'void_invoice' | 'refund_invoice'
type InvoiceIdempotentRpc = 'create_invoice_idempotent' | 'pay_invoice_idempotent' | 'void_invoice_idempotent' | 'refund_invoice_idempotent'
type InvoiceIdempotentRpcArgs<Name extends InvoiceIdempotentRpc> = Database['public']['Functions'][Name]['Args']

export class InvoiceRequestConflictError extends Error {
  constructor() {
    super('Yêu cầu trước đã được lưu trên Supabase với nội dung khác. Danh sách hóa đơn đang được làm mới; hãy đối soát trước khi bắt đầu thao tác mới.')
    this.name = 'InvoiceRequestConflictError'
  }
}

async function prepareInvoiceRequest(client: AppSupabaseClient, operation: InvoiceOperation, scope: string): Promise<PendingInvoiceRequest> {
  const { data, error } = await client.auth.getSession()
  if (error) throw error
  const userId = data.session?.user.id
  if (!userId) throw new Error('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại trước khi tiếp tục.')

  const storageKey = `crm-spa:pending-request:${userId}:${operation}:${scope}`
  const existingRequestId = window.localStorage.getItem(storageKey)
  const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
  const requestId = existingRequestId && uuidPattern.test(existingRequestId) ? existingRequestId : window.crypto.randomUUID()
  if (requestId !== existingRequestId) window.localStorage.setItem(storageKey, requestId)
  return { requestId, storageKey }
}

function completeInvoiceRequest(storageKey: string): void {
  try {
    window.localStorage.removeItem(storageKey)
  } catch {
    throw new Error('Giao dịch đã được lưu trên Supabase nhưng ứng dụng chưa thể dọn mã khôi phục cục bộ. Hãy làm mới danh sách hóa đơn trước khi thao tác tiếp.')
  }
}

async function runIdempotentInvoiceRequest<Name extends InvoiceIdempotentRpc, R = any>(
  client: AppSupabaseClient,
  operation: InvoiceOperation,
  scope: string,
  rpcName: Name,
  rpcPayload: (requestId: string) => InvoiceIdempotentRpcArgs<Name>,
): Promise<R> {
  const pending = await prepareInvoiceRequest(client, operation, scope)
  const { data, error } = await client.rpc(rpcName, rpcPayload(pending.requestId))
  if (error) {
    if (error.code === '22023' && error.message === 'Mã yêu cầu đã được dùng với nội dung khác.') {
      completeInvoiceRequest(pending.storageKey)
      throw new InvoiceRequestConflictError()
    }
    throw error
  }
  completeInvoiceRequest(pending.storageKey)
  return data as R
}

const invoiceFields = `id, customer_id, appointment_id, customer_package_id, code, type, status, subtotal, discount_amount, total_received, payment_method, issued_at, paid_at, voided_at, notes,
  customer:customers(id, code, name, phone),
  items:invoice_items(id, offering_id, employee_id, description, quantity, unit_price, line_total, package_sessions, offering:offerings(name), employee:employees!invoice_items_employee_id_fkey(name)),
  payments(id, receipt_code, method, amount, reference, paid_at),
  refund:invoice_refunds(id, code, method, amount, reference, reason, refunded_at),
  customer_package:customer_packages(id, code, sessions_total, expires_on)`

export async function loadInvoices(client: AppSupabaseClient, filters: InvoiceFilters, pageSize = 10): Promise<InvoicePage> {
  const from = filters.page * pageSize
  const { data: matches, error: matchError } = await client.rpc('search_invoice_ids', {
    p_search: filters.search.trim(),
    p_status: filters.status,
    p_method: filters.method,
    p_date_from: filters.dateFrom || null,
    p_date_to: filters.dateTo || null,
    p_limit: pageSize,
    p_offset: from,
  })
  if (matchError) throw matchError
  const ids = (matches ?? []) as unknown as { invoice_id: number; total_count: number }[]
  if (!ids.length) return { rows: [], total: 0 }
  const { data, error } = await client.from('invoices').select(invoiceFields).in('id', ids.map((row) => row.invoice_id)).order('issued_at', { ascending: false }).order('id', { ascending: false })
  if (error) throw error
  return { rows: (data ?? []) as unknown as InvoiceRecord[], total: Number(ids[0].total_count) }
}

export async function loadInvoiceOptions(client: AppSupabaseClient): Promise<{
  customers: InvoiceCustomerOption[]; offerings: InvoiceOfferingOption[]; employees: InvoiceEmployeeOption[]; appointments: InvoiceAppointmentOption[]
}> {
  const [customers, offerings, employees, appointments] = await Promise.all([
    client.from('customers').select('id, code, name, phone').eq('status', 'active').is('deleted_at', null).order('name').limit(1000),
    client.from('offerings').select('id, code, name, type, price, sessions_total').eq('status', 'active').is('deleted_at', null).order('name').limit(500),
    client.from('employees').select('id, name, job_title').eq('status', 'active').is('deleted_at', null).order('name').limit(500),
    client.from('appointments').select('id, code, customer_id, offering_id, appointment_date, start_time, customer:customers(name), offering:offerings(name), linked_invoice:invoices!invoices_appointment_id_fkey(id)').eq('status', 'completed_pending_payment').eq('payment_type', 'service').is('linked_invoice.id', null).order('appointment_date', { ascending: false }).limit(100),
  ])
  const error = customers.error ?? offerings.error ?? employees.error ?? appointments.error
  if (error) throw error
  return {
    customers: (customers.data ?? []) as InvoiceCustomerOption[],
    offerings: (offerings.data ?? []) as InvoiceOfferingOption[],
    employees: (employees.data ?? []) as InvoiceEmployeeOption[],
    appointments: (appointments.data ?? []) as unknown as InvoiceAppointmentOption[],
  }
}

export async function loadInvoiceStats(client: AppSupabaseClient): Promise<{ revenueToday: number; pending: number }> {
  const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
  const next = new Date(`${today}T00:00:00Z`)
  next.setUTCDate(next.getUTCDate() + 1)
  const tomorrow = next.toISOString().slice(0, 10)
  const [paid, pending] = await Promise.all([
    client.from('invoices').select('total_received').eq('status', 'paid').gte('paid_at', `${today}T00:00:00+07:00`).lt('paid_at', `${tomorrow}T00:00:00+07:00`),
    client.from('invoices').select('id', { count: 'exact', head: true }).eq('status', 'pending'),
  ])
  const error = paid.error ?? pending.error
  if (error) throw error
  return { revenueToday: (paid.data ?? []).reduce((sum, row) => sum + Number(row.total_received), 0), pending: pending.count ?? 0 }
}

export async function createInvoice(
  client: AppSupabaseClient,
  values: { customerId: number; offeringId: number; appointmentId: number | null; employeeId: number | null; discountAmount: number },
  scope = 'new',
): Promise<{ invoice: { id: number; code: string; type: string } }> {
  return await runIdempotentInvoiceRequest(client, 'create_invoice', scope, 'create_invoice_idempotent', (requestId) => ({
    p_request_id: requestId,
    p_customer_id: values.customerId,
    p_offering_id: values.offeringId,
    p_appointment_id: values.appointmentId,
    p_employee_id: values.employeeId,
    p_discount_amount: values.discountAmount,
  }))
}

export async function payInvoice(
  client: AppSupabaseClient,
  invoiceId: number,
  method: InvoiceMethod,
  reference: string,
): Promise<{ invoice: { id: number }; payment?: { id: number } }> {
  const payload = { invoiceId, method, reference: reference.trim() || null }
  return await runIdempotentInvoiceRequest(client, 'pay_invoice', String(invoiceId), 'pay_invoice_idempotent', (requestId) => ({
    p_request_id: requestId,
    p_invoice_id: invoiceId,
    p_method: method,
    p_reference: payload.reference,
  }))
}

export async function consumeCustomerPackage(
  client: AppSupabaseClient,
  customerPackageId: number,
  performedBy: number | null,
  note = 'Trừ buổi từ ca làm cuối ngày',
): Promise<void> {
  const { error } = await client.rpc('consume_customer_package_session', {
    p_customer_package_id: customerPackageId,
    p_performed_by: performedBy,
    p_note: note,
  })
  if (error) throw error
}

export async function voidInvoice(client: AppSupabaseClient, invoiceId: number, reason: string): Promise<void> {
  const payload = { invoiceId, reason: reason.trim() }
  await runIdempotentInvoiceRequest(client, 'void_invoice', String(invoiceId), 'void_invoice_idempotent', (requestId) => ({
    p_request_id: requestId,
    p_invoice_id: invoiceId,
    p_reason: payload.reason,
  }))
}

export async function refundInvoice(client: AppSupabaseClient, invoiceId: number, method: InvoiceMethod, reason: string, reference: string): Promise<void> {
  const payload = { invoiceId, method, reason: reason.trim(), reference: reference.trim() || null }
  await runIdempotentInvoiceRequest(client, 'refund_invoice', String(invoiceId), 'refund_invoice_idempotent', (requestId) => ({
    p_request_id: requestId,
    p_invoice_id: invoiceId,
    p_method: method,
    p_reason: payload.reason,
    p_reference: payload.reference,
  }))
}

export async function exportInvoices(client: AppSupabaseClient, filters: Omit<InvoiceFilters, 'page'>): Promise<{
  code: string; issued_at: string | null; type: string; status: string; payment_method: string | null; total_received: number; customer: { name: string } | null
}[]> {
  const pageSize = 500
  const rows: { code: string; issued_at: string | null; type: string; status: string; payment_method: string | null; total_received: number; customer: { name: string } | null }[] = []
  for (let offset = 0; ; offset += pageSize) {
    const { data: matches, error: matchError } = await client.rpc('search_invoice_ids', {
      p_search: filters.search.trim(), p_status: filters.status, p_method: filters.method,
      p_date_from: filters.dateFrom || null, p_date_to: filters.dateTo || null, p_limit: pageSize, p_offset: offset,
    })
    if (matchError) throw matchError
    const ids = ((matches ?? []) as unknown as { invoice_id: number }[]).map((row) => row.invoice_id)
    if (!ids.length) return rows
    const { data, error } = await client.from('invoices').select('code, issued_at, type, status, payment_method, total_received, customer:customers(name)').in('id', ids)
    if (error) throw error
    rows.push(...((data ?? []) as unknown as typeof rows))
    if (ids.length < pageSize) return rows
  }
}

export function downloadInvoicesCsv(rows: Awaited<ReturnType<typeof exportInvoices>>): void {
  const lines = [
    ['Mã hóa đơn', 'Ngày lập', 'Khách hàng', 'Loại', 'Trạng thái', 'Phương thức', 'Tiền thực thu'],
    ...rows.map((row) => [row.code, row.issued_at ? new Date(row.issued_at).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' }) : '', row.customer?.name ?? '', row.type, row.status, row.payment_method ?? '', String(row.total_received)]),
  ]
  const csv = '\ufeff' + lines.map((line) => line.map((cell) => `"${cell.replaceAll('"', '""')}"`).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = `hoa-don-${new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })}.csv`
  anchor.click()
  URL.revokeObjectURL(url)
}

export function invoiceStatusLabel(status: string): string {
  return ({ draft: 'Bản nháp', pending: 'Chờ thanh toán', paid: 'Đã thanh toán', void: 'Đã hủy', refunded: 'Đã hoàn tiền' } as Record<string, string>)[status] ?? status
}
export function invoiceMethodLabel(method: string | null): string {
  return ({ cash: 'Tiền mặt', vietqr: 'VietQR', pos: 'POS' } as Record<string, string>)[method ?? ''] ?? 'Chưa thu'
}
