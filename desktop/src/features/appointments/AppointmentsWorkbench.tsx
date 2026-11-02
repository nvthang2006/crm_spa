import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AppSupabaseClient } from '../../lib/supabase'
import { ArrowLeft, ArrowRight, CalendarDays, Check, DoorOpen, LoaderCircle, Plus, Search, UserRound, UsersRound } from 'lucide-react'
import { toast } from 'sonner'
import {
  completeAppointment,
  loadAppointmentOptions,
  loadAppointmentPackages,
  loadAppointments,
  saveAppointment,
  transitionAppointment,
  consumePackageSession,
  type AppointmentDraft,
  type AppointmentOptions,
  type AppointmentRow,
  type AppointmentStatus,
} from '../../lib/appointments'
import './appointments.css'

type Props = {
  client: AppSupabaseClient
  onOpenInvoices: () => void
  onEditorStateChange: (editing: boolean) => void
}

type Action = 'arrived' | 'in_service' | 'complete' | 'use_package' | 'cancelled' | 'no_show'
type FilterStatus = AppointmentStatus | 'all' | 'waiting'

const statusText: Record<AppointmentStatus, string> = {
  scheduled: 'Đã đặt',
  arrived: 'Đã đến',
  in_service: 'Đang phục vụ',
  completed_pending_payment: 'Chờ thanh toán',
  paid: 'Đã thanh toán',
  package_used: 'Đã dùng thẻ',
  cancelled: 'Đã hủy',
  no_show: 'Không đến',
  refunded: 'Đã hoàn tiền',
}

const statusOptions: { id: FilterStatus; label: string }[] = [
  { id: 'all', label: 'Tất cả trạng thái' },
  { id: 'waiting', label: 'Chờ tiếp đón' },
  { id: 'scheduled', label: 'Đã đặt' },
  { id: 'arrived', label: 'Đã đến' },
  { id: 'in_service', label: 'Đang phục vụ' },
  { id: 'completed_pending_payment', label: 'Chờ thanh toán' },
  { id: 'paid', label: 'Đã thanh toán' },
  { id: 'package_used', label: 'Đã dùng thẻ' },
  { id: 'cancelled', label: 'Đã hủy' },
  { id: 'no_show', label: 'Không đến' },
  { id: 'refunded', label: 'Đã hoàn tiền' },
]

