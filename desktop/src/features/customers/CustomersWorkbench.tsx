import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, Download, Flower2, HeartPulse, LoaderCircle, LockKeyhole, PackageCheck, Plus, RefreshCw, Search, ShieldCheck, UserRound, UsersRound } from 'lucide-react'
import { toast } from 'sonner'
import { formatVnd } from '../../lib/offerings'
import { consumeCustomerPackage, customerDraft, downloadCustomersCsv, exportCustomers, loadCustomerEmployees, loadCustomerHealth, loadCustomerPackages, loadCustomerTiers, loadCustomers, restoreCustomerPackageSession, saveCustomer, type CustomerDraft, type CustomerHealthProfile, type CustomerPackage, type CustomerRecord } from '../../lib/customers'
import type { AppSupabaseClient } from '../../lib/supabase'
import './customers.css'

const pageSize = 12

export default function CustomersWorkbench({ client, onEditorStateChange }: { client: AppSupabaseClient; onEditorStateChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [tier, setTier] = useState('all')
  const [page, setPage] = useState(0)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [draft, setDraft] = useState<CustomerDraft>(customerDraft())
  const [healthOpen, setHealthOpen] = useState(false)
  const [healthEditing, setHealthEditing] = useState(false)
  const [healthDraft, setHealthDraft] = useState<CustomerHealthProfile | null>(null)
  const [consumingPackage, setConsumingPackage] = useState<CustomerPackage | null>(null)
  const [consumeNote, setConsumeNote] = useState('')
  const [performedBy, setPerformedBy] = useState('')
  const [restoring, setRestoring] = useState<{ packageId: number; sessionNumber: number } | null>(null)
  const [restoreReason, setRestoreReason] = useState('')

  const customers = useQuery({
    queryKey: ['customers', search, tier, page],
    queryFn: () => loadCustomers(client, { search, tier, page, pageSize }),
  })
  const tiers = useQuery({ queryKey: ['customer-tiers'], queryFn: () => loadCustomerTiers(client), staleTime: 5 * 60_000 })
  const employees = useQuery({ queryKey: ['customer-employees'], queryFn: () => loadCustomerEmployees(client), staleTime: 5 * 60_000 })
  const selected = useQuery({
    queryKey: ['customer-detail', selectedId],
    enabled: selectedId !== null,
    queryFn: async () => {
      const { data, error } = await client.from('customers').select('id, code, name, phone, email, membership_tier, birthday, address, total_spend, last_visit_at, status, notes, created_at').eq('id', selectedId!).is('deleted_at', null).single()
      if (error) throw error
      return data as CustomerRecord
    },
  })
  const packages = useQuery({ queryKey: ['customer-packages', selectedId], enabled: selectedId !== null, queryFn: () => loadCustomerPackages(client, selectedId!) })
  const health = useQuery({
    queryKey: ['customer-health', selectedId], enabled: healthOpen && selectedId !== null,
    queryFn: () => loadCustomerHealth(client, selectedId!), staleTime: 5 * 60_000,
  })

  const saveBasic = useMutation({
    mutationFn: () => saveCustomer(client, draft, editingId),
    onSuccess: (row) => {
      toast.success(editingId ? 'Đã cập nhật hồ sơ khách hàng.' : 'Đã thêm khách hàng.')
      setSelectedId(row.id)
      setEditorOpen(false)
      void queryClient.invalidateQueries({ queryKey: ['customers'] })
      void queryClient.invalidateQueries({ queryKey: ['customer-detail', row.id] })
    },
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể lưu hồ sơ khách hàng.')),
  })
  const saveHealth = useMutation({
    mutationFn: async () => {
      if (!selected.data || !healthDraft) throw new Error('Chưa tải hồ sơ sức khỏe.')
      await saveCustomer(client, customerDraft(selected.data), selected.data.id, healthDraft)
    },
    onSuccess: () => {
      if (selectedId && healthDraft) queryClient.setQueryData(['customer-health', selectedId], healthDraft)
      setHealthEditing(false)
      toast.success('Đã cập nhật thông tin sức khỏe và lưu nhật ký truy cập.')
    },
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể lưu thông tin sức khỏe.')),
  })
  const consume = useMutation({
    mutationFn: () => consumingPackage ? consumeCustomerPackage(client, consumingPackage.id, performedBy ? Number(performedBy) : null, consumeNote) : Promise.reject(new Error('Chưa chọn thẻ liệu trình.')),
    onSuccess: () => {
      toast.success('Đã trừ một buổi và lưu nhật ký kiểm toán.')
      setConsumingPackage(null)
      setConsumeNote('')
      setPerformedBy('')
      void queryClient.invalidateQueries({ queryKey: ['customer-packages', selectedId] })
    },
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể trừ buổi liệu trình.')),
  })
  const restore = useMutation({
    mutationFn: () => restoring ? restoreCustomerPackageSession(client, restoring.packageId, restoring.sessionNumber, restoreReason) : Promise.reject(new Error('Chưa chọn buổi cần hoàn.')),
    onSuccess: () => {
      toast.success('Đã hoàn lại buổi và lưu lý do kiểm toán.')
      setRestoring(null)
      setRestoreReason('')
      void queryClient.invalidateQueries({ queryKey: ['customer-packages', selectedId] })
    },
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể hoàn lại buổi.')),
  })
  const exportList = useMutation({
    mutationFn: () => exportCustomers(client, search, tier),
    onSuccess: (rows) => downloadCustomersCsv(rows),
    onError: (error: Error) => toast.error(messageFor(error, 'Không thể xuất danh sách khách hàng.')),
  })

  useEffect(() => {
    onEditorStateChange(
      editorOpen || healthEditing || Boolean(consumingPackage) || Boolean(restoring)
        || saveBasic.isPending || saveHealth.isPending || consume.isPending || restore.isPending,
    )
    return () => onEditorStateChange(false)
  }, [editorOpen, healthEditing, consumingPackage, restoring, saveBasic.isPending, saveHealth.isPending, consume.isPending, restore.isPending, onEditorStateChange])

  const totalPages = Math.ceil((customers.data?.total ?? 0) / pageSize)
  const activePackages = useMemo(() => (packages.data ?? []).filter((item) => item.status === 'active' && item.sessions_used < item.sessions_total), [packages.data])

  function openNew() {
    setEditingId(null)
    setDraft(customerDraft())
    setEditorOpen(true)
  }
  function openEdit(row: CustomerRecord) {
    setEditingId(row.id)
    setDraft(customerDraft(row))
    setEditorOpen(true)
  }
  function chooseCustomer(id: number) {
    setSelectedId(id)
    setHealthOpen(false)
    setHealthEditing(false)
    setHealthDraft(null)
    setConsumingPackage(null)
  }
  function changeSearch(value: string) {
    setSearch(value)
    setPage(0)
  }
  function submitBasic(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!draft.name.trim()) {
      toast.error('Vui lòng nhập họ tên khách hàng.')
      return
    }
    saveBasic.mutate()
  }
  function submitHealth(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!healthDraft) return
    saveHealth.mutate()
  }

  return <section className="customer-page">
    <header className="customer-heading">
      <div><span className="eyebrow">CRM & LIỆU TRÌNH</span><h1>Khách hàng</h1><p>Tra cứu hồ sơ, theo dõi thẻ liệu trình và lịch sử sử dụng.</p></div>
      <div className="customer-heading-actions"><button className="secondary-button" disabled={exportList.isPending} onClick={() => exportList.mutate()}><Download size={15} />{exportList.isPending ? 'Đang xuất…' : 'Xuất danh sách'}</button><button className="primary-button" onClick={openNew}><Plus size={16} /> Thêm khách hàng</button></div>
    </header>
    <div className="customer-workspace">
      <section className="customer-directory" aria-label="Danh sách khách hàng">
        <div className="customer-directory-header"><div><h2>Danh bạ</h2><p>{customers.data?.total ?? '—'} hồ sơ phù hợp</p></div><button className="customer-icon-button" aria-label="Làm mới danh sách" title="Làm mới danh sách" onClick={() => void customers.refetch()}><RefreshCw size={16} /></button></div>
        <div className="customer-filter-bar">
          <label className="customer-search"><Search size={16} /><input value={search} onChange={(event) => changeSearch(event.target.value)} placeholder="Tìm tên, mã hoặc số điện thoại…" aria-label="Tìm khách hàng" /></label>
          <select aria-label="Lọc theo hạng thẻ" value={tier} onChange={(event) => { setTier(event.target.value); setPage(0) }}><option value="all">Tất cả hạng thẻ</option>{(tiers.data ?? ['Thành viên', 'Bạc', 'Vàng', 'Kim Cương']).map((item) => <option key={item}>{item}</option>)}</select>
        </div>
        {customers.isPending ? <div className="customer-list-state"><LoaderCircle className="spinning" size={20} />Đang tải danh bạ…</div> : customers.isError ? <div className="customer-list-state"><span>Chưa tải được danh bạ.</span><button onClick={() => void customers.refetch()}>Thử lại</button></div> : !customers.data?.rows.length ? <div className="customer-list-state"><UsersRound size={23} /><b>Chưa có khách hàng phù hợp</b><span>Thay đổi bộ lọc hoặc thêm hồ sơ mới.</span><button className="primary-button" onClick={openNew}><Plus size={15} /> Thêm khách hàng</button></div> : <div className="customer-list">
          {customers.data.rows.map((row) => <button key={row.id} className={`customer-list-row ${selectedId === row.id ? 'selected' : ''}`} onClick={() => chooseCustomer(row.id)} aria-current={selectedId === row.id ? 'true' : undefined}>
            <span className="customer-avatar"><UserRound size={17} /></span><span className="customer-list-copy"><span><b>{row.name}</b><small>{row.code}</small></span><span><small>{row.phone ?? 'Chưa có số điện thoại'}</small><em className={row.membership_tier === 'Vàng' || row.membership_tier === 'Kim Cương' ? 'vip' : ''}>{row.membership_tier}</em></span></span>
          </button>)}
        </div>}
        <footer className="customer-directory-footer"><span>{customers.data?.total ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, customers.data.total)} trên ${customers.data.total}` : '0 hồ sơ'}</span><div><button className="customer-icon-button" aria-label="Trang trước" disabled={page === 0} onClick={() => setPage((value) => Math.max(0, value - 1))}><ArrowLeft size={15} /></button><button className="customer-icon-button" aria-label="Trang sau" disabled={page + 1 >= totalPages} onClick={() => setPage((value) => value + 1)}><ArrowRight size={15} /></button></div></footer>
      </section>
      <section className="customer-profile" aria-label="Hồ sơ khách hàng">
        {selectedId === null ? <div className="customer-profile-empty"><div><UserRound size={26} /></div><b>Chọn một khách hàng</b><span>Thông tin chi tiết, thẻ liệu trình và lịch sử sử dụng sẽ hiện tại đây.</span><button className="secondary-button" onClick={openNew}><Plus size={15} /> Thêm khách hàng mới</button></div> : selected.isPending ? <div className="customer-list-state"><LoaderCircle className="spinning" size={20} />Đang tải hồ sơ…</div> : selected.isError || !selected.data ? <div className="customer-list-state"><b>Không tải được hồ sơ.</b><button onClick={() => void selected.refetch()}>Thử lại</button></div> : <>
          <header className="customer-profile-header"><div className="customer-profile-ident"><span className="customer-profile-avatar"><UserRound size={22} /></span><div><span className="eyebrow">HỒ SƠ KHÁCH HÀNG</span><h2>{selected.data.name}</h2><p><code>{selected.data.code}</code><span>·</span>{selected.data.phone ?? 'Chưa có số điện thoại'}<span className="customer-tier-pill">{selected.data.membership_tier}</span></p></div></div><button className="secondary-button customer-edit-button" onClick={() => openEdit(selected.data)}><UserRound size={15} /> Chỉnh sửa</button></header>
          <div className="customer-summary-grid"><div><span>Chi tiêu tích lũy</span><b>{formatVnd(Number(selected.data.total_spend))} ₫</b></div><div><span>Lần ghé gần nhất</span><b>{selected.data.last_visit_at ? formatDate(selected.data.last_visit_at) : 'Chưa ghi nhận'}</b></div><div><span>Thẻ còn buổi</span><b>{packages.isPending ? '—' : activePackages.length}</b></div></div>
          <div className="customer-profile-content">
            <section className="customer-section"><header><div><span className="customer-section-icon"><PackageCheck size={17} /></span><div><h3>Thẻ liệu trình</h3><p>{packages.data?.length ?? 0} thẻ trong hồ sơ</p></div></div><button className="customer-icon-button" aria-label="Làm mới thẻ liệu trình" onClick={() => void packages.refetch()}><RefreshCw size={15} /></button></header>
              {packages.isPending ? <div className="customer-section-state">Đang tải lịch sử thẻ…</div> : packages.isError ? <div className="customer-section-state">Chưa tải được lịch sử thẻ. <button onClick={() => void packages.refetch()}>Thử lại</button></div> : !packages.data?.length ? <div className="customer-section-state">Khách hàng chưa có thẻ liệu trình.</div> : <div className="customer-packages">{packages.data.map((item) => <PackageCard key={item.id} item={item} consuming={consumingPackage?.id === item.id} consumeNote={consumeNote} performedBy={performedBy} employees={employees.data ?? []} busy={consume.isPending} error={consume.error?.message} onConsume={() => { setConsumingPackage(item); setConsumeNote(''); setPerformedBy(health.data?.preferred_employee_id ? String(health.data.preferred_employee_id) : '') }} onCancelConsume={() => setConsumingPackage(null)} onConsumeNote={setConsumeNote} onPerformedBy={setPerformedBy} onSubmitConsume={() => consume.mutate()} onRestore={(sessionNumber) => { setRestoring({ packageId: item.id, sessionNumber }); setRestoreReason('') }} />)}</div>}
            </section>
            <section className={`customer-section customer-health-section ${healthOpen ? 'open' : ''}`}>
              <button className="customer-section-toggle" aria-expanded={healthOpen} onClick={() => { setHealthOpen((value) => !value); if (healthOpen) setHealthEditing(false) }}><span><span className="customer-section-icon health"><HeartPulse size={17} /></span><span><b>Thông tin sức khỏe</b><small>Thông tin nhạy cảm, mã hóa trong Supabase</small></span></span><span className="health-toggle"><LockKeyhole size={14} />{healthOpen ? 'Đang mở' : 'Chỉ xem khi cần'}</span></button>
              {healthOpen && <div className="customer-health-content">{health.isPending ? <div className="customer-section-state"><LoaderCircle className="spinning" size={18} />Đang giải mã hồ sơ an toàn…</div> : health.isError ? <div className="customer-section-state">Chưa tải được thông tin sức khỏe. <button onClick={() => void health.refetch()}>Thử lại</button></div> : <>
                <div className="customer-health-note"><ShieldCheck size={15} /> Lượt xem hồ sơ được ghi vào nhật ký kiểm toán.</div>
                <div className="customer-health-grid"><HealthValue label="Dị ứng" value={health.data?.allergy_notes} /><HealthValue label="Tình trạng hiện tại" value={health.data?.current_condition} /><HealthValue label="Chống chỉ định" value={health.data?.contraindications} /><HealthValue label="Ghi chú chăm sóc" value={health.data?.care_notes} /><HealthValue label="KTV ưu tiên" value={(employees.data ?? []).find((employee) => employee.id === health.data?.preferred_employee_id)?.name} /></div>
                <button className="secondary-button customer-health-edit" onClick={() => { setHealthDraft(health.data ?? null); setHealthEditing(true) }}>Chỉnh sửa thông tin sức khỏe</button>
              </>}{healthEditing && healthDraft && <Popup title="Chỉnh sửa thông tin sức khỏe" description={selected.data.name} busy={saveHealth.isPending} error={saveHealth.error?.message} onClose={() => setHealthEditing(false)}><form className="customer-health-form" onSubmit={submitHealth}><div className="customer-health-form-grid"><label>Dị ứng<textarea rows={2} maxLength={5000} value={healthDraft.allergy_notes ?? ''} onChange={(event) => setHealthDraft({ ...healthDraft, allergy_notes: event.target.value })} /></label><label>Chống chỉ định<textarea rows={2} maxLength={5000} value={healthDraft.contraindications ?? ''} onChange={(event) => setHealthDraft({ ...healthDraft, contraindications: event.target.value })} /></label><label>Tình trạng hiện tại<textarea rows={2} maxLength={5000} value={healthDraft.current_condition ?? ''} onChange={(event) => setHealthDraft({ ...healthDraft, current_condition: event.target.value })} /></label><label>Ghi chú chăm sóc<textarea rows={2} maxLength={5000} value={healthDraft.care_notes ?? ''} onChange={(event) => setHealthDraft({ ...healthDraft, care_notes: event.target.value })} /></label><label>KTV ưu tiên<select value={healthDraft.preferred_employee_id ?? ''} onChange={(event) => setHealthDraft({ ...healthDraft, preferred_employee_id: event.target.value ? Number(event.target.value) : null })}><option value="">Không chỉ định</option>{(employees.data ?? []).map((employee) => <option key={employee.id} value={employee.id}>{employee.name}</option>)}</select></label></div><footer><button type="button" className="secondary-button" disabled={saveHealth.isPending} onClick={() => setHealthEditing(false)}>Hủy</button><button className="primary-button" disabled={saveHealth.isPending}>{saveHealth.isPending ? 'Đang lưu…' : 'Lưu thông tin'}</button></footer></form></Popup>}</div>}
            </section>
            <section className="customer-section customer-basic-section"><header><div><span className="customer-section-icon"><UserRound size={16} /></span><div><h3>Thông tin liên hệ</h3><p>Thông tin cơ bản trong hồ sơ</p></div></div></header><dl><div><dt>Email</dt><dd>{selected.data.email || 'Chưa cập nhật'}</dd></div><div><dt>Ngày sinh</dt><dd>{selected.data.birthday ? formatDateOnly(selected.data.birthday) : 'Chưa cập nhật'}</dd></div><div><dt>Địa chỉ</dt><dd>{selected.data.address || 'Chưa cập nhật'}</dd></div><div><dt>Ghi chú</dt><dd>{selected.data.notes || 'Chưa có ghi chú'}</dd></div></dl></section>
          </div>
        </>}
      </section>
    </div>
    {editorOpen && <CustomerEditor value={draft} editing={editingId !== null} busy={saveBasic.isPending} error={saveBasic.error?.message} onChange={setDraft} onClose={() => setEditorOpen(false)} onSubmit={submitBasic} />}
    {restoring && <Popup title="Hoàn lại buổi liệu trình" description={`Thẻ ${packages.data?.find((item) => item.id === restoring.packageId)?.code ?? ''} · buổi #${restoring.sessionNumber}`} busy={restore.isPending} onClose={() => setRestoring(null)}>
      <form onSubmit={(event) => { event.preventDefault(); if (!restore.isPending && restoreReason.trim().length >= 5) restore.mutate() }}>
        <label><span>Lý do hoàn lại <b>*</b></span><textarea required minLength={5} rows={3} maxLength={500} value={restoreReason} onChange={(event) => setRestoreReason(event.target.value)} placeholder="Nhập lý do cần lưu vào nhật ký kiểm toán" /></label>
        {restore.isError && <p className="popup-error" role="alert">{restore.error.message}</p>}
        <footer><button type="button" className="secondary-button" disabled={restore.isPending} onClick={() => setRestoring(null)}>Hủy</button><button className="primary-button" disabled={restore.isPending || restoreReason.trim().length < 5}>{restore.isPending ? 'Đang lưu…' : 'Xác nhận hoàn buổi'}</button></footer>
      </form>
    </Popup>}
  </section>
}

