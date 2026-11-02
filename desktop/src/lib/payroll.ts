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

export function getCurrentMonthRange(): { start: string; end: string } {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth() // 0-based
  const startDate = new Date(year, month, 1)
  const endDate = new Date(year, month + 1, 0)

  const format = (d: Date) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  return {
    start: format(startDate),
    end: format(endDate),
  }
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

  if (!employeeId || isNaN(employeeId)) {
    throw new Error('Vui lòng chọn nhân viên.')
  }
  if (!amount || amount <= 0 || isNaN(amount)) {
    throw new Error('Số tiền điều chỉnh phải lớn hơn 0.')
  }
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