export default function AppointmentsWorkbench({ client, onOpenInvoices, onEditorStateChange }: Props) {
  const queryClient = useQueryClient()
  const [date, setDate] = useState(todayInVietnam())
  const [status, setStatus] = useState<FilterStatus>('all')
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<AppointmentDraft | null>(null)

  const appointments = useQuery({
    queryKey: ['appointments', date],
    queryFn: () => loadAppointments(client, date),
  })
  const options = useQuery({
    queryKey: ['appointment-options'],
    queryFn: () => loadAppointmentOptions(client),
    staleTime: 60_000,
  })

  const save = useMutation({
    mutationFn: (draft: AppointmentDraft) => saveAppointment(client, draft),
    onSuccess: async (_result, draft) => {
      setDate(draft.date)
      setEditing(null)
      await invalidateAppointmentData(queryClient)
      toast.success(draft.id ? 'Đã cập nhật lịch hẹn.' : 'Đã tạo lịch hẹn.')
    },
  })

  const act = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: Action }) => {
      if (action === 'complete') return { action, result: await completeAppointment(client, id) }
      if (action === 'use_package') return { action, result: await consumePackageSession(client, id) }
      return { action, result: await transitionAppointment(client, id, action, action === 'cancelled' ? 'Hủy bởi quản trị viên' : action === 'no_show' ? 'Khách không đến theo giờ hẹn' : undefined) }
    },
    onSuccess: async ({ action, result }) => {
      await invalidateAppointmentData(queryClient)
      if (action === 'complete' && 'invoice' in result && result.invoice?.code) {
        toast.success('Đã hoàn thành dịch vụ, tạo phiếu thu ' + result.invoice.code + '.')
      } else if (action === 'complete') {
        toast.success('Đã hoàn thành dịch vụ và trừ một buổi liệu trình.')
      } else if (action === 'use_package') {
        toast.success('Đã xác nhận trừ buổi và khóa lịch hẹn.')
      } else {
        const message: Record<'arrived' | 'in_service' | 'cancelled' | 'no_show', string> = {
          arrived: 'Đã xác nhận khách đến sảnh.',
          in_service: 'Đã đưa khách vào phục vụ.',
          cancelled: 'Đã hủy lịch hẹn.',
          no_show: 'Đã đánh dấu khách không đến.',
        }
        toast.success(message[action])
      }
    },
    onError: (error) => toast.error(messageFromError(error, 'Không thể cập nhật lịch hẹn. Kiểm tra kết nối rồi thử lại.')),
  })

  const rows = useMemo(() => appointments.data ?? [], [appointments.data])
  const stats = useMemo(() => ({
    total: rows.length,
    waiting: rows.filter((row) => row.status === 'scheduled' || row.status === 'arrived').length,
    serving: rows.filter((row) => row.status === 'in_service').length,
    pending: rows.filter((row) => row.status === 'completed_pending_payment').length,
  }), [rows])
  const visible = useMemo(() => {
    const term = search.trim().toLocaleLowerCase('vi')
    return rows.filter((row) => {
      const matchesStatus = status === 'all' || (status === 'waiting' ? row.status === 'scheduled' || row.status === 'arrived' : row.status === status)
      const matchesSearch = !term || [row.code, row.customer?.name ?? '', row.customer?.phone ?? '', row.offering?.name ?? ''].some((value) => value.toLocaleLowerCase('vi').includes(term))
      return matchesStatus && matchesSearch
    })
  }, [rows, search, status])
  const groups = useMemo(() => {
    const grouped = new Map<string, AppointmentRow[]>()
    visible.forEach((row) => {
      const hour = row.start_time ? row.start_time.slice(0, 2) + ':00' : 'Chưa xếp giờ'
      grouped.set(hour, [...(grouped.get(hour) ?? []), row])
    })
    return [...grouped.entries()]
  }, [visible])

  function beginCreate() {
    setEditing({
      id: null,
      customerId: 0,
      offeringId: 0,
      primaryEmployeeId: null,
      supportEmployeeId: null,
      resourceId: null,
      date,
      startTime: null,
      endTime: null,
      paymentType: 'service',
      packageId: null,
      source: 'booked',
      notes: '',
      overrideReason: null,
    })
  }

  function beginEdit(row: AppointmentRow) {
    setEditing({
      id: row.id,
      customerId: row.customer_id,
      offeringId: row.offering_id,
      primaryEmployeeId: row.primary_employee_id,
      supportEmployeeId: row.support_employee_id,
      resourceId: row.service_resource_id,
      date: row.appointment_date,
      startTime: row.start_time?.slice(0, 5) ?? null,
      endTime: row.end_time?.slice(0, 5) ?? null,
      paymentType: row.payment_type,
      packageId: row.customer_package_id,
      source: row.source,
      notes: row.notes ?? '',
      overrideReason: null,
    })
  }

  function runAction(row: AppointmentRow, action: Action) {
    if (action === 'cancelled') {
      const extra = row.status === 'completed_pending_payment' && row.payment_type === 'service'
        ? ' Phiếu thu đang chờ sẽ được hủy cùng lịch.'
        : ''
      if (!window.confirm('Bạn có chắc muốn hủy lịch ' + row.code + '? ' + extra)) return
    }
    if (action === 'no_show' && !window.confirm('Đánh dấu khách của lịch ' + row.code + ' không đến?')) return
    act.mutate({ id: row.id, action })
  }

  return <section className="appointment-page" aria-labelledby="appointments-title">
    <header className="appointment-heading">
      <div>
        <h1 id="appointments-title">Lịch hẹn & tiếp đón</h1>
        <p>Điều phối khách, dịch vụ và nhân viên theo ca phục vụ.</p>
      </div>
      <div className="appointment-heading-actions">
        <div className="appointment-date-nav" aria-label="Chọn ngày lịch hẹn">
          <button className="appointment-icon-button" type="button" aria-label="Ngày trước" onClick={() => setDate((value) => moveDate(value, -1))}><ArrowLeft size={17} /></button>
          <label className="appointment-date-input"><DateInput label="Ngày lịch hẹn" required value={date} onChange={(value) => { if (value) setDate(value) }} /></label>
          <button className="appointment-icon-button" type="button" aria-label="Ngày tiếp theo" onClick={() => setDate((value) => moveDate(value, 1))}><ArrowRight size={17} /></button>
        </div>
        <button className="secondary-button appointment-today" type="button" onClick={() => setDate(todayInVietnam())}>Hôm nay</button>
        <button className="primary-button" type="button" onClick={beginCreate}><Plus size={16} /> Tạo lịch hẹn</button>
      </div>
    </header>

    <div className="appointment-day-label"><CalendarDays size={16} /><strong>{formatAppointmentDate(date)}</strong><span>{stats.total} lịch trong ngày</span></div>

    <div className="appointment-status-strip" aria-label="Tóm tắt lịch trong ngày">
      <button type="button" className={status === 'all' ? 'selected' : ''} onClick={() => setStatus('all')}><span>Tổng lịch</span><b>{stats.total}</b></button>
      <button type="button" className={status === 'waiting' ? 'selected' : ''} onClick={() => setStatus('waiting')}><span>Chờ tiếp đón</span><b>{stats.waiting}</b></button>
      <button type="button" className={status === 'in_service' ? 'selected' : ''} onClick={() => setStatus('in_service')}><span>Đang phục vụ</span><b>{stats.serving}</b></button>
      <button type="button" className={status === 'completed_pending_payment' ? 'selected' : ''} onClick={() => setStatus('completed_pending_payment')}><span>Chờ thanh toán</span><b>{stats.pending}</b></button>
    </div>

    <section className="appointment-workbench" aria-label="Lịch hẹn trong ngày">
      <div className="appointment-toolbar">
        <label className="appointment-search"><Search size={17} /><input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm khách, số điện thoại, mã lịch…" aria-label="Tìm lịch hẹn" /></label>
        <label className="appointment-status-filter"><span className="sr-only">Lọc trạng thái lịch hẹn</span><select value={status} onChange={(event) => setStatus(event.target.value as FilterStatus)}>{statusOptions.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}</select></label>
        <button className="appointment-icon-button appointment-refresh" type="button" aria-label="Làm mới lịch" title="Làm mới lịch" disabled={appointments.isFetching} onClick={() => void appointments.refetch()}><LoaderCircle size={17} className={appointments.isFetching ? 'spinning' : ''} /></button>
        <span className="appointment-result-count">{visible.length} lịch hiển thị</span>
      </div>

      {appointments.isPending ? <AppointmentLoading /> : appointments.isError ? <div className="appointment-empty" role="alert"><CalendarDays size={27} /><b>Chưa tải được lịch hẹn</b><span>Kiểm tra kết nối Supabase rồi thử lại.</span><button className="secondary-button" type="button" onClick={() => void appointments.refetch()}>Thử lại</button></div> : visible.length === 0 ? <div className="appointment-empty"><CalendarDays size={27} /><b>{rows.length ? 'Không có lịch phù hợp' : 'Chưa có lịch hẹn trong ngày này'}</b><span>{rows.length ? 'Thử đổi bộ lọc hoặc từ khóa tìm kiếm.' : 'Tạo lịch đầu tiên để bắt đầu xếp ca phục vụ.'}</span>{rows.length === 0 && <button className="primary-button" type="button" onClick={beginCreate}><Plus size={16} /> Tạo lịch hẹn</button>}</div> : <div className="appointment-groups">{groups.map(([hour, items]) => <section className="appointment-time-group" key={hour} aria-label={hour === 'Chưa xếp giờ' ? hour : 'Lịch bắt đầu lúc ' + hour}>
        <div className="appointment-time-heading"><time>{hour}</time><span>{items.length} lịch</span></div>
        <div className="appointment-time-list">{items.map((row) => <AppointmentCard key={row.id} row={row} busy={act.isPending && act.variables?.id === row.id} onEdit={() => beginEdit(row)} onAction={(action) => runAction(row, action)} onOpenInvoices={onOpenInvoices} />)}</div>
      </section>)}</div>}
    </section>

    {editing && options.data && <AppointmentEditor
      key={editing.id ?? 'new'}
      client={client}
      initial={editing}
      options={options.data}
      saving={save.isPending}
      onEditorStateChange={onEditorStateChange}
      onClose={() => { if (!save.isPending) setEditing(null) }}
      onSave={async (draft) => { await save.mutateAsync(draft) }}
    />}
    {editing && options.isPending && <div className="appointment-loading-overlay" role="status">Đang tải danh mục khách hàng và ca phục vụ…</div>}
    {editing && options.isError && <div className="appointment-loading-overlay" role="alert"><div>Chưa tải được danh mục để tạo lịch.<button className="secondary-button" type="button" onClick={() => void options.refetch()}>Thử lại</button><button className="text-button" type="button" onClick={() => setEditing(null)}>Đóng</button></div></div>}
  </section>
}

