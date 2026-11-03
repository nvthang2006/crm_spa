import type { AppSupabaseClient } from './supabase'
import type { InvoiceMethod } from './invoices'

export type SessionClassification = 'retail' | 'package'
export type CommissionCalculation = 'percentage' | 'fixed'
export type ShiftEntryRow = {
  tempId: string
  classification: SessionClassification
  customerId: number | ''
  offeringId: number | ''
  technicianId: number | ''
  collected: number
  paymentMethod: InvoiceMethod
  notes: string
  commissionType: CommissionCalculation
  commissionValue: string
}

export type DailyCommissionRule = {
  id: number
  offering_id: number
  beneficiary_type: string
  beneficiary_employee_id: number | null
  basis: string
  calculation_type: string
  value: number
  starts_on: string
  ends_on: string | null
  is_active: boolean
}

type OfferingForCommission = { id: number; price: number | null; sessions_total: number }

export function hasUnsavedShiftEntries(rows: ShiftEntryRow[]): boolean {
  return rows.length > 1 || rows.some((row) => row.classification !== 'retail'
    || row.customerId !== '' || row.offeringId !== '' || row.technicianId !== ''
    || row.collected !== 0 || row.paymentMethod !== 'cash' || row.notes !== ''
    || row.commissionType !== 'percentage' || row.commissionValue !== '')
}

export function validateShiftEntry(row: ShiftEntryRow): string | null {
  if (!Number.isSafeInteger(row.customerId) || Number(row.customerId) <= 0) return 'Vui lòng chọn khách hàng.'
  if (!Number.isSafeInteger(row.offeringId) || Number(row.offeringId) <= 0) return 'Vui lòng chọn dịch vụ hoặc gói.'
  if (!Number.isSafeInteger(row.technicianId) || Number(row.technicianId) <= 0) return 'Vui lòng chọn kỹ thuật viên thực hiện.'
  if (!Number.isSafeInteger(row.collected) || row.collected < 0) return 'Tiền thực thu phải là số đồng nguyên từ 0 trở lên.'
  if (!['retail', 'package'].includes(row.classification)) return 'Phân loại ca làm không hợp lệ.'
  if (!['cash', 'vietqr', 'pos'].includes(row.paymentMethod)) return 'Phương thức thanh toán không hợp lệ.'
  if (row.notes.length > 500) return 'Ghi chú không được vượt quá 500 ký tự.'
  return validateCommissionOverride(row.commissionType, row.commissionValue)
}

export function validateCommissionOverride(type: CommissionCalculation, input: string): string | null {
  if (!['percentage', 'fixed'].includes(type)) return 'Loại hoa hồng không hợp lệ.'
  if (input.trim() === '') return null
  const value = Number(input)
  if (!Number.isFinite(value) || value < 0) return 'Mức hoa hồng phải là số từ 0 trở lên.'
  if (type === 'percentage' && (value > 100 || Math.abs(value * 100 - Math.round(value * 100)) > 0.000001)) {
    return 'Tỷ lệ hoa hồng phải từ 0 đến 100%, tối đa 2 chữ số thập phân.'
  }
  if (type === 'fixed' && !Number.isSafeInteger(value)) return 'Hoa hồng theo số tiền phải là số đồng nguyên.'
  if (type === 'fixed' && value > 9999999999) return 'Số tiền hoa hồng không được vượt quá 9.999.999.999 đồng.'
  return null
}

export function commissionBase(row: Pick<ShiftEntryRow, 'classification' | 'collected'>, offering: OfferingForCommission): number {
  return row.classification === 'package' && row.collected === 0
    ? Number(offering.price ?? 0) / Math.max(1, offering.sessions_total)
    : row.collected
}

export function previewShiftCommission(row: ShiftEntryRow, offering: OfferingForCommission | undefined, rules: DailyCommissionRule[], workDate: string): number {
  if (!offering || !row.technicianId || validateCommissionOverride(row.commissionType, row.commissionValue)) return 0
  const base = commissionBase(row, offering)
  const activeRules = rules.filter((rule) => rule.offering_id === offering.id && rule.is_active
    && ['payment', 'completion'].includes(rule.basis)
    && rule.starts_on <= workDate && (!rule.ends_on || rule.ends_on >= workDate))
  const personal = activeRules.filter((rule) => rule.beneficiary_type === 'performer_employee' && rule.beneficiary_employee_id === row.technicianId)
    .sort((a, b) => b.starts_on.localeCompare(a.starts_on) || b.id - a.id)[0]
  const performerRules = personal ? [personal] : activeRules.filter((rule) => rule.beneficiary_type === 'primary_technician')
  const specificRules = activeRules.filter((rule) => rule.beneficiary_type === 'specific_employee' && rule.beneficiary_employee_id !== null)
  const amount = (type: string, value: number) => type === 'fixed' ? Math.round(value) : Math.round(base * value / 100)
  const performerAmount = row.commissionValue.trim() !== ''
    ? amount(row.commissionType, Number(row.commissionValue))
    : performerRules.reduce((sum, rule) => sum + amount(rule.calculation_type, Number(rule.value)), 0)
  return Math.max(0, performerAmount) + specificRules.reduce((sum, rule) => sum + amount(rule.calculation_type, Number(rule.value)), 0)
}

export function recordedShiftClassification(invoice: { notes: string | null; total_received: number; type: string }): SessionClassification {
  if (invoice.notes?.startsWith('[Ca gói]')) return 'package'
  if (invoice.notes?.startsWith('[Ca lẻ]')) return 'retail'
  return Number(invoice.total_received) === 0 || invoice.type === 'package' ? 'package' : 'retail'
}

export async function recordDailyShift(client: AppSupabaseClient, row: ShiftEntryRow, workDate: string, customerPackageId: number | null): Promise<{ invoice: { id: number; code: string }; commission_amount: number }> {
  const validationError = validateShiftEntry(row)
  if (validationError) throw new Error(validationError)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(workDate)) throw new Error('Vui lòng chọn ngày làm việc hợp lệ.')
  const hasOverride = row.commissionValue.trim() !== ''
  const { data, error } = await client.rpc('record_daily_shift', {
    p_request_id: row.tempId,
    p_customer_id: Number(row.customerId),
    p_offering_id: Number(row.offeringId),
    p_employee_id: Number(row.technicianId),
    p_classification: row.classification,
    p_collected: row.collected,
    p_work_date: workDate,
    p_payment_method: row.paymentMethod,
    p_customer_package_id: row.classification === 'package' ? customerPackageId : null,
    p_commission_type: hasOverride ? row.commissionType : null,
    p_commission_value: hasOverride ? Number(row.commissionValue) : null,
    p_notes: row.notes.trim() || 'Nhập ca làm cuối ngày',
  })
  if (error) {
    if (error.code === 'PGRST202') throw new Error('Chức năng ghi nhận ca làm chưa được cập nhật trên máy chủ. Vui lòng thử lại sau khi cập nhật hệ thống.')
    if (error.code === '22023') throw new Error('Ca này đã được gửi với nội dung khác. Hãy kiểm tra nhật ký trong ngày trước khi nhập một ca mới.')
    throw error
  }
  return data as unknown as { invoice: { id: number; code: string }; commission_amount: number }
}
