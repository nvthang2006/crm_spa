import type { AppSupabaseClient } from './supabase'
import type { Database } from './database.types'

export type PayrollPeriod = Database['public']['Tables']['payroll_periods']['Row']
export type PayrollAdjustment = Database['public']['Tables']['payroll_adjustments']['Row']
export type PayrollAdjustmentWithEmployee = PayrollAdjustment & {
  employee: { id: number; code: string; name: string } | null
}

export type PayrollLineWithEmployee = Database['public']['Tables']['payroll_lines']['Row'] & {
  employee: { id: number; code: string; name: string; job_title: string } | null
}

export type PayrollAdjustmentDraft = {
  employee_id: string
  type: 'bonus' | 'deduction'
  amount: string
  reason: string
}

export function getCurrentMonthRange(now = new Date()): { start: string; end: string } {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(now)
  const year = Number(parts.find((part) => part.type === 'year')?.value)
  const month = Number(parts.find((part) => part.type === 'month')?.value)
  const prefix = `${year}-${String(month).padStart(2, '0')}`
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate()
  return { start: `${prefix}-01`, end: `${prefix}-${lastDay}` }
}

export function validatePayrollRange(start: string, end: string): void {
  const validDate = (value: string): boolean => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
    const parsed = new Date(`${value}T00:00:00Z`)
    return Number.isFinite(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value
  }
  if (!validDate(start)) throw new Error('Vui lòng nhập ngày bắt đầu hợp lệ theo dd/mm/yyyy.')
  if (!validDate(end)) throw new Error('Vui lòng nhập ngày kết thúc hợp lệ theo dd/mm/yyyy.')
  if (end < start) throw new Error('Ngày kết thúc kỳ lương phải bằng hoặc sau ngày bắt đầu.')
}

export function getPayrollErrorMessage(error: unknown, fallback = 'Chưa xử lý được bảng lương. Vui lòng thử lại.'): string {
  const details = error && typeof error === 'object' ? error as { code?: unknown; message?: unknown } : {}
  const code = typeof details.code === 'string' ? details.code : ''
  const message = typeof details.message === 'string' ? details.message : ''
  if (code === 'PGRST202' || /Could not find the function|schema cache/i.test(message)) {
    return 'Hệ thống chưa cập nhật đầy đủ chức năng bảng lương. Vui lòng cập nhật cơ sở dữ liệu rồi thử lại.'
  }
  if (code === '42501' || code === 'PGRST301') return 'Tài khoản không có quyền quản lý bảng lương hoặc phiên đăng nhập đã hết hạn.'
  if (/fetch|network|timeout|Failed to fetch/i.test(message)) return 'Không kết nối được máy chủ. Kiểm tra kết nối rồi thử lại.'
  if (/[À-ỹ]/.test(message)) return message
  return fallback
}

export async function loadPayrollPeriods(client: AppSupabaseClient): Promise<PayrollPeriod[]> {
  const { data, error } = await client
    .from('payroll_periods')
    .select('*')
    .order('period_start', { ascending: false })
    .limit(50)

  if (error) throw error
  return data ?? []
}

export async function loadPayrollPeriodById(
  client: AppSupabaseClient,
  periodId: number
): Promise<{
  period: PayrollPeriod
  lines: PayrollLineWithEmployee[]
  adjustments: PayrollAdjustmentWithEmployee[]
} | null> {
  const { data: period, error: periodErr } = await client
    .from('payroll_periods')
    .select('*')
    .eq('id', periodId)
    .single()

  if (periodErr) throw periodErr
  if (!period) return null

  const [{ data: lines, error: linesErr }, { data: adjustments, error: adjErr }] =
    await Promise.all([
      client
        .from('payroll_lines')
        .select('*, employee:employees(id, code, name, job_title)')
        .eq('payroll_period_id', periodId)
        .order('id', { ascending: true }),
      client
        .from('payroll_adjustments')
        .select('*, employee:employees(id, code, name)')
        .eq('payroll_period_id', periodId)
        .order('created_at', { ascending: false }),
    ])

  if (linesErr) throw linesErr
  if (adjErr) throw adjErr

  return {
    period,
    lines: (lines ?? []) as unknown as PayrollLineWithEmployee[],
    adjustments: (adjustments ?? []) as unknown as PayrollAdjustmentWithEmployee[],
  }
}

export async function generatePayroll(
  client: AppSupabaseClient,
  periodStart: string,
  periodEnd: string
): Promise<PayrollPeriod> {
  validatePayrollRange(periodStart, periodEnd)
  const { data, error } = await client.rpc('generate_payroll', {
    p_period_start: periodStart,
    p_period_end: periodEnd,
  })

  if (error) throw error
  return data as unknown as PayrollPeriod
}

export async function lockPayroll(
  client: AppSupabaseClient,
  periodId: number
): Promise<PayrollPeriod> {
  const { data, error } = await client.rpc('lock_payroll', {
    p_period_id: periodId,
  })

  if (error) throw error
  return data as unknown as PayrollPeriod
}

export async function createPayrollAdjustment(
  client: AppSupabaseClient,
  periodId: number,
  draft: PayrollAdjustmentDraft
): Promise<PayrollAdjustment> {
  const employeeId = Number(draft.employee_id)
  const amount = Number(draft.amount)

  if (!Number.isSafeInteger(employeeId) || employeeId <= 0) {
    throw new Error('Vui lòng chọn nhân viên.')
  }
  if (!Number.isSafeInteger(amount) || amount <= 0) {
    throw new Error('Số tiền điều chỉnh phải là số nguyên lớn hơn 0.')
  }
  if (!['bonus', 'deduction'].includes(draft.type)) throw new Error('Vui lòng chọn loại điều chỉnh hợp lệ.')
  if (!draft.reason.trim()) {
    throw new Error('Vui lòng nhập lý do điều chỉnh.')
  }

  const { data, error } = await client.rpc('create_payroll_adjustment', {
    p_period_id: periodId,
    p_employee_id: employeeId,
    p_type: draft.type,
    p_amount: amount,
    p_reason: draft.reason.trim(),
  })

  if (error) throw error
  return data as unknown as PayrollAdjustment
}

export async function reviewPayrollAdjustment(
  client: AppSupabaseClient,
  adjustmentId: number,
  approve: boolean,
  reason?: string
): Promise<PayrollAdjustment> {
  const { data, error } = await client.rpc('review_payroll_adjustment', {
    p_adjustment_id: adjustmentId,
    p_approve: approve,
    p_reason: reason || null,
  })

  if (error) throw error
  return data as unknown as PayrollAdjustment
}

export function exportPayrollCsv(
  period: PayrollPeriod,
  lines: PayrollLineWithEmployee[]
): void {
  const headers = [
    'Mã NV',
    'Tên nhân viên',
    'Chức vụ',
    'Lương cứng',
    'Ngày công',
    'Hoa hồng',
    'Thưởng / Tips',
    'Khấu trừ',
    'Thực lĩnh',
    'Trạng thái',
  ]

  const rows = lines.map((l) => [
    `"${l.employee?.code || ''}"`,
    `"${l.employee?.name || ''}"`,
    `"${l.employee?.job_title || ''}"`,
    l.base_salary,
    l.work_days,
    l.commission_amount,
    l.tips,
    l.deductions,
    l.net_pay,
    l.status === 'locked' ? '"Đã khóa"' : '"Bản nháp"',
  ])

  const csvContent =
    '﻿' +
    [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n')

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `Bang_Luong_${period.code}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