function AppointmentCard({ row, busy, onEdit, onAction, onOpenInvoices }: {
  row: AppointmentRow
  busy: boolean
  onEdit: () => void
  onAction: (action: Action) => void
  onOpenInvoices: () => void
}) {
  return <article className={'appointment-card appointment-card-' + row.status}>
    <div className="appointment-card-time"><strong>{row.start_time?.slice(0, 5) ?? 'Chưa xếp giờ'}</strong>{row.end_time && <span>đến {row.end_time.slice(0, 5)}</span>}</div>
    <div className="appointment-card-person"><span className="appointment-person-icon"><UserRound size={17} /></span><div><strong title={row.customer?.name}>{row.customer?.name ?? 'Khách đã lưu trữ'}</strong><span>{row.customer?.phone ?? 'Không có số điện thoại'} · {row.code}</span></div></div>
    <div className="appointment-card-service"><strong title={row.offering?.name}>{row.offering?.name ?? 'Dịch vụ đã lưu trữ'}</strong><span>{row.payment_type === 'package' ? 'Dùng thẻ liệu trình' : 'Thanh toán dịch vụ'}{row.source === 'walkin' ? ' · Khách vãng lai' : ''}</span></div>
    <div className="appointment-card-assignment"><span><UsersRound size={14} /> {row.primary_employee?.name ?? 'Chưa phân công'}{row.support_employee ? ' + ' + row.support_employee.name : ''}</span><span><DoorOpen size={14} /> {row.resource?.name ?? 'Chưa xếp phòng'}</span></div>
    <span className={'appointment-status-badge status-' + row.status}>{statusText[row.status]}</span>
    <div className="appointment-card-actions">
      {(row.status === 'scheduled' || row.status === 'arrived') && <button className="secondary-button appointment-small-button" type="button" disabled={busy} onClick={onEdit}>Sửa</button>}
      {row.status === 'scheduled' && <button className="secondary-button appointment-small-button" type="button" disabled={busy} onClick={() => onAction('arrived')}>Khách đến</button>}
      {row.status === 'arrived' && <button className="primary-button appointment-small-button" type="button" disabled={busy} onClick={() => onAction('in_service')}>Bắt đầu</button>}
      {row.status === 'in_service' && <button className="primary-button appointment-small-button" type="button" disabled={busy} onClick={() => onAction('complete')}><Check size={14} /> Hoàn thành</button>}
      {row.status === 'completed_pending_payment' && row.payment_type === 'package' && <button className="primary-button appointment-small-button" type="button" disabled={busy} onClick={() => onAction('use_package')}>Trừ thẻ</button>}
      {row.status === 'completed_pending_payment' && row.payment_type === 'service' && <button className="secondary-button appointment-small-button" type="button" onClick={onOpenInvoices}>Mở thu ngân</button>}
      {(row.status === 'scheduled' || row.status === 'arrived') && <button className="appointment-text-action" type="button" disabled={busy} onClick={() => onAction('no_show')}>Không đến</button>}
      {!['paid', 'package_used', 'cancelled', 'no_show', 'refunded'].includes(row.status) && <button className="appointment-text-action appointment-cancel-action" type="button" disabled={busy} onClick={() => onAction('cancelled')}>Hủy</button>}
      {busy && <span className="appointment-action-progress" role="status">Đang lưu…</span>}
    </div>
  </article>
}