function PackageCard({ item, consuming, consumeNote, performedBy, employees, busy, onConsume, onCancelConsume, onConsumeNote, onPerformedBy, onSubmitConsume, onRestore, error }: { error?: string;
  item: CustomerPackage; consuming: boolean; consumeNote: string; performedBy: string; employees: { id: number; name: string; job_title: string }[]; busy: boolean
  onConsume: () => void; onCancelConsume: () => void; onConsumeNote: (value: string) => void; onPerformedBy: (value: string) => void; onSubmitConsume: () => void; onRestore: (sessionNumber: number) => void
}) {
  const pct = Math.min(100, Math.round(item.sessions_used / Math.max(1, item.sessions_total) * 100))
  const restored = new Set(item.usages.filter((usage) => usage.direction === 'restore').map((usage) => usage.session_number))
  const usable = item.status === 'active' && item.sessions_used < item.sessions_total && (!item.expires_on || item.expires_on >= todayLocal())
  return <article className="customer-package-card"><header><span className="customer-package-symbol"><Flower2 size={16} /></span><span><b>{item.offering?.name ?? 'Gói liệu trình'}</b><small>{item.code}</small></span><span className={`customer-package-status ${usable ? 'active' : item.status}`}>{packageStatus(item.status, usable)}</span></header><div className="customer-package-progress"><div><span>{item.sessions_used}/{item.sessions_total} buổi đã sử dụng</span><b>{formatVnd(Number(item.unit_price))} ₫</b></div><span className="customer-progress-track"><i style={{ width: `${pct}%` }} /></span><small>Hạn dùng: {item.expires_on ? formatDateOnly(item.expires_on) : 'Không thời hạn'}</small></div>
    {item.usages.length > 0 && <details className="customer-usage-history"><summary>Lịch sử sử dụng <span>{item.usages.length} lượt</span></summary><div className="customer-usage-list">{item.usages.slice().sort((a, b) => new Date(b.used_at).getTime() - new Date(a.used_at).getTime()).map((usage) => <div key={usage.id} className={`customer-usage-row ${usage.direction}`}><span className="usage-marker" /><span><b>{usage.direction === 'use' ? `Đã trừ buổi #${usage.session_number}` : `Đã hoàn buổi #${usage.session_number}`}</b><small title={[formatDate(usage.used_at), usage.performer?.name, usage.verification_note].filter(Boolean).join(" · ")}>{formatDate(usage.used_at)}{usage.performer?.name ? ` · ${usage.performer.name}` : ''}{usage.verification_note ? ` · ${usage.verification_note}` : ''}</small></span>{usage.direction === 'use' && !restored.has(usage.session_number) && <button onClick={() => onRestore(usage.session_number)}>Hoàn buổi</button>}</div>)}</div></details>}
    {consuming && <Popup title="Trừ một buổi liệu trình" description={`${item.offering?.name ?? 'Gói liệu trình'} · ${item.code} · còn ${item.sessions_total - item.sessions_used} buổi`} busy={busy} error={error} onClose={onCancelConsume}><form className="customer-consume-form" onSubmit={(event) => { event.preventDefault(); if (!busy) onSubmitConsume() }}><label>KTV thực hiện<select value={performedBy} onChange={(event) => onPerformedBy(event.target.value)}><option value="">Dùng KTV ưu tiên trong hồ sơ</option>{employees.map((employee) => <option value={employee.id} key={employee.id}>{employee.name}</option>)}</select></label><label>Ghi chú<textarea rows={2} maxLength={500} value={consumeNote} onChange={(event) => onConsumeNote(event.target.value)} placeholder="Có thể để trống" /></label><footer><button type="button" className="secondary-button" disabled={busy} onClick={onCancelConsume}>Hủy</button><button className="primary-button" disabled={busy}>{busy ? 'Đang lưu…' : 'Xác nhận trừ buổi'}</button></footer></form></Popup>}<footer><span>{item.starts_on ? `Bắt đầu ${formatDateOnly(item.starts_on)}` : `Mua ngày ${formatDateOnly(item.purchased_on)}`}</span>{usable && <button className="secondary-button" onClick={onConsume}>Trừ một buổi</button>}</footer>
  </article>
}

