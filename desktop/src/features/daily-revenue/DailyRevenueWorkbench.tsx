import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import { useState, useMemo, useEffect, useRef } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  CalendarDays,
  Plus,
  Trash2,
  Save,
  CheckCircle2,
  Sparkles,
  WalletCards,
  UserPlus,
  Receipt,
  User,
  SlidersHorizontal,
  ChevronDown,
  RotateCcw,
} from 'lucide-react'
import { toast } from 'sonner'
import type { AppSupabaseClient } from '../../lib/supabase'
import { commissionBase, previewShiftCommission, recordDailyShift, recordedShiftClassification, validateCommissionOverride, validateShiftEntry, type DailyCommissionRule, type ShiftEntryRow } from '../../lib/dailyRevenue'
import { saveCustomer, type CustomerDraft } from '../../lib/customers'
import { formatVnd } from '../../lib/offerings'
import './daily-revenue.css'

export type { SessionClassification, ShiftEntryRow } from '../../lib/dailyRevenue'

type Props = {
  client: AppSupabaseClient
  onOpenInvoices?: () => void
  onEditorStateChange?: (open: boolean) => void
}

function createBlankRow(): ShiftEntryRow {
  return {
    tempId: window.crypto.randomUUID(),
    classification: 'retail',
    customerId: '',
    offeringId: '',
    technicianId: '',
    collected: 0,
    paymentMethod: 'cash',
    commissionType: 'percentage',
    commissionValue: '',
    notes: '',
  }
}

function getTodayDateString(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
}

