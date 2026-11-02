import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, Check, CreditCard, Download, FileText, LoaderCircle, Plus, Printer, RefreshCw, Search, WalletCards } from 'lucide-react'
import { toast } from 'sonner'
import { formatVnd } from '../../lib/offerings'
import { createInvoice, downloadInvoicesCsv, exportInvoices, invoiceMethodLabel, invoiceStatusLabel, InvoiceRequestConflictError, loadInvoiceOptions, loadInvoiceStats, loadInvoices, payInvoice, refundInvoice, voidInvoice, type InvoiceFilters, type InvoiceMethod, type InvoiceOfferingOption, type InvoiceRecord } from '../../lib/invoices'
import type { AppSupabaseClient } from '../../lib/supabase'
import './invoices.css'

const pageSize = 10
const emptyDraft = { customerId: '', offeringId: '', appointmentId: '', employeeId: '', discountAmount: '0' }

export default function InvoicesWorkbench({ client, onEditorStateChange }: { client: AppSupabaseClient; onEditorStateChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [status, setStatus] = useState('all')
  const [method, setMethod] = useState('all')
  const [dateFrom, setDateFrom] = useState(todayLocal())
  const [dateTo, setDateTo] = useState(todayLocal())
  const [page, setPage] = useState(0)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [draft, setDraft] = useState({ ...emptyDraft })
  const [action, setAction] = useState<'pay' | 'void' | 'refund' | null>(null)
  const [payMethod, setPayMethod] = useState<InvoiceMethod>('cash')
  const [payReference, setPayReference] = useState('')
  const [voidReason, setVoidReason] = useState('')
  const [refundMethodValue, setRefundMethodValue] = useState<InvoiceMethod>('cash')
  const [refundReference, setRefundReference] = useState('')
  const [refundReason, setRefundReason] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => { setDebouncedSearch(search); setPage(0) }, 250)
    return () => window.clearTimeout(timer)
  }, [search])
  const filters: InvoiceFilters = { search: debouncedSearch, status, method, dateFrom, dateTo, page }
  const invoices = useQuery({ queryKey: ['invoices', filters], queryFn: () => loadInvoices(client, filters, pageSize) })
  const stats = useQuery({ queryKey: ['invoice-stats'], queryFn: () => loadInvoiceStats(client), refetchInterval: 60_000 })
  const options = useQuery({ queryKey: ['invoice-options'], queryFn: () => loadInvoiceOptions(client), enabled: editorOpen, staleTime: 60_000 })
  const selected = useQuery({
    queryKey: ['invoice-detail', selectedId], enabled: selectedId !== null,
    queryFn: async () => {
      const { data, error } = await client.from('invoices').select('id, customer_id, appointment_id, customer_package_id, code, type, status, subtotal, discount_amount, total_received, payment_method, issued_at, paid_at, voided_at, notes, customer:customers(id, code, name, phone), items:invoice_items(id, offering_id, employee_id, description, quantity, unit_price, line_total, package_sessions, offering:offerings(name), employee:employees!invoice_items_employee_id_fkey(name)), payments(id, receipt_code, method, amount, reference, paid_at), refund:invoice_refunds(id, code, method, amount, reference, reason, refunded_at), customer_package:customer_packages(id, code, sessions_total, expires_on)').eq('id', selectedId!).single()
      if (error) throw error
      return data as unknown as InvoiceRecord
    },
  })
  const pageCount = Math.ceil((invoices.data?.total ?? 0) / pageSize)

  const create = useMutation({
    mutationFn: () => createInvoice(client, { customerId: Number(draft.customerId), offeringId: Number(draft.offeringId), appointmentId: draft.appointmentId ? Number(draft.appointmentId) : null, employeeId: draft.employeeId ? Number(draft.employeeId) : null, discountAmount: Number(draft.discountAmount) }),
    onSuccess: () => { toast.success('Đã lập hóa đơn và lưu nhật ký.'); setEditorOpen(false); setDraft({ ...emptyDraft }); void queryClient.invalidateQueries({ queryKey: ['invoices'] }); void queryClient.invalidateQueries({ queryKey: ['invoice-stats'] }); void queryClient.invalidateQueries({ queryKey: ['invoice-options'] }) },
    onError: (error: Error) => {
      if (error instanceof InvoiceRequestConflictError) {
        setEditorOpen(false)
        setDraft({ ...emptyDraft })
        invalidate()
        toast.error(error.message)
        return
      }
      toast.error(messageFor(error, 'Không thể lập hóa đơn.'))
    },
  })
  const pay = useMutation({
    mutationFn: () => selectedId === null ? Promise.reject(new Error('Chưa chọn hóa đơn.')) : payInvoice(client, selectedId, payMethod, payReference),
    onSuccess: () => { toast.success('Đã xác nhận thu tiền.'); setAction(null); setPayReference(''); invalidate() },
    onError: (error: Error) => {
      if (error instanceof InvoiceRequestConflictError) {
        setAction(null)
        setPayReference('')
        invalidate()
        toast.error(error.message)
        return
      }
      toast.error(messageFor(error, 'Không thể xác nhận thanh toán.'))
    },
  })
  const voiding = useMutation({
    mutationFn: () => selectedId === null ? Promise.reject(new Error('Chưa chọn hóa đơn.')) : voidInvoice(client, selectedId, voidReason),
    onSuccess: () => { toast.success('Đã hủy hóa đơn và lưu lý do.'); setAction(null); setVoidReason(''); invalidate() },
    onError: (error: Error) => {
      if (error instanceof InvoiceRequestConflictError) {
        setAction(null)
        setVoidReason('')
        invalidate()
        toast.error(error.message)
        return
      }
      toast.error(messageFor(error, 'Không thể hủy hóa đơn.'))
    },
  })
  const refunding = useMutation({
    mutationFn: () => selectedId === null ? Promise.reject(new Error('Chưa chọn hóa đơn.')) : refundInvoice(client, selectedId, refundMethodValue, refundReason, refundReference),
    onSuccess: () => { toast.success('Đã hoàn tiền và lưu phiếu hoàn.'); setAction(null); setRefundReason(''); setRefundReference(''); invalidate() },
    onError: (error: Error) => {
      if (error instanceof InvoiceRequestConflictError) {
        setAction(null)
        setRefundReason('')
        setRefundReference('')
        invalidate()
        toast.error(error.message)
        return
      }
      toast.error(messageFor(error, 'Không thể hoàn tiền hóa đơn.'))
    },
  })
  const exportList = useMutation({
    mutationFn: () => exportInvoices(client, { search: debouncedSearch, status, method, dateFrom, dateTo }),
    onSuccess: (rows) => downloadInvoicesCsv(rows),
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể xuất danh sách hóa đơn.')),
  })

  useEffect(() => {
    onEditorStateChange(
      editorOpen || action !== null || create.isPending || pay.isPending || voiding.isPending || refunding.isPending,
    )
    return () => onEditorStateChange(false)
  }, [editorOpen, action, create.isPending, pay.isPending, voiding.isPending, refunding.isPending, onEditorStateChange])

  function invalidate() {
    void queryClient.invalidateQueries({ queryKey: ['invoices'] })
    void queryClient.invalidateQueries({ queryKey: ['invoice-detail', selectedId] })
    void queryClient.invalidateQueries({ queryKey: ['invoice-stats'] })
    void queryClient.invalidateQueries({ queryKey: ['overview'] })
    void queryClient.invalidateQueries({ queryKey: ['customer-detail'] })
    void queryClient.invalidateQueries({ queryKey: ['customer-packages'] })
  }
  function resetFilters() {
    setSearch(''); setDebouncedSearch(''); setStatus('all'); setMethod('all'); setDateFrom(''); setDateTo(''); setPage(0)
  }
  function openCreate() { setDraft({ ...emptyDraft }); setEditorOpen(true) }
  function submitCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!draft.customerId || !draft.offeringId) { toast.error('Vui lòng chọn khách hàng và dịch vụ.'); return }
    if (!Number.isInteger(Number(draft.discountAmount)) || Number(draft.discountAmount) < 0) { toast.error('Mức giảm phải là số nguyên không âm.'); return }
    const chosen = options.data?.offerings.find((item) => item.id === Number(draft.offeringId))
    if (Number(draft.discountAmount) > Number(chosen?.price ?? 0)) { toast.error('Mức giảm không được lớn hơn giá niêm yết.'); return }
    create.mutate()
  }
  function chooseAppointment(value: string) {
    const appointment = options.data?.appointments.find((item) => item.id === Number(value))
    setDraft({ ...draft, appointmentId: value, customerId: appointment ? String(appointment.customer_id) : draft.customerId, offeringId: appointment ? String(appointment.offering_id) : draft.offeringId })
  }

  return <section className="invoice-page">
    <header className="invoice-heading"><div><span className="eyebrow">THU NGÂN & DOANH THU</span><h1>Hóa đơn</h1><p>Lập phiếu thu, xác nhận thanh toán và theo dõi hoàn tiền.</p></div><div className="invoice-heading-actions"><button className="secondary-button" disabled={exportList.isPending} onClick={() => exportList.mutate()}><Download size={15} />{exportList.isPending ? 'Đang xuất…' : 'Xuất danh sách'}</button><button className="primary-button" onClick={openCreate}><Plus size={16} /> Lập hóa đơn</button></div></header>
    <div className="invoice-summary"><div><span className="invoice-summary-icon revenue"><WalletCards size={17} /></span><div><small>Doanh thu hôm nay</small><b>{stats.isPending ? '—' : `${formatVnd(stats.data?.revenueToday ?? 0)} ₫`}</b></div><span className="invoice-summary-note">Từ hóa đơn đã thanh toán</span></div><div><span className="invoice-summary-icon pending"><FileText size={17} /></span><div><small>Phiếu chờ thanh toán</small><b>{stats.isPending ? '—' : stats.data?.pending ?? 0}</b></div><span className="invoice-summary-note">Cần thu ngân xử lý</span></div></div>
    <section className="invoice-workbench">
      <div className="invoice-workbench-heading"><div><h2>Danh sách phiếu thu</h2><p>{invoices.data?.total ?? '—'} hóa đơn theo bộ lọc</p></div><button className="invoice-icon-button" aria-label="Làm mới" onClick={() => { void invoices.refetch(); void stats.refetch() }}><RefreshCw size={16} /></button></div>
      <div className="invoice-filter-bar"><label className="invoice-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm mã hóa đơn, tên hoặc số điện thoại…" aria-label="Tìm hóa đơn" /></label><select aria-label="Lọc trạng thái" value={status} onChange={(event) => { setStatus(event.target.value); setPage(0) }}><option value="all">Mọi trạng thái</option><option value="draft">Bản nháp</option><option value="pending">Chờ thanh toán</option><option value="paid">Đã thanh toán</option><option value="void">Đã hủy</option><option value="refunded">Đã hoàn tiền</option></select><select aria-label="Lọc phương thức" value={method} onChange={(event) => { setMethod(event.target.value); setPage(0) }}><option value="all">Mọi phương thức</option><option value="cash">Tiền mặt</option><option value="vietqr">VietQR</option><option value="pos">POS</option></select><label className="invoice-date-filter"><span>Từ</span><DateInput label="Từ ngày" value={dateFrom} onChange={(value) => { setDateFrom(value); setPage(0) }} /></label><label className="invoice-date-filter"><span>Đến</span><DateInput label="Đến ngày" value={dateTo} onChange={(value) => { setDateTo(value); setPage(0) }} /></label><button className="invoice-clear-filter" onClick={resetFilters}>Xóa lọc</button></div>
      <div className="invoice-layout">
        <div className="invoice-list-panel">
          {invoices.isPending ? <div className="invoice-state"><LoaderCircle className="spinning" size={19} />Đang tải hóa đơn…</div> : invoices.isError ? <div className="invoice-state">Chưa tải được danh sách. <button onClick={() => void invoices.refetch()}>Thử lại</button></div> : !invoices.data?.rows.length ? <div className="invoice-state"><FileText size={25} /><b>Không có hóa đơn phù hợp</b><span>Thử đổi khoảng ngày hoặc điều kiện lọc.</span></div> : <div className="invoice-list">{invoices.data.rows.map((row) => <button key={row.id} className={`invoice-list-row ${selectedId === row.id ? 'selected' : ''}`} onClick={() => { setSelectedId(row.id); setAction(null) }} aria-current={selectedId === row.id ? 'true' : undefined}><span className={`invoice-list-symbol ${row.status}`}><FileText size={16} /></span><span className="invoice-list-main"><span><b>{row.code}</b><em className={`invoice-status ${row.status}`}>{invoiceStatusLabel(row.status)}</em></span><span><small>{row.customer?.name ?? 'Khách hàng'} · {row.items.length} mục</small><strong>{formatVnd(Number(row.total_received || row.subtotal - row.discount_amount))} ₫</strong></span><span><small>{row.issued_at ? formatDate(row.issued_at) : 'Chưa phát hành'}</small><small>{invoiceMethodLabel(row.payment_method)}</small></span></span></button>)}</div>}
          <footer className="invoice-pagination"><span>{invoices.data?.total ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, invoices.data.total)} trên ${invoices.data.total}` : '0 hóa đơn'}</span><div><button className="invoice-icon-button" aria-label="Trang trước" disabled={page === 0} onClick={() => setPage((value) => Math.max(0, value - 1))}><ArrowLeft size={15} /></button><button className="invoice-icon-button" aria-label="Trang sau" disabled={page + 1 >= pageCount} onClick={() => setPage((value) => value + 1)}><ArrowRight size={15} /></button></div></footer>
        </div>
        <aside className="invoice-detail-panel" aria-label="Chi tiết hóa đơn">
          {selectedId === null ? <div className="invoice-detail-empty"><span><FileText size={24} /></span><b>Chọn một hóa đơn</b><small>Chi tiết dòng hàng và thao tác sẽ hiện tại đây.</small></div> : selected.isPending ? <div className="invoice-state"><LoaderCircle className="spinning" size={18} />Đang tải chi tiết…</div> : selected.isError || !selected.data ? <div className="invoice-state">Chưa tải được chi tiết. <button onClick={() => void selected.refetch()}>Thử lại</button></div> : <InvoiceDetails invoice={selected.data} action={action} busy={pay.isPending || voiding.isPending || refunding.isPending} payMethod={payMethod} payReference={payReference} voidReason={voidReason} refundMethod={refundMethodValue} refundReference={refundReference} refundReason={refundReason} actionError={(action === 'pay' ? pay.error : action === 'void' ? voiding.error : refunding.error)?.message} onAction={setAction} onPayMethod={setPayMethod} onPayReference={setPayReference} onVoidReason={setVoidReason} onRefundMethod={setRefundMethodValue} onRefundReference={setRefundReference} onRefundReason={setRefundReason} onPay={() => pay.mutate()} onVoid={() => voiding.mutate()} onRefund={() => refunding.mutate()} />}
        </aside>
      </div>
    </section>
    {editorOpen && <InvoiceEditor value={draft} options={options.data} busy={create.isPending} optionsPending={options.isPending} error={create.error?.message || (options.isError ? 'Không tải được danh mục. Vui lòng thử lại.' : undefined)} onRetry={() => void options.refetch()} onChange={setDraft} onChooseAppointment={chooseAppointment} onClose={() => setEditorOpen(false)} onSubmit={submitCreate} />}
  </section>
}

function InvoiceDetails({ invoice, action, busy, payMethod, payReference, voidReason, refundMethod, refundReference, refundReason, onAction, onPayMethod, onPayReference, onVoidReason, onRefundMethod, onRefundReference, onRefundReason, onPay, onVoid, onRefund, actionError }: { actionError?: string;
  invoice: InvoiceRecord; action: 'pay' | 'void' | 'refund' | null; busy: boolean; payMethod: InvoiceMethod; payReference: string; voidReason: string; refundMethod: InvoiceMethod; refundReference: string; refundReason: string
  onAction: (value: 'pay' | 'void' | 'refund' | null) => void; onPayMethod: (value: InvoiceMethod) => void; onPayReference: (value: string) => void; onVoidReason: (value: string) => void; onRefundMethod: (value: InvoiceMethod) => void; onRefundReference: (value: string) => void; onRefundReason: (value: string) => void; onPay: () => void; onVoid: () => void; onRefund: () => void
}) {
  const total = Math.max(0, Number(invoice.subtotal) - Number(invoice.discount_amount))
  return <div className="invoice-detail"><header className="invoice-detail-heading"><div><span className={`invoice-list-symbol ${invoice.status}`}><FileText size={17} /></span><div><span className="eyebrow">CHI TIẾT PHIẾU THU</span><h2>{invoice.code}</h2><p>{invoice.issued_at ? formatDate(invoice.issued_at) : 'Chưa phát hành'}</p></div></div><div className="invoice-detail-heading-tools"><span className={`invoice-status ${invoice.status}`}>{invoiceStatusLabel(invoice.status)}</span><button className="secondary-button invoice-print-button" aria-label={`In hóa đơn ${invoice.code}`} onClick={() => window.print()}><Printer size={14} /> In hóa đơn</button></div></header>
    <div className="invoice-customer-box"><span className="invoice-customer-avatar">{invoice.customer?.name?.slice(0, 1).toUpperCase() ?? 'K'}</span><div><b>{invoice.customer?.name ?? 'Khách hàng'}</b><small>{invoice.customer?.phone ?? 'Chưa có số điện thoại'} · {invoice.customer?.code ?? ''}</small></div></div>
    <section className="invoice-detail-section"><h3>Dòng hàng</h3>{invoice.items.length ? invoice.items.map((item) => <div className="invoice-item-row" key={item.id}><span><b>{item.description}</b><small>{item.quantity} × {formatVnd(Number(item.unit_price))} ₫{item.employee?.name ? ` · ${item.employee.name}` : ''}</small></span><strong>{formatVnd(Number(item.line_total))} ₫</strong></div>) : <p className="invoice-inline-muted">Chưa có dòng hàng.</p>}
      <div className="invoice-total-lines"><span>Tạm tính <b>{formatVnd(Number(invoice.subtotal))} ₫</b></span><span>Giảm giá <b>− {formatVnd(Number(invoice.discount_amount))} ₫</b></span><strong>Tổng cần thu <b>{formatVnd(total)} ₫</b></strong></div>
    </section>
    <section className="invoice-detail-section"><h3>Thanh toán</h3>{invoice.payments.length ? invoice.payments.map((payment) => <div className="invoice-payment-row" key={payment.id}><span className="payment-check"><Check size={13} /></span><span><b>{invoiceMethodLabel(payment.method)} · {payment.receipt_code}</b><small>{formatDate(payment.paid_at)}{payment.reference ? ` · Mã ${payment.reference}` : ''}</small></span><strong>{formatVnd(Number(payment.amount))} ₫</strong></div>) : <p className="invoice-inline-muted">Chưa ghi nhận thanh toán.</p>}{invoice.refund && <div className="invoice-payment-row refund" key={invoice.refund.id}><span className="payment-check"><RefreshCw size={12} /></span><span><b>Hoàn tiền · {invoice.refund.code}</b><small>{formatDate(invoice.refund.refunded_at)} · {invoice.refund.reason}</small></span><strong>− {formatVnd(Number(invoice.refund.amount))} ₫</strong></div>}{invoice.customer_package && <div className="invoice-package-note">Đã tạo thẻ {invoice.customer_package.code} · {invoice.customer_package.sessions_total} buổi</div>}</section>
    {invoice.notes && invoice.status === 'void' && <div className="invoice-void-note"><b>Lý do hủy</b><p>{invoice.notes}</p></div>}
    {invoice.status === 'pending' && <div className="invoice-detail-actions"><button className="primary-button" onClick={() => onAction('pay')}><CreditCard size={15} /> Thu tiền</button><button className="secondary-button danger-button" onClick={() => onAction('void')}>Hủy hóa đơn</button></div>}
    {invoice.status === 'paid' && <div className="invoice-detail-actions"><button className="secondary-button danger-button" onClick={() => onAction('refund')}>Hoàn tiền</button><span>Chỉ hoàn tiền khi chưa khóa kỳ lương.</span></div>}
    {action === 'pay' && <Popup title="Xác nhận thu tiền" description={`${invoice.code} · ${invoice.customer?.name ?? 'Khách hàng'}`} busy={busy} error={actionError} onClose={() => onAction(null)}><form className="invoice-action-form" onSubmit={(event) => { event.preventDefault(); if ((payMethod === 'cash' || payReference.trim()) && !busy) onPay() }}><label>Phương thức<select value={payMethod} onChange={(event) => onPayMethod(event.target.value as InvoiceMethod)}><option value="cash">Tiền mặt</option><option value="vietqr">VietQR</option><option value="pos">POS</option></select></label>{payMethod !== 'cash' && <label><span>Mã giao dịch <b>*</b></span><input required maxLength={120} value={payReference} onChange={(event) => onPayReference(event.target.value)} /></label>}<div className="invoice-action-total"><span>Số tiền thu</span><b>{formatVnd(total)} ₫</b></div><footer><button type="button" className="secondary-button" disabled={busy} onClick={() => onAction(null)}>Hủy</button><button className="primary-button" disabled={busy || (payMethod !== 'cash' && !payReference.trim())}>{busy ? 'Đang xử lý…' : 'Xác nhận đã thu'}</button></footer></form></Popup>}
    {action === 'void' && <Popup title="Hủy hóa đơn" description={`${invoice.code} · ${invoice.customer?.name ?? 'Khách hàng'}`} busy={busy} error={actionError} onClose={() => onAction(null)}><form className="invoice-action-form" onSubmit={(event) => { event.preventDefault(); if (voidReason.trim().length >= 5 && !busy) onVoid() }}><label><span>Lý do hủy <b>*</b></span><textarea rows={3} maxLength={500} value={voidReason} onChange={(event) => onVoidReason(event.target.value)} placeholder="Nhập lý do cần lưu" /></label><p className="invoice-action-hint">Hóa đơn đã thanh toán cần dùng luồng hoàn tiền.</p><footer><button type="button" className="secondary-button" disabled={busy} onClick={() => onAction(null)}>Hủy</button><button className="primary-button danger-fill" disabled={busy || voidReason.trim().length < 5}>{busy ? 'Đang xử lý…' : 'Xác nhận hủy'}</button></footer></form></Popup>}
    {action === 'refund' && <Popup title="Hoàn tiền toàn bộ" description={`${invoice.code} · ${invoice.customer?.name ?? 'Khách hàng'}`} busy={busy} error={actionError} onClose={() => onAction(null)}><form className="invoice-action-form" onSubmit={(event) => { event.preventDefault(); if (refundReason.trim().length >= 5 && (refundMethod === 'cash' || refundReference.trim()) && !busy) onRefund() }}><label>Phương thức hoàn<select value={refundMethod} onChange={(event) => onRefundMethod(event.target.value as InvoiceMethod)}><option value="cash">Tiền mặt</option><option value="vietqr">VietQR</option><option value="pos">POS</option></select></label>{refundMethod !== 'cash' && <label><span>Mã giao dịch hoàn <b>*</b></span><input required maxLength={120} value={refundReference} onChange={(event) => onRefundReference(event.target.value)} /></label>}<label><span>Lý do hoàn tiền <b>*</b></span><textarea rows={3} maxLength={500} value={refundReason} onChange={(event) => onRefundReason(event.target.value)} placeholder="Nhập lý do cần lưu" /></label><div className="invoice-action-total"><span>Số tiền hoàn</span><b>{formatVnd(Number(invoice.total_received))} ₫</b></div><p className="invoice-action-hint">Gói đã trừ buổi hoặc hoa hồng đã khóa không thể hoàn toàn bộ.</p><footer><button type="button" className="secondary-button" disabled={busy} onClick={() => onAction(null)}>Hủy</button><button className="primary-button danger-fill" disabled={busy || refundReason.trim().length < 5 || (refundMethod !== 'cash' && !refundReference.trim())}>{busy ? 'Đang xử lý…' : 'Xác nhận hoàn tiền'}</button></footer></form></Popup>}
  </div>
}

function InvoiceEditor({ value, options, busy, optionsPending, onChange, onChooseAppointment, onClose, onSubmit, error, onRetry }: {
  error?: string; onRetry: () => void; value: typeof emptyDraft; options: Awaited<ReturnType<typeof loadInvoiceOptions>> | undefined; busy: boolean; optionsPending: boolean
  onChange: (value: typeof emptyDraft) => void; onChooseAppointment: (value: string) => void; onClose: () => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  const selectedOffering: InvoiceOfferingOption | undefined = options?.offerings.find((item) => item.id === Number(value.offeringId))
  const total = Math.max(0, Number(selectedOffering?.price ?? 0) - Number(value.discountAmount || 0))
  const appointments = (options?.appointments ?? []).filter((row) => (!value.customerId || row.customer_id === Number(value.customerId)) && (!value.offeringId || row.offering_id === Number(value.offeringId)))
  return <Popup title="Hóa đơn mới" description="Chỉ lập hóa đơn cho dịch vụ hoặc gói đang hoạt động." busy={busy} error={error} onClose={onClose}><form onSubmit={onSubmit}><div className="invoice-editor-body">{!options && !optionsPending && <button type="button" className="secondary-button" onClick={onRetry}>Tải lại danh mục</button>}{optionsPending ? <div className="invoice-state"><LoaderCircle className="spinning" size={18} />Đang tải dữ liệu…</div> : <>
    <label><span>Khách hàng <b>*</b></span><select required value={value.customerId} onChange={(event) => onChange({ ...value, customerId: event.target.value, appointmentId: '' })}><option value="">Chọn khách hàng</option>{(options?.customers ?? []).map((item) => <option key={item.id} value={item.id}>{item.name} · {item.phone ?? 'Chưa có số điện thoại'} ({item.code})</option>)}</select></label>
    <label><span>Dịch vụ hoặc gói liệu trình <b>*</b></span><select required value={value.offeringId} onChange={(event) => onChange({ ...value, offeringId: event.target.value, appointmentId: '' })}><option value="">Chọn dịch vụ</option>{(options?.offerings ?? []).map((item) => <option key={item.id} value={item.id}>{item.name} · {item.type === 'package' ? `${item.sessions_total} buổi` : 'Dịch vụ'} · {formatVnd(Number(item.price ?? 0))} ₫</option>)}</select></label>
    <label>Lịch hẹn đã hoàn thành<select value={value.appointmentId} onChange={(event) => onChooseAppointment(event.target.value)} disabled={!appointments.length}><option value="">Không liên kết lịch hẹn</option>{appointments.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.customer?.name} · {item.offering?.name} · {item.appointment_date.split('-').reverse().join('/')} {item.start_time?.slice(0, 5) ?? 'Chưa xếp giờ'}</option>)}</select><small>Chỉ hiện lịch dịch vụ hoàn thành đang chờ thanh toán.</small></label>
    <label>Nhân sự thực hiện<select value={value.employeeId} onChange={(event) => onChange({ ...value, employeeId: event.target.value })}><option value="">Không chỉ định</option>{(options?.employees ?? []).map((item) => <option key={item.id} value={item.id}>{item.name} · {item.job_title}</option>)}</select></label>
    <label>Mức giảm (VND)<input type="number" min={0} max={Number(selectedOffering?.price ?? 0)} step={1000} value={value.discountAmount} onChange={(event) => onChange({ ...value, discountAmount: event.target.value })} /></label>
    <div className="invoice-editor-total"><span>Giá niêm yết <b>{formatVnd(Number(selectedOffering?.price ?? 0))} ₫</b></span><span>Giảm giá <b>− {formatVnd(Number(value.discountAmount || 0))} ₫</b></span><strong>Tổng cần thu <b>{formatVnd(total)} ₫</b></strong></div>
    <div className="invoice-editor-security"><CreditCard size={15} /><span>Hóa đơn được lưu ở trạng thái chờ thanh toán. Thu ngân xác nhận giao dịch riêng sau khi nhận tiền.</span></div>
  </>}</div><footer><button type="button" className="secondary-button" disabled={busy} onClick={onClose}>Hủy</button><button className="primary-button" disabled={busy || optionsPending || !options?.customers.length || !options.offerings.length}>{busy ? 'Đang lưu…' : 'Tạo hóa đơn'}</button></footer></form></Popup>
}

function formatDate(value: string): string { return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date(value)) }
function todayLocal(): string { return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' }) }
function messageFor(error: Error, fallback: string): string { const value = error.message?.trim(); return value && !value.startsWith('Failed to fetch') ? value : fallback }