function AppointmentEditor({ client, initial, options, saving, onEditorStateChange, onClose, onSave }: {
  client: AppSupabaseClient
  initial: AppointmentDraft
  options: AppointmentOptions
  saving: boolean
  onEditorStateChange: (editing: boolean) => void
  onClose: () => void
  onSave: (draft: AppointmentDraft) => Promise<void>
}) {
  const [draft, setDraft] = useState(initial)
  const [formError, setFormError] = useState('')
  const [usePackage, setUsePackage] = useState(initial.id ? initial.paymentType === 'package' : true)
  const [showConflictReason, setShowConflictReason] = useState(false)
  const packages = useQuery({
    queryKey: ['appointment-packages', draft.customerId, draft.offeringId, draft.date],
    enabled: draft.customerId > 0 && draft.offeringId > 0 && Boolean(draft.date),
    queryFn: () => loadAppointmentPackages(client, draft.customerId, draft.offeringId, draft.date),
  })
  const packageRows = packages.data ?? []
  const sameOriginalSelection = draft.customerId === initial.customerId && draft.offeringId === initial.offeringId && draft.date === initial.date
  const selectedPackage = (sameOriginalSelection ? packageRows.find((item) => item.id === initial.packageId) : undefined) ?? packageRows[0]
  const packageLookupReady = draft.customerId > 0 && draft.offeringId > 0 && Boolean(draft.date)
  const packageLookupBusy = packageLookupReady && packages.isFetching
  const paymentType = usePackage && selectedPackage ? 'package' : 'service'
  const isPackageOffering = options.offerings.find((item) => item.id === draft.offeringId)?.type === 'package'

  useEffect(() => {
    onEditorStateChange(true)
    return () => onEditorStateChange(false)
  }, [onEditorStateChange])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError('')
    if (!draft.customerId || !draft.offeringId) {
      setFormError('Chọn khách hàng và dịch vụ trước khi lưu.')
      return
    }
    if (!draft.date) {
      setFormError('Ngày hẹn phải hợp lệ và có định dạng dd/mm/yyyy.')
      return
    }
    if (packageLookupBusy || (usePackage && packages.isError)) {
      setFormError('Chưa xác định được thẻ liệu trình. Vui lòng tải lại hoặc bỏ chọn dùng thẻ.')
      return
    }
    if (paymentType === 'service' && isPackageOffering) {
      setFormError('Gói liệu trình cần thẻ còn hạn và còn buổi. Hãy dùng thẻ hoặc chọn một dịch vụ lẻ.')
      return
    }
    if (draft.notes.length > 1000) {
      setFormError('Ghi chú không được vượt quá 1.000 ký tự.')
      return
    }
    try {
      await onSave({ ...draft, paymentType, packageId: paymentType === 'package' ? selectedPackage.id : null, overrideReason: showConflictReason ? draft.overrideReason : null })
    } catch (error) {
      const message = messageFromError(error, 'Không thể lưu lịch hẹn. Kiểm tra lại thông tin rồi thử lại.')
      if (/trùng|xung đột/i.test(message)) {
        setShowConflictReason(true)
        setFormError(message)
        window.setTimeout(() => document.getElementById('appointment-override-reason')?.focus(), 0)
      } else {
        setFormError(message)
      }
    }
  }

  function changeOffering(offeringId: number) {
    setDraft((current) => ({ ...current, offeringId, packageId: null }))
    setUsePackage(true)
  }

  return <Popup title={draft.id ? 'Cập nhật lịch hẹn' : 'Tạo lịch hẹn'} description="Chọn khách hàng, dịch vụ và ngày hẹn." busy={saving} onClose={onClose}>
    <form className="appointment-editor-form" onSubmit={(event) => void submit(event)}>
      <div className="appointment-editor-body">
        <label><span>Khách hàng <b>*</b></span><select required value={draft.customerId || ''} onChange={(event) => { setDraft((current) => ({ ...current, customerId: Number(event.target.value), packageId: null })); setUsePackage(true) }}><option value="">Chọn khách hàng</option>{options.customers.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.phone ?? 'Chưa có số điện thoại'}</option>)}</select></label>
        {options.customers.length === 0 && <p className="appointment-field-note">Chưa có hồ sơ khách hàng đang hoạt động. Hãy tạo khách trước khi xếp lịch.</p>}
        <label><span>Dịch vụ / gói liệu trình <b>*</b></span><select required value={draft.offeringId || ''} onChange={(event) => changeOffering(Number(event.target.value))}><option value="">Chọn dịch vụ</option>{options.offerings.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.duration_minutes} phút</option>)}</select></label>
        <div className="appointment-editor-two">
          <label><span>Ngày hẹn <b>*</b></span><DateInput required label="Ngày hẹn" value={draft.date} onChange={(date) => setDraft((current) => ({ ...current, date }))} /></label>
          <label>Nguồn lịch<select value={draft.source} onChange={(event) => setDraft((current) => ({ ...current, source: event.target.value as AppointmentDraft['source'] }))}><option value="booked">Đặt trước</option><option value="walkin">Khách vãng lai</option></select></label>
        </div>
        <label>Kỹ thuật viên chính<select value={draft.primaryEmployeeId ?? ''} onChange={(event) => setDraft((current) => ({ ...current, primaryEmployeeId: Number(event.target.value) || null }))}><option value="">Chưa phân công</option>{options.employees.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.job_title}</option>)}</select></label>
        {packageLookupReady && <div className="appointment-package-info" aria-live="polite">
          {packageLookupBusy ? <p className="appointment-field-note">Đang tìm thẻ liệu trình của khách…</p> : packages.isError ? <><p className="appointment-form-error">Không tải được thẻ liệu trình.</p><button type="button" className="text-button" onClick={() => void packages.refetch()}>Thử lại</button><label className="appointment-package-toggle"><input type="checkbox" checked={usePackage} onChange={(event) => setUsePackage(event.target.checked)} /> Dùng thẻ liệu trình</label></> : selectedPackage ? <><label className="appointment-package-toggle"><input type="checkbox" checked={usePackage} onChange={(event) => setUsePackage(event.target.checked)} /> Dùng thẻ liệu trình</label><p className="appointment-field-note">{selectedPackage.code} · còn {selectedPackage.sessions_total - selectedPackage.sessions_used} buổi{selectedPackage.expires_on ? ' · hết hạn ' + formatShortDate(selectedPackage.expires_on) : ''}</p>{!usePackage && <p className="appointment-field-note">{isPackageOffering ? 'Gói liệu trình cần dùng thẻ. Hãy bật dùng thẻ hoặc chọn dịch vụ lẻ.' : 'Lịch này tính là dịch vụ lẻ.'}</p>}</> : <p className="appointment-field-note">{isPackageOffering ? 'Không có thẻ phù hợp còn hạn và còn buổi. Hãy chọn dịch vụ lẻ hoặc cấp thẻ liệu trình trước.' : 'Không có thẻ phù hợp còn hạn và còn buổi. Lịch này tính là dịch vụ lẻ.'}</p>}
        </div>}
        <label>Ghi chú điều phối<textarea rows={3} maxLength={1000} value={draft.notes} onChange={(event) => setDraft((current) => ({ ...current, notes: event.target.value }))} placeholder="Lưu ý khi đón tiếp hoặc phục vụ khách" /><small>{draft.notes.length}/1.000 ký tự</small></label>
        {showConflictReason && <label className="appointment-conflict-field" htmlFor="appointment-override-reason"><span>Lý do ghi đè xung đột lịch <b>*</b></span><textarea id="appointment-override-reason" rows={2} maxLength={500} required value={draft.overrideReason ?? ''} onChange={(event) => setDraft((current) => ({ ...current, overrideReason: event.target.value }))} placeholder="Giải thích vì sao cần xếp trùng giờ" /><small>Hệ thống sẽ lưu lý do này vào lịch sử kiểm toán.</small></label>}
        {formError && <p className="appointment-form-error" role="alert">{formError}</p>}
      </div>
      <footer className="appointment-editor-footer"><button className="secondary-button" type="button" disabled={saving} onClick={onClose}>Hủy</button><button className="primary-button" type="submit" disabled={saving || packageLookupBusy || (usePackage && packageLookupReady && packages.isError)}>{saving ? 'Đang lưu…' : draft.id ? 'Lưu thay đổi' : 'Tạo lịch hẹn'}</button></footer>
    </form>
  </Popup>
}

function AppointmentLoading() {
  return <div className="appointment-loading" role="status"><div /><div /><div /><span>Đang tải lịch trong ngày…</span></div>
}

function todayInVietnam(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
}

function moveDate(value: string, amount: number): string {
  const date = new Date(value + 'T12:00:00+07:00')
  date.setDate(date.getDate() + amount)
  return date.toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' })
}

function formatAppointmentDate(value: string): string {
  return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date(value + 'T12:00:00+07:00'))
}

function formatShortDate(value: string): string {
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date(value + 'T12:00:00+07:00'))
}

function messageFromError(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim()) return error.message
  if (typeof error === 'object' && error !== null && 'message' in error && typeof error.message === 'string') return error.message
  return fallback
}

async function invalidateAppointmentData(queryClient: ReturnType<typeof useQueryClient>): Promise<void> {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: ['appointments'] }),
    queryClient.invalidateQueries({ queryKey: ['overview'] }),
  ])
}