export default function DailyRevenueWorkbench({ client, onEditorStateChange }: Props) {
  const queryClient = useQueryClient()
  const [selectedDate, setSelectedDate] = useState(getTodayDateString)
  const [activePanel, setActivePanel] = useState<'entry' | 'recorded'>('entry')
  const [recordedPage, setRecordedPage] = useState(0)
  const [rows, setRows] = useState<ShiftEntryRow[]>(() => [createBlankRow()])
  const [saving, setSaving] = useState(false)
  const [newCustomerModal, setNewCustomerModal] = useState<{ open: boolean; targetRowIndex: number }>({ open: false, targetRowIndex: 0 })
  const [newCustomerName, setNewCustomerName] = useState('')
  const [newCustomerPhone, setNewCustomerPhone] = useState('')
  const [creatingCustomer, setCreatingCustomer] = useState(false)
  const [customerError, setCustomerError] = useState('')
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({})
  const [expandedCommissions, setExpandedCommissions] = useState<Record<string, boolean>>({})
  const saveInProgress = useRef(false)
  const pendingPackages = useRef(new Map<string, { selection: string; packageId: number | null }>())
  const recordedTableScroll = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (recordedTableScroll.current) recordedTableScroll.current.scrollTop = 0
  }, [recordedPage, selectedDate])


  // 1. Load active customers
  const customersQuery = useQuery({
    queryKey: ['active-customers-options'],
    queryFn: async () => {
      const { data, error } = await client
        .from('customers')
        .select('id, code, name, phone')
        .eq('status', 'active')
        .is('deleted_at', null)
        .order('name')
      if (error) throw error
      return data ?? []
    },
  })

  // 2. Load active offerings (services and packages)
  const offeringsQuery = useQuery({
    queryKey: ['active-offerings-options'],
    queryFn: async () => {
      const { data, error } = await client
        .from('offerings')
        .select('id, code, name, type, price, sessions_total')
        .eq('status', 'active')
        .is('deleted_at', null)
        .order('name')
      if (error) throw error
      return data ?? []
    },
  })

  // 3. Load active technicians (employees)
  const employeesQuery = useQuery({
    queryKey: ['active-employees-options'],
    queryFn: async () => {
      const { data, error } = await client
        .from('employees')
        .select('id, code, name, job_title')
        .eq('status', 'active')
        .is('deleted_at', null)
        .order('name')
      if (error) throw error
      return data ?? []
    },
  })

  // 4. Load commission rules for live previews
  const commissionRulesQuery = useQuery({
    queryKey: ['active-commission-rules'],
    queryFn: async () => {
      const { data, error } = await client
        .from('commission_rules')
        .select('id, offering_id, beneficiary_type, beneficiary_employee_id, basis, calculation_type, value, starts_on, ends_on, is_active')
        .eq('is_active', true)
      if (error) throw error
      return (data ?? []) as DailyCommissionRule[]
    },
  })

  // 5. Load active customer packages (to link when classification is 'package')
  const packagesQuery = useQuery({
    queryKey: ['active-customer-packages'],
    queryFn: async () => {
      const { data, error } = await client
        .from('customer_packages')
        .select('id, customer_id, offering_id, code, sessions_total, sessions_used, starts_on, expires_on, status')
        .eq('status', 'active')
      if (error) throw error
      return data ?? []
    },
  })

  // 6. Load paid invoices on selectedDate (Recorded Shift Entries)
  const shiftEntriesQuery = useQuery({
    queryKey: ['daily-shift-entries', selectedDate],
    enabled: Boolean(selectedDate),
    queryFn: async () => {
      const nextDate = new Date(`${selectedDate}T00:00:00Z`)
      nextDate.setUTCDate(nextDate.getUTCDate() + 1)
      const nextDateStr = nextDate.toISOString().slice(0, 10)

      const { data, error } = await client
        .from('invoices')
        .select(`
          id, code, customer_id, type, total_received, paid_at, notes,
          customer:customers(name, phone),
          items:invoice_items(id, offering_id, employee_id, quantity, offering:offerings(name), employee:employees!invoice_items_employee_id_fkey(name)),
          commission_entries(id, employee_id, amount, status)
        `)
        .eq('status', 'paid')
        .gte('paid_at', `${selectedDate}T00:00:00+07:00`)
        .lt('paid_at', `${nextDateStr}T00:00:00+07:00`)
        .order('paid_at', { ascending: false })

      if (error) throw error
      return data ?? []
    },
  })

  const offeringsMap = useMemo(() => {
    const map = new Map<number, NonNullable<typeof offeringsQuery.data>[number]>()
    for (const off of offeringsQuery.data ?? []) {
      map.set(off.id, off)
    }
    return map
  }, [offeringsQuery.data])

  function getRowCommission(row: ShiftEntryRow): number {
    return previewShiftCommission(row, offeringsMap.get(Number(row.offeringId)), commissionRulesQuery.data ?? [], selectedDate)
  }

  function findCustomerPackage(row: ShiftEntryRow) {
    if (row.classification !== 'package' || !row.customerId || !row.offeringId) return null
    return (packagesQuery.data ?? []).find((pkg) => pkg.customer_id === row.customerId
      && pkg.offering_id === row.offeringId && pkg.sessions_used < pkg.sessions_total
      && (!pkg.starts_on || pkg.starts_on <= selectedDate)
      && (!pkg.expires_on || pkg.expires_on >= selectedDate)) ?? null
  }

  // Thay đổi khách hoặc dịch vụ luôn xác định lại thẻ gói từ dữ liệu hiện tại.
  function updateRow(index: number, patch: Partial<ShiftEntryRow>) {
    setRows((previous) => previous.map((row, rowIndex) => {
      if (rowIndex !== index) return row
      const current = { ...row, ...patch }
      if (patch.classification === 'package') current.collected = 0
      if (patch.offeringId !== undefined || patch.classification === 'retail') {
        current.collected = current.classification === 'package' ? 0 : offeringsMap.get(Number(current.offeringId))?.price ?? 0
      }
      return current
    }))
    const rowId = rows[index]?.tempId
    if (rowId) setRowErrors((previous) => ({ ...previous, [rowId]: '' }))
  }

  function toggleCommissionExpand(tempId: string) {
    setExpandedCommissions((prev) => ({ ...prev, [tempId]: !prev[tempId] }))
  }

  function addRow() {
    const nextRow = createBlankRow()
    setRows((prev) => [...prev, nextRow])
    window.requestAnimationFrame(() => {
      document.getElementById(`customer-${nextRow.tempId}`)?.focus({ preventScroll: true })
      document.getElementById(`customer-${nextRow.tempId}`)?.scrollIntoView({ block: 'nearest' })
    })
  }

  function removeRow(index: number) {
    if (rows.length <= 1) {
      pendingPackages.current.delete(rows[index]?.tempId)
      setRows([createBlankRow()])
      return
    }
    pendingPackages.current.delete(rows[index]?.tempId)
    setRows((prev) => prev.filter((_, i) => i !== index))
  }

  // Quick customer creation
  function closeNewCustomerModal() {
    if (creatingCustomer) return
    setNewCustomerModal({ open: false, targetRowIndex: 0 })
    if (onEditorStateChange) onEditorStateChange(false)
  }

  async function handleCreateCustomer() {
    if (!newCustomerName.trim()) {
      toast.error('Vui lòng nhập họ tên khách hàng.')
      return
    }
    setCustomerError('')
    setCreatingCustomer(true)
    try {
      const draft: CustomerDraft = {
        code: '',
        name: newCustomerName.trim(),
        phone: newCustomerPhone.trim(),
        email: null,
        membership_tier: 'Thành viên',
        birthday: null,
        address: null,
        notes: 'Tạo nhanh từ màn hình nhập cuối ngày',
      }
      const created = await saveCustomer(client, draft, null)
      toast.success(`Đã thêm khách hàng ${created.name}!`)
      await queryClient.invalidateQueries({ queryKey: ['active-customers-options'] })

      // Select this customer in the target row
      updateRow(newCustomerModal.targetRowIndex, { customerId: created.id })

      setNewCustomerModal({ open: false, targetRowIndex: 0 })
      setNewCustomerName('')
      setNewCustomerPhone('')
      if (onEditorStateChange) onEditorStateChange(false)
    } catch (err: any) {
      setCustomerError(err instanceof Error ? err.message : 'Không thể tạo khách hàng.')
      toast.error(err.message || 'Không thể tạo khách hàng.')
    } finally {
      setCreatingCustomer(false)
    }
  }

  async function refreshRecordedShifts() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['daily-shift-entries'] }),
      queryClient.invalidateQueries({ queryKey: ['overview'] }),
      queryClient.invalidateQueries({ queryKey: ['invoices'] }),
      queryClient.invalidateQueries({ queryKey: ['active-customer-packages'] }),
      queryClient.invalidateQueries({ queryKey: ['payroll-period-detail'] }),
    ])
  }

  async function saveAll() {
    if (saveInProgress.current) return
    if (!selectedDate) {
      toast.error('Vui lòng chọn ngày làm việc hợp lệ.')
      return
    }
    const errors: Record<string, string> = {}
    rows.forEach((row) => {
      const error = validateShiftEntry(row)
      if (error) errors[row.tempId] = error
    })
    setRowErrors(errors)
    const firstInvalid = rows.findIndex((row) => errors[row.tempId])
    if (firstInvalid >= 0) {
      toast.error(`Ca số ${firstInvalid + 1}: ${errors[rows[firstInvalid].tempId]}`)
      document.getElementById(`shift-error-${rows[firstInvalid].tempId}`)?.scrollIntoView({ block: 'nearest' })
      return
    }
    if (commissionRulesQuery.isPending || commissionRulesQuery.isError || packagesQuery.isPending || packagesQuery.isError) {
      toast.error('Chưa tải đủ quy tắc hoa hồng hoặc thẻ gói. Vui lòng tải lại dữ liệu trước khi lưu.')
      return
    }

    saveInProgress.current = true
    setSaving(true)
    let savedCount = 0
    const remaining = [...rows]
    try {
      for (const row of rows) {
        const selection = `${row.classification}:${row.customerId}:${row.offeringId}`
        const pendingPackage = pendingPackages.current.get(row.tempId)
        const packageId = pendingPackage?.selection === selection ? pendingPackage.packageId : findCustomerPackage(row)?.id ?? null
        pendingPackages.current.set(row.tempId, { selection, packageId })
        await recordDailyShift(client, row, selectedDate, packageId)
        pendingPackages.current.delete(row.tempId)
        savedCount++
        remaining.shift()
        setRows([...remaining])
      }
      toast.success(`Đã lưu thành công ${savedCount} ca làm!`)
      setRows([createBlankRow()])
      setRowErrors({})
    } catch (error: unknown) {
      const message = error && typeof error === 'object' && 'message' in error ? String(error.message) : 'Không thể lưu ca làm. Vui lòng thử lại.'
      toast.error(savedCount > 0 ? `Đã lưu ${savedCount} ca. ${message}` : message)
    } finally {
      await refreshRecordedShifts()
      setSaving(false)
      saveInProgress.current = false
    }
  }

  // Shift summary calculations for the selected day
  const recordedEntries = shiftEntriesQuery.data ?? []
  const recordedRevenue = recordedEntries.reduce((sum, inv) => sum + Number(inv.total_received), 0)
  const recordedCommission = recordedEntries.reduce((sum, inv) => {
    const entryComms = (inv.commission_entries ?? []).filter((c: any) => c.status !== 'reversed')
    return sum + entryComms.reduce((s: number, c: any) => s + Number(c.amount), 0)
  }, 0)
  const recordedPackageSessions = recordedEntries.filter((inv) => recordedShiftClassification(inv) === 'package').length
  const recordedRetailSessions = recordedEntries.length - recordedPackageSessions
  const recordedPageSize = 10
  const recordedPageCount = Math.max(1, Math.ceil(recordedEntries.length / recordedPageSize))
  const visibleRecordedPage = Math.min(recordedPage, recordedPageCount - 1)
  const visibleRecordedEntries = recordedEntries.slice(visibleRecordedPage * recordedPageSize, (visibleRecordedPage + 1) * recordedPageSize)

  // Draft calculations
  const draftRevenue = rows.reduce((sum, r) => sum + Number(r.collected || 0), 0)
  const draftCommission = rows.reduce((sum, r) => sum + getRowCommission(r), 0)

  return (
    <div className="daily-revenue-page">
      {/* Header */}
      <div className="daily-revenue-heading">
        <div>
          <span className="eyebrow">NHẬP LIỆU NHANH TRONG CA</span>
          <h1>Nhập ca làm cuối ngày</h1>
          <p>Ghi nhận nhanh danh sách ca phục vụ của KTV, tiền thực thu và tự động tính hoa hồng.</p>
        </div>
        <div className="daily-revenue-actions">
          <DateInput label="Chọn ngày làm việc" value={selectedDate} required disabled={saving} onChange={(value) => { setSelectedDate(value); setRecordedPage(0) }} />
        </div>
      </div>

      {/* KPI Summary Cards */}
      <div className="daily-revenue-kpi-grid">
        <div className="daily-revenue-kpi-card">
          <div className="daily-revenue-kpi-icon revenue">
            <WalletCards size={22} />
          </div>
          <div className="daily-revenue-kpi-content">
            <small>Doanh thu ngày {selectedDate.split('-').reverse().join('/')}</small>
            <b>{formatVnd(recordedRevenue)} ₫</b>
            <span>{recordedEntries.length} ca đã thanh toán</span>
          </div>
        </div>

        <div className="daily-revenue-kpi-card">
          <div className="daily-revenue-kpi-icon commission">
            <Sparkles size={22} />
          </div>
          <div className="daily-revenue-kpi-content">
            <small>Hoa hồng KTV ngày {selectedDate.split('-').reverse().join('/')}</small>
            <b>{formatVnd(recordedCommission)} ₫</b>
            <span>Tự động tính theo ca hoàn thành</span>
          </div>
        </div>

        <div className="daily-revenue-kpi-card">
          <div className="daily-revenue-kpi-icon sessions">
            <CalendarDays size={22} />
          </div>
          <div className="daily-revenue-kpi-content">
            <small>Tổng số ca phục vụ</small>
            <b>{recordedEntries.length} ca</b>
            <span>
              {recordedRetailSessions} khách lẻ · {recordedPackageSessions} ca làm gói
            </span>
          </div>
        </div>
      </div>

      <div className="daily-revenue-view-switch" role="group" aria-label="Nội dung ca làm">
        <button type="button" aria-pressed={activePanel === 'entry'} onClick={() => setActivePanel('entry')}>
          <Plus size={16} aria-hidden="true" /> Nhập ca làm <span>{rows.length}</span>
        </button>
        <button type="button" aria-pressed={activePanel === 'recorded'} onClick={() => setActivePanel('recorded')}>
          <Receipt size={16} aria-hidden="true" /> Nhật ký trong ngày <span>{recordedEntries.length}</span>
        </button>
      </div>

      {(commissionRulesQuery.isError || packagesQuery.isError) && <div className="daily-revenue-load-error" role="alert">
        <span>Chưa tải đủ quy tắc hoa hồng hoặc thẻ gói. Các ca đang nhập được giữ nguyên.</span>
        <button type="button" className="secondary-button" disabled={commissionRulesQuery.isFetching || packagesQuery.isFetching}
          onClick={() => { void commissionRulesQuery.refetch(); void packagesQuery.refetch() }}>Tải lại dữ liệu</button>
      </div>}

      {/* Quick Entry Workbench */}
      <section className="daily-revenue-card" hidden={activePanel !== 'entry'}>
        <div className="daily-revenue-card-header">
          <div>
            <h2>Danh sách ca phục vụ cần ghi nhận</h2>
            <p>Chọn phân loại khách, dịch vụ và kỹ thuật viên thực hiện.</p>
          </div>
          <button className="secondary-button" onClick={addRow} disabled={saving}>
            <Plus size={16} /> Thêm ca làm
          </button>
        </div>

        <div className="daily-revenue-rows">
          {rows.map((row, index) => {
            const previewCommission = getRowCommission(row)
            const matchedPackage = findCustomerPackage(row)
            const offering = offeringsMap.get(Number(row.offeringId))
            const base = offering ? commissionBase(row, offering) : 0
            const commissionError = validateCommissionOverride(row.commissionType, row.commissionValue)
            const hasCustomCommission = row.commissionValue.trim() !== ''
            const isCommissionOpen = Boolean(expandedCommissions[row.tempId] || hasCustomCommission)

            return (
              <div key={row.tempId} className="daily-revenue-row-card">
                {/* Header: Shift Number + Segmented Classification + Live Commission Preview + Delete */}
                <div className="daily-revenue-row-top">
                  <div className="daily-revenue-row-title-wrap">
                    <span className="daily-revenue-row-index">Ca #{index + 1}</span>
                    <div className="daily-revenue-seg-control" role="group" aria-label={`Phân loại ca số ${index + 1}`}>
                      <button
                        type="button"
                        className={`daily-revenue-seg-btn ${row.classification === 'retail' ? 'active' : ''}`}
                        onClick={() => updateRow(index, { classification: 'retail' })}
                        disabled={saving}
                      >
                        Khách lẻ
                      </button>
                      <button
                        type="button"
                        className={`daily-revenue-seg-btn ${row.classification === 'package' ? 'active package' : ''}`}
                        onClick={() => updateRow(index, { classification: 'package' })}
                        disabled={saving}
                      >
                        Khách làm gói
                      </button>
                    </div>
                  </div>

                  <div className="daily-revenue-row-top-actions">
                    <div
                      className="daily-revenue-preview-commission"
                      aria-live="polite"
                      title="Hoa hồng dự kiến KTV nhận được cho ca này"
                    >
                      <Sparkles size={13} />
                      <span>
                        Hoa hồng: <strong>{commissionRulesQuery.isPending ? '…' : commissionRulesQuery.isError ? 'Lỗi' : commissionError ? 'Chưa hợp lệ' : `${formatVnd(previewCommission)} ₫`}</strong>
                      </span>
                    </div>

                    {rows.length > 1 && (
                      <button
                        type="button"
                        className="daily-revenue-row-delete-btn"
                        onClick={() => removeRow(index)}
                        title="Xóa ca này"
                        aria-label={`Xóa ca số ${index + 1}`}
                        disabled={saving}
                      >
                        <Trash2 size={15} />
                      </button>
                    )}
                  </div>
                </div>

                {/* 4 Essential Fields: Khách hàng, Dịch vụ / Gói, KTV thực hiện, Tiền thực thu */}
                <div className="daily-revenue-grid">
                  {/* Customer */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`customer-${row.tempId}`}>Khách hàng *</label>
                    <div className="daily-revenue-customer-select-wrap">
                      <select
                        id={`customer-${row.tempId}`}
                        value={row.customerId}
                        onChange={(e) => updateRow(index, { customerId: e.target.value ? Number(e.target.value) : '' })}
                        disabled={saving}
                      >
                        <option value="">-- Chọn khách hàng --</option>
                        {(customersQuery.data ?? []).map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name} · {c.phone ?? 'Chưa có SĐT'}
                          </option>
                        ))}
                      </select>
                      <button
                        type="button"
                        className="daily-revenue-customer-add-btn"
                        title="Thêm nhanh khách hàng mới"
                        aria-label={`Thêm khách hàng cho ca số ${index + 1}`}
                        onClick={() => {
                          setCustomerError('')
                          setNewCustomerModal({ open: true, targetRowIndex: index })
                          if (onEditorStateChange) onEditorStateChange(true)
                        }}
                        disabled={saving}
                      >
                        <UserPlus size={15} />
                      </button>
                    </div>
                  </div>

                  {/* Offering */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`offering-${row.tempId}`}>Dịch vụ / Gói liệu trình *</label>
                    <select
                      id={`offering-${row.tempId}`}
                      value={row.offeringId}
                      onChange={(e) => updateRow(index, { offeringId: e.target.value ? Number(e.target.value) : '' })}
                      disabled={saving}
                    >
                      <option value="">-- Chọn dịch vụ hoặc gói --</option>
                      {(offeringsQuery.data ?? []).map((off) => (
                        <option key={off.id} value={off.id}>
                          [{off.type === 'package' ? 'Gói' : 'Dịch vụ'}] {off.name} · {formatVnd(off.price ?? 0)} ₫
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Technician */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`technician-${row.tempId}`}>KTV thực hiện *</label>
                    <select
                      id={`technician-${row.tempId}`}
                      value={row.technicianId}
                      onChange={(e) => updateRow(index, { technicianId: e.target.value ? Number(e.target.value) : '' })}
                      disabled={saving}
                    >
                      <option value="">-- Chọn nhân viên --</option>
                      {(employeesQuery.data ?? []).map((emp) => (
                        <option key={emp.id} value={emp.id}>
                          {emp.name} ({emp.job_title})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Collected money */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`collected-${row.tempId}`}>Tiền thực thu (₫)</label>
                    <input
                      id={`collected-${row.tempId}`}
                      type="number"
                      min={0}
                      step={1}
                      value={row.collected}
                      placeholder={row.classification === 'package' ? '0 ₫ (gói)' : '0'}
                      onChange={(e) => updateRow(index, { collected: Number(e.target.value) })}
                      disabled={saving}
                    />
                  </div>
                </div>

                {/* Sub-bar: Package Info & Commission Override Toggle */}
                <div className="daily-revenue-subline">
                  <div className="daily-revenue-subline-left">
                    {row.classification === 'package' && (
                      <span className="daily-revenue-package-badge">
                        <CheckCircle2 size={13} />
                        {matchedPackage ? (
                          <>Thẻ gói <strong>{matchedPackage.code}</strong> (Còn {matchedPackage.sessions_total - matchedPackage.sessions_used} buổi)</>
                        ) : (
                          <>Khách làm gói: Tiền thu 0 ₫ (hoặc phụ thu) · KTV vẫn hưởng hoa hồng</>
                        )}
                      </span>
                    )}
                  </div>

                  <div className="daily-revenue-subline-right">
                    <button
                      type="button"
                      className={`daily-revenue-override-toggle-btn ${hasCustomCommission ? 'has-override' : ''} ${isCommissionOpen ? 'open' : ''}`}
                      onClick={() => toggleCommissionExpand(row.tempId)}
                      title="Tùy chỉnh tỷ lệ hoặc số tiền hoa hồng riêng cho ca này"
                    >
                      <SlidersHorizontal size={13} />
                      <span>
                        {hasCustomCommission
                          ? `Hoa hồng riêng: ${row.commissionType === 'percentage' ? `${row.commissionValue}%` : `${formatVnd(Number(row.commissionValue))} ₫`}`
                          : 'Tùy chỉnh hoa hồng riêng'}
                      </span>
                      <ChevronDown size={13} className={`daily-revenue-chevron ${isCommissionOpen ? 'rotate' : ''}`} />
                    </button>
                  </div>
                </div>

                {/* Collapsible Commission Override Panel */}
                {isCommissionOpen && (
                  <div className="daily-revenue-commission-editor">
                    <div className="daily-revenue-field">
                      <label htmlFor={`commission-type-${row.tempId}`}>Hình thức hoa hồng</label>
                      <select
                        id={`commission-type-${row.tempId}`}
                        value={row.commissionType}
                        disabled={saving}
                        onChange={(event) => updateRow(index, { commissionType: event.target.value as ShiftEntryRow['commissionType'] })}
                      >
                        <option value="percentage">Theo phần trăm (%)</option>
                        <option value="fixed">Theo số tiền (₫)</option>
                      </select>
                    </div>

                    <div className="daily-revenue-field">
                      <label htmlFor={`commission-value-${row.tempId}`}>
                        {row.commissionType === 'percentage' ? 'Tỷ lệ hoa hồng (%)' : 'Số tiền hoa hồng (₫)'}
                      </label>
                      <div className="daily-revenue-input-with-reset">
                        <input
                          id={`commission-value-${row.tempId}`}
                          type="number"
                          min={0}
                          max={row.commissionType === 'percentage' ? 100 : 9999999999}
                          step={row.commissionType === 'percentage' ? 0.01 : 1}
                          placeholder="Mặc định theo quy tắc"
                          value={row.commissionValue}
                          disabled={saving}
                          aria-invalid={Boolean(commissionError)}
                          aria-describedby={`commission-hint-${row.tempId}${commissionError ? ` commission-error-${row.tempId}` : ''}`}
                          onChange={(event) => updateRow(index, { commissionValue: event.target.value })}
                        />
                        {row.commissionValue !== '' && (
                          <button
                            type="button"
                            className="daily-revenue-reset-override-btn"
                            title="Đặt lại theo quy tắc hoa hồng mặc định"
                            onClick={() => updateRow(index, { commissionValue: '' })}
                            disabled={saving}
                          >
                            <RotateCcw size={12} />
                            <span>Mặc định</span>
                          </button>
                        )}
                      </div>
                    </div>

                    <div className="daily-revenue-commission-note">
                      <p id={`commission-hint-${row.tempId}`} className="daily-revenue-commission-hint">
                        Chỉ áp dụng cho ca này, không đổi quy tắc spa. {row.commissionType === 'percentage' && <>Tính trên {formatVnd(base)} ₫{row.classification === 'package' && row.collected === 0 ? ' (giá niêm yết mỗi buổi)' : ' thực thu'}.</>}
                      </p>
                      {commissionError && <p id={`commission-error-${row.tempId}`} className="daily-revenue-row-error" role="alert">{commissionError}</p>}
                    </div>
                  </div>
                )}

                {rowErrors[row.tempId] && (
                  <p id={`shift-error-${row.tempId}`} className="daily-revenue-row-error" role="alert">
                    {rowErrors[row.tempId]}
                  </p>
                )}
              </div>
            )
          })}
        </div>

        {/* Footer Bar */}
        <div className="daily-revenue-footer-bar">
          <div className="daily-revenue-draft-total">
            <span>Dự kiến ghi nhận: </span>
            <strong>{rows.length} ca</strong> · Tiền thực thu: <strong>{formatVnd(draftRevenue)} ₫</strong> · Tổng hoa hồng: <strong>{formatVnd(draftCommission)} ₫</strong>
          </div>
          <button className="primary-button" onClick={saveAll} disabled={saving}>
            <Save size={16} /> {saving ? 'Đang lưu ca làm…' : 'Lưu tất cả ca làm'}
          </button>
        </div>
      </section>

      {/* Recorded Shift Entries Table */}
      <section className="daily-revenue-card" hidden={activePanel !== 'recorded'}>
        <div className="daily-revenue-card-header">
          <div>
            <h2>Nhật ký ca làm đã ghi nhận trong ngày</h2>
            <p>Danh sách các ca khách lẻ và khách làm gói đã lưu trên hệ thống ngày {selectedDate.split('-').reverse().join('/')}.</p>
          </div>
        </div>

        <div className="daily-revenue-table-wrap" ref={recordedTableScroll}>
          <table className="daily-revenue-table">
            <thead>
              <tr>
                <th>Giờ</th>
                <th>Mã phiếu</th>
                <th>Khách hàng</th>
                <th>Phân loại</th>
                <th>Dịch vụ / Gói</th>
                <th>KTV thực hiện</th>
                <th style={{ textAlign: 'right' }}>Tiền thực thu</th>
                <th style={{ textAlign: 'right' }}>Hoa hồng KTV</th>
              </tr>
            </thead>
            <tbody>
              {shiftEntriesQuery.isPending ? (
                <tr>
                  <td colSpan={8} className="daily-revenue-table-state" role="status">Đang tải nhật ký ca làm…</td>
                </tr>
              ) : shiftEntriesQuery.isError ? (
                <tr>
                  <td colSpan={8} className="daily-revenue-table-state" role="alert">
                    <span>Chưa tải được nhật ký ca làm.</span>
                    <button type="button" className="secondary-button" onClick={() => void shiftEntriesQuery.refetch()}>Thử lại</button>
                  </td>
                </tr>
              ) : recordedEntries.length === 0 ? (
                <tr>
                  <td colSpan={8} style={{ textAlign: 'center', padding: '36px', color: 'var(--muted)' }}>
                    Chưa có ca làm nào được ghi nhận cho ngày {selectedDate.split('-').reverse().join('/')}.
                  </td>
                </tr>
              ) : (
                visibleRecordedEntries.map((inv) => {
                  const item = inv.items?.[0]
                  const comms = (inv.commission_entries ?? []).filter((c: any) => c.status !== 'reversed')
                  const totalComm = comms.reduce((s: number, c: any) => s + Number(c.amount), 0)
                  const isPackage = recordedShiftClassification(inv) === 'package'
                  const timeStr = inv.paid_at ? new Date(inv.paid_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '--:--'

                  return (
                    <tr key={inv.id}>
                      <td style={{ color: 'var(--muted)', fontSize: '12px' }}>{timeStr}</td>
                      <td><code>{inv.code}</code></td>
                      <td>
                        <strong>{inv.customer?.name || 'Khách vãng lai'}</strong>
                        {inv.customer?.phone && <div style={{ fontSize: '11px', color: 'var(--muted)' }}>{inv.customer.phone}</div>}
                      </td>
                      <td>
                        <span className={`daily-revenue-badge ${isPackage ? 'package' : 'retail'}`}>
                          {isPackage ? 'Khách làm gói' : 'Khách lẻ'}
                        </span>
                      </td>
                      <td>{item?.offering?.name || 'Dịch vụ'}</td>
                      <td>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                          <User size={14} style={{ color: 'var(--muted)' }} />
                          {item?.employee?.name || 'Chưa gán'}
                        </span>
                      </td>
                      <td className="num" style={{ color: Number(inv.total_received) === 0 ? 'var(--muted)' : 'var(--text)' }}>
                        {formatVnd(Number(inv.total_received))} ₫
                      </td>
                      <td className="num" style={{ color: '#b45309' }}>
                        {formatVnd(totalComm)} ₫
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
        {recordedEntries.length > 0 && (
          <div className="daily-revenue-pagination">
            <span>Hiển thị {visibleRecordedPage * recordedPageSize + 1}–{Math.min((visibleRecordedPage + 1) * recordedPageSize, recordedEntries.length)} / {recordedEntries.length} ca</span>
            <div>
              <button type="button" className="secondary-button" disabled={visibleRecordedPage === 0} onClick={() => setRecordedPage(visibleRecordedPage - 1)}>Trước</button>
              <span>Trang {visibleRecordedPage + 1} / {recordedPageCount}</span>
              <button type="button" className="secondary-button" disabled={visibleRecordedPage + 1 >= recordedPageCount} onClick={() => setRecordedPage(visibleRecordedPage + 1)}>Sau</button>
            </div>
          </div>
        )}
      </section>

      {newCustomerModal.open && <Popup title="Thêm nhanh khách hàng" description="Mã khách hàng được cấp tự động khi lưu." busy={creatingCustomer} error={customerError} onClose={closeNewCustomerModal}>
        <form onSubmit={(event) => { event.preventDefault(); if (!creatingCustomer) void handleCreateCustomer() }}>
          <label><span>Họ và tên <b>*</b></span><input autoFocus required maxLength={120} value={newCustomerName} onChange={(event) => setNewCustomerName(event.target.value)} placeholder="Nhập họ và tên" /></label>
          <label>Số điện thoại<input type="tel" maxLength={32} value={newCustomerPhone} onChange={(event) => setNewCustomerPhone(event.target.value)} placeholder="Có thể để trống" /></label>
          <footer><button type="button" className="secondary-button" disabled={creatingCustomer} onClick={closeNewCustomerModal}>Hủy</button><button className="primary-button" disabled={creatingCustomer}>{creatingCustomer ? 'Đang thêm…' : 'Thêm khách hàng'}</button></footer>
        </form>
      </Popup>}
    </div>
  )
}
