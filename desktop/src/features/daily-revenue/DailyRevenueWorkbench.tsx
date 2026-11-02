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
} from 'lucide-react'
import { toast } from 'sonner'
import type { AppSupabaseClient } from '../../lib/supabase'
import { createInvoice, payInvoice, consumeCustomerPackage, type InvoiceMethod } from '../../lib/invoices'
import { saveCustomer, type CustomerDraft } from '../../lib/customers'
import { formatVnd } from '../../lib/offerings'
import './daily-revenue.css'

export type SessionClassification = 'retail' | 'package'

export type ShiftEntryRow = {
  tempId: string
  classification: SessionClassification
  customerId: number | ''
  offeringId: number | ''
  technicianId: number | ''
  collected: number
  paymentMethod: InvoiceMethod
  selectedPackageId: number | null
  notes: string
}

type CommissionRule = {
  id: number
  offering_id: number
  beneficiary_type: string
  beneficiary_employee_id: number | null
  calculation_type: string
  value: number
  starts_on: string
  ends_on: string | null
  is_active: boolean
}

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
    selectedPackageId: null,
    notes: '',
  }
}

function getTodayDateString(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
}

export default function DailyRevenueWorkbench({ client, onOpenInvoices, onEditorStateChange }: Props) {
  const queryClient = useQueryClient()
  const [selectedDate, setSelectedDate] = useState(getTodayDateString)
  const [activePanel, setActivePanel] = useState<'entry' | 'recorded'>('entry')
  const [recordedPage, setRecordedPage] = useState(0)
  const [rows, setRows] = useState<ShiftEntryRow[]>([createBlankRow()])
  const [saving, setSaving] = useState(false)
  const [newCustomerModal, setNewCustomerModal] = useState<{ open: boolean; targetRowIndex: number }>({ open: false, targetRowIndex: 0 })
  const [newCustomerName, setNewCustomerName] = useState('')
  const [newCustomerPhone, setNewCustomerPhone] = useState('')
  const [creatingCustomer, setCreatingCustomer] = useState(false)
  const [customerError, setCustomerError] = useState('')
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
        .select('id, offering_id, beneficiary_type, beneficiary_employee_id, calculation_type, value, starts_on, ends_on, is_active')
        .eq('is_active', true)
      if (error) throw error
      return (data ?? []) as CommissionRule[]
    },
  })

  // 5. Load active customer packages (to link when classification is 'package')
  const packagesQuery = useQuery({
    queryKey: ['active-customer-packages'],
    queryFn: async () => {
      const { data, error } = await client
        .from('customer_packages')
        .select('id, customer_id, offering_id, code, sessions_total, sessions_used, expires_on, status')
        .eq('status', 'active')
      if (error) throw error
      return data ?? []
    },
  })

  // 6. Load paid invoices on selectedDate (Recorded Shift Entries)
  const shiftEntriesQuery = useQuery({
    queryKey: ['daily-shift-entries', selectedDate],
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

  // Commission preview calculation
  function getRowCommission(row: ShiftEntryRow): number {
    if (!row.offeringId || !row.technicianId) return 0
    const rules = commissionRulesQuery.data ?? []
    const offering = offeringsMap.get(Number(row.offeringId))
    if (!offering) return 0

    const techId = Number(row.technicianId)
    const rule =
      rules.find(
        (r) =>
          r.offering_id === offering.id &&
          r.is_active &&
          r.beneficiary_type === 'performer_employee' &&
          r.beneficiary_employee_id === techId,
      ) ||
      rules.find(
        (r) =>
          r.offering_id === offering.id &&
          r.is_active &&
          r.beneficiary_type === 'primary_technician',
      )

    if (!rule) return 0
    if (rule.calculation_type === 'fixed') {
      return Number(rule.value)
    }

    const baseAmount =
      row.classification === 'package' && row.collected === 0
        ? offering.price || 0
        : row.collected
    return Math.round((baseAmount * Number(rule.value)) / 100)
  }

  // Row updates
  function updateRow(index: number, patch: Partial<ShiftEntryRow>) {
    setRows((prev) => {
      const next = [...prev]
      const current = { ...next[index], ...patch }

      // If classification changed to package: set collected to 0 & auto-detect customer package
      if (patch.classification === 'package') {
        current.collected = 0
        if (current.customerId && current.offeringId) {
          const pkg = (packagesQuery.data ?? []).find(
            (p) =>
              p.customer_id === current.customerId &&
              p.offering_id === current.offeringId &&
              p.sessions_used < p.sessions_total,
          )
          current.selectedPackageId = pkg ? pkg.id : null
        }
      } else if (patch.classification === 'retail') {
        current.selectedPackageId = null
        if (current.offeringId && current.collected === 0) {
          const off = offeringsMap.get(Number(current.offeringId))
          if (off && off.price) current.collected = off.price
        }
      }

      // If offering changed: auto-fill price
      if (patch.offeringId !== undefined && patch.offeringId !== '') {
        const off = offeringsMap.get(Number(patch.offeringId))
        if (off) {
          if (current.classification === 'package') {
            current.collected = 0
            if (current.customerId) {
              const pkg = (packagesQuery.data ?? []).find(
                (p) =>
                  p.customer_id === current.customerId &&
                  p.offering_id === off.id &&
                  p.sessions_used < p.sessions_total,
              )
              current.selectedPackageId = pkg ? pkg.id : null
            }
          } else {
            current.collected = off.price ?? 0
          }
        }
      }

      // If customer changed: check package
      if (patch.customerId !== undefined && patch.customerId !== '') {
        if (current.classification === 'package' && current.offeringId) {
          const pkg = (packagesQuery.data ?? []).find(
            (p) =>
              p.customer_id === patch.customerId &&
              p.offering_id === current.offeringId &&
              p.sessions_used < p.sessions_total,
          )
          current.selectedPackageId = pkg ? pkg.id : null
        }
      }

      next[index] = current
      return next
    })
  }

  function addRow() {
    const nextRow = createBlankRow()
    setRows((prev) => [...prev, nextRow])
    window.requestAnimationFrame(() => {
      document.getElementById(`classification-${nextRow.tempId}`)?.focus({ preventScroll: true })
      document.getElementById(`classification-${nextRow.tempId}`)?.scrollIntoView({ block: 'nearest' })
    })
  }

  function removeRow(index: number) {
    if (rows.length <= 1) {
      setRows([createBlankRow()])
      return
    }
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

  // Save all rows
  async function saveAll() {
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i]
      if (!row.customerId) {
        toast.error(`Ca số ${i + 1}: Vui lòng chọn khách hàng.`)
        return
      }
      if (!row.offeringId) {
        toast.error(`Ca số ${i + 1}: Vui lòng chọn dịch vụ hoặc gói.`)
        return
      }
      if (!row.technicianId) {
        toast.error(`Ca số ${i + 1}: Vui lòng chọn kỹ thuật viên thực hiện.`)
        return
      }
      if (row.collected < 0) {
        toast.error(`Ca số ${i + 1}: Tiền thực thu không được âm.`)
        return
      }
    }

    setSaving(true)
    let savedCount = 0
    const remaining = [...rows]

    try {
      for (const row of rows) {
        const offering = offeringsMap.get(Number(row.offeringId))!
        const nominalPrice = offering.price || 0
        const discountAmount = Math.max(0, nominalPrice - Number(row.collected))

        // 1. Create invoice idempotently
        const invoiceScope = `shift-${row.tempId}`
        const invoiceRes = await createInvoice(
          client,
          {
            customerId: Number(row.customerId),
            offeringId: Number(row.offeringId),
            appointmentId: null,
            employeeId: Number(row.technicianId),
            discountAmount,
          },
          invoiceScope,
        )

        // 2. Pay invoice (triggers private.create_invoice_commissions on Supabase)
        await payInvoice(
          client,
          invoiceRes.invoice.id,
          row.paymentMethod,
          row.notes.trim() || 'Nhập ca làm cuối ngày',
        )

        // 3. If package session linked, consume session
        if (row.classification === 'package' && row.selectedPackageId) {
          try {
            await consumeCustomerPackage(
              client,
              row.selectedPackageId,
              Number(row.technicianId),
              'Trừ buổi từ ca làm cuối ngày',
            )
          } catch {
            // Non-blocking package consumption warning
            toast.warning(`Đã tạo hóa đơn nhưng chưa trừ được buổi gói của thẻ #${row.selectedPackageId}.`)
          }
        }

        savedCount++
        remaining.shift()
        setRows([...remaining])
      }

      toast.success(`Đã lưu thành công ${savedCount} ca làm!`)
      setRows([createBlankRow()])
      void queryClient.invalidateQueries({ queryKey: ['daily-shift-entries', selectedDate] })
      void queryClient.invalidateQueries({ queryKey: ['overview'] })
      void queryClient.invalidateQueries({ queryKey: ['invoices'] })
      void queryClient.invalidateQueries({ queryKey: ['active-customer-packages'] })
    } catch (err: any) {
      toast.error(err.message || 'Lỗi khi lưu ca làm.')
    } finally {
      setSaving(false)
    }
  }

  // Shift summary calculations for the selected day
  const recordedEntries = shiftEntriesQuery.data ?? []
  const recordedRevenue = recordedEntries.reduce((sum, inv) => sum + Number(inv.total_received), 0)
  const recordedCommission = recordedEntries.reduce((sum, inv) => {
    const entryComms = (inv.commission_entries ?? []).filter((c: any) => c.status !== 'reversed')
    return sum + entryComms.reduce((s: number, c: any) => s + Number(c.amount), 0)
  }, 0)
  const recordedPackageSessions = recordedEntries.filter((inv) => Number(inv.total_received) === 0 || inv.type === 'package').length
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
          <DateInput label="Chọn ngày làm việc" value={selectedDate} required onChange={(value) => { if (value) { setSelectedDate(value); setRecordedPage(0) } }} />
          {onOpenInvoices && (
            <button className="secondary-button" onClick={onOpenInvoices}>
              <Receipt size={16} /> Mở sổ hóa đơn
            </button>
          )}
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
            const matchedPackage =
              row.classification === 'package' && row.customerId && row.offeringId
                ? (packagesQuery.data ?? []).find(
                    (p) =>
                      p.customer_id === row.customerId &&
                      p.offering_id === row.offeringId &&
                      p.sessions_used < p.sessions_total,
                  )
                : null

            return (
              <div key={row.tempId} className="daily-revenue-row-card">
                <div className="daily-revenue-row-top">
                  <div className="daily-revenue-row-title">
                    <span>Ca #{index + 1}</span>
                    <span className={`daily-revenue-row-badge ${row.classification}`}>
                      {row.classification === 'package' ? 'Khách làm gói' : 'Khách lẻ'}
                    </span>
                  </div>
                  {rows.length > 1 && (
                    <button
                      className="icon-button"
                      onClick={() => removeRow(index)}
                      title="Xóa ca này"
                      aria-label={`Xóa ca số ${index + 1}`}
                      disabled={saving}
                    >
                      <Trash2 size={16} />
                    </button>
                  )}
                </div>

                <div className="daily-revenue-grid">
                  {/* Classification */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`classification-${row.tempId}`}>Phân loại khách</label>
                    <select
                      id={`classification-${row.tempId}`}
                      value={row.classification}
                      onChange={(e) => updateRow(index, { classification: e.target.value as SessionClassification })}
                      disabled={saving}
                    >
                      <option value="retail">Khách lẻ trong ngày</option>
                      <option value="package">Khách đã mua gói</option>
                    </select>
                  </div>

                  {/* Customer selection */}
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
                            {c.name} · {c.phone ?? 'Chưa có số điện thoại'}
                          </option>
                        ))}
                      </select>
                      <button
                        type="button"
                        className="daily-revenue-customer-add-btn"
                        title="Thêm nhanh khách hàng"
                        aria-label={`Thêm khách hàng cho ca số ${index + 1}`}
                        onClick={() => {
                          setCustomerError('')
                          setNewCustomerModal({ open: true, targetRowIndex: index })
                          if (onEditorStateChange) onEditorStateChange(true)
                        }}
                        disabled={saving}
                      >
                        <UserPlus size={16} />
                      </button>
                    </div>
                  </div>

                  {/* Offering selection */}
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

                  {/* Collected money */}
                  <div className="daily-revenue-field">
                    <label htmlFor={`collected-${row.tempId}`}>Tiền thực thu (₫)</label>
                    <input
                      id={`collected-${row.tempId}`}
                      type="number"
                      min={0}
                      step={1000}
                      value={row.collected}
                      onChange={(e) => updateRow(index, { collected: Math.max(0, Number(e.target.value)) })}
                      disabled={saving}
                    />
                  </div>

                  {/* Technician selection */}
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
                </div>

                {/* Bottom Row Hints & Live Preview */}
                <div className="daily-revenue-row-bottom">
                  <div>
                    {row.classification === 'package' && (
                      <span className="daily-revenue-package-badge">
                        <CheckCircle2 size={14} />
                        {matchedPackage ? (
                          <>Thẻ gói <strong>{matchedPackage.code}</strong> (Còn {matchedPackage.sessions_total - matchedPackage.sessions_used} buổi)</>
                        ) : (
                          <>Khách làm gói: Tiền thu 0 ₫ (hoặc phụ thu). KTV vẫn nhận đủ hoa hồng ca làm.</>
                        )}
                      </span>
                    )}
                  </div>

                  <div className="daily-revenue-preview-commission">
                    <Sparkles size={14} />
                    <span>
                      Hoa hồng KTV dự kiến: <strong>{formatVnd(previewCommission)} ₫</strong>
                    </span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>

        {/* Footer Bar */}
        <div className="daily-revenue-footer-bar">
          <div className="daily-revenue-draft-total">
            <span>Dự kiến ghi nhận: </span>
            <strong>{rows.length} ca</strong> · Tiền thực thu: <strong>{formatVnd(draftRevenue)} ₫</strong> · Hoa hồng: <strong>{formatVnd(draftCommission)} ₫</strong>
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
                  const isPackage = Number(inv.total_received) === 0 || inv.type === 'package'
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