function HealthValue({ label, value }: { label: string; value?: string | null }) {
  return <div><span>{label}</span><p>{value?.trim() || 'Chưa ghi nhận'}</p></div>
}

function CustomerEditor({ value, editing, busy, onChange, onClose, onSubmit, error }: { error?: string; value: CustomerDraft; editing: boolean; busy: boolean; onChange: (value: CustomerDraft) => void; onClose: () => void; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  return <Popup title={editing ? 'Chỉnh sửa khách hàng' : 'Thêm khách hàng mới'} description="Thông tin sức khỏe được mở riêng từ hồ sơ." busy={busy} error={error} onClose={onClose}><form onSubmit={onSubmit}><div className="popup-grid"><label><span>Họ và tên <b>*</b></span><input autoFocus required maxLength={120} value={value.name} onChange={(event) => onChange({ ...value, name: event.target.value })} /></label><label><span>Số điện thoại</span><input type="tel" maxLength={32} value={value.phone} onChange={(event) => onChange({ ...value, phone: event.target.value })} /></label><label>Hạng thẻ<select value={value.membership_tier} onChange={(event) => onChange({ ...value, membership_tier: event.target.value })}><option>Thành viên</option><option>Bạc</option><option>Vàng</option><option>Kim Cương</option></select></label><label>Ngày sinh<DateInput label="Ngày sinh" value={value.birthday ?? ''} onChange={(birthday) => onChange({ ...value, birthday })} /></label><label className="wide">Ghi chú<textarea rows={3} maxLength={2000} value={value.notes ?? ''} onChange={(event) => onChange({ ...value, notes: event.target.value })} /></label></div><footer><button type="button" className="secondary-button" disabled={busy} onClick={onClose}>Hủy</button><button className="primary-button" disabled={busy}>{busy ? 'Đang lưu…' : editing ? 'Lưu thay đổi' : 'Thêm khách hàng'}</button></footer></form></Popup>
}

function formatDate(value: string): string { return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date(value)) }
function formatDateOnly(value: string): string { return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date(`${value}T12:00:00+07:00`)) }
function todayLocal(): string { return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' }) }
function packageStatus(status: string, usable: boolean): string {
  if (usable) return 'Đang chạy'
  if (status === 'completed') return 'Đã hoàn thành'
  if (status === 'refunded') return 'Đã hoàn tiền'
  if (status === 'expired') return 'Đã hết hạn'
  return 'Tạm dừng'
}
function messageFor(error: Error, fallback: string): string {
  const value = error.message?.trim()
  return value && !value.startsWith('Failed to fetch') ? value : fallback
}
