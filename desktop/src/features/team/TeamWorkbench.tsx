import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Ellipsis, Search, UserRound, UsersRound, WalletCards } from 'lucide-react'
import { toast } from 'sonner'
import type { AppSupabaseClient } from '../../lib/supabase'
import { formatVnd } from '../../lib/offerings'
import { loadCommissionOptions, loadCommissionRules, loadEmployees, saveCommissionRule, saveEmployee, setCommissionRuleActive, setEmployeeStatus, todayInBusinessTimeZone, type CommissionRule, type CommissionRuleDraft, type Employee, type EmployeeDraft } from '../../lib/team'
import './team.css'

type TeamTab = 'employees' | 'commissions'
type EmployeeFilter = 'all' | 'active' | 'inactive'
type CommissionFilter = 'all' | 'active' | 'inactive'

const emptyEmployee: EmployeeDraft = { code: '', name: '', job_title: '', base_salary: '' }
const pageSize = 10
const emptyRule = (): CommissionRuleDraft => ({
  offering_id: '', beneficiary_type: 'primary_technician', beneficiary_employee_id: '', basis: 'payment',
  calculation_type: 'percentage', value: '10', starts_on: todayInBusinessTimeZone(), ends_on: '', is_active: true, notes: '',
})

const beneficiaryLabels: Record<string, string> = {
  primary_technician: 'Kỹ thuật viên chính',
  support_technician: 'Kỹ thuật viên hỗ trợ',
  specific_employee: 'Nhân sự chỉ định',
  performer_employee: 'Nhân viên thực hiện',
}

export default function TeamWorkbench({ client, onEditorStateChange }: { client: AppSupabaseClient; onEditorStateChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [tab, setTab] = useState<TeamTab>('employees')
  const [employeeSearch, setEmployeeSearch] = useState('')
  const [employeeFilter, setEmployeeFilter] = useState<EmployeeFilter>('all')
  const [employeePage, setEmployeePage] = useState(1)
  const [ruleSearch, setRuleSearch] = useState('')
  const [ruleFilter, setRuleFilter] = useState<CommissionFilter>('all')
  const [rulePage, setRulePage] = useState(1)
  const tableRef = useRef<HTMLDivElement>(null)
  const [employeeEditing, setEmployeeEditing] = useState<Employee | null | false>(false)
  const [employeeDraft, setEmployeeDraft] = useState<EmployeeDraft>(emptyEmployee)
  const [ruleEditing, setRuleEditing] = useState<CommissionRule | null | false>(false)
  const [ruleDraft, setRuleDraft] = useState<CommissionRuleDraft>(emptyRule())
  const closeEmployeeEditor = useCallback(() => setEmployeeEditing(false), [])
  const closeRuleEditor = useCallback(() => setRuleEditing(false), [])

  const employees = useQuery({ queryKey: ['team-employees'], queryFn: () => loadEmployees(client) })
  const commissionOptions = useQuery({
    queryKey: ['team-commission-options'], queryFn: () => loadCommissionOptions(client),
    enabled: tab === 'commissions', staleTime: 60_000,
  })
  const commissionRules = useQuery({
    queryKey: ['team-commission-rules'], queryFn: () => loadCommissionRules(client),
    enabled: tab === 'commissions', staleTime: 30_000,
  })

  const saveEmployeeMutation = useMutation({
    mutationFn: () => saveEmployee(client, employeeDraft, employeeEditing ? employeeEditing.id : null),
    onSuccess: () => {
      toast.success(employeeEditing ? 'Đã cập nhật nhân viên.' : 'Đã thêm nhân viên.')
      setEmployeeEditing(false)
      void queryClient.invalidateQueries({ queryKey: ['team-employees'] })
      void queryClient.invalidateQueries({ queryKey: ['team-commission-options'] })
      void queryClient.invalidateQueries({ queryKey: ['team-commission-rules'] })
      void queryClient.invalidateQueries({ queryKey: ['overview'] })
    },
    onError: (error: Error) => toast.error(error.message || 'Không thể lưu thông tin nhân viên.'),
  })
  const toggleEmployeeMutation = useMutation({
    mutationFn: (employeeId: number) => setEmployeeStatus(client, employeeId),
    onSuccess: (status) => {
      toast.success(status === 'active' ? 'Đã kích hoạt nhân viên.' : 'Đã tạm ngưng nhân viên.')
      void queryClient.invalidateQueries({ queryKey: ['team-employees'] })
      void queryClient.invalidateQueries({ queryKey: ['team-commission-options'] })
      void queryClient.invalidateQueries({ queryKey: ['team-commission-rules'] })
      void queryClient.invalidateQueries({ queryKey: ['overview'] })
    },
    onError: (error: Error) => toast.error(error.message || 'Không thể cập nhật trạng thái nhân viên.'),
  })
  const saveRuleMutation = useMutation({
    mutationFn: () => saveCommissionRule(client, ruleDraft, ruleEditing ? ruleEditing.id : null),
    onSuccess: () => {
      toast.success(ruleEditing ? 'Đã cập nhật quy tắc hoa hồng.' : 'Đã thêm quy tắc hoa hồng.')
      setRuleEditing(false)
      void queryClient.invalidateQueries({ queryKey: ['team-commission-rules'] })
    },
    onError: (error: Error) => toast.error(error.message || 'Không thể lưu quy tắc hoa hồng.'),
  })
  const toggleRuleMutation = useMutation({
    mutationFn: (ruleId: number) => setCommissionRuleActive(client, ruleId),
    onSuccess: (active) => {
      toast.success(active ? 'Đã kích hoạt quy tắc hoa hồng.' : 'Đã tạm ngưng quy tắc hoa hồng.')
      void queryClient.invalidateQueries({ queryKey: ['team-commission-rules'] })
    },
    onError: (error: Error) => toast.error(error.message || 'Không thể cập nhật quy tắc hoa hồng.'),
  })

  const anyEditorOpen = employeeEditing !== false || ruleEditing !== false
  const anyMutationPending = saveEmployeeMutation.isPending || toggleEmployeeMutation.isPending || saveRuleMutation.isPending || toggleRuleMutation.isPending
  useEffect(() => {
    onEditorStateChange(anyEditorOpen || anyMutationPending)
    return () => onEditorStateChange(false)
  }, [anyEditorOpen, anyMutationPending, onEditorStateChange])

  const visibleEmployees = useMemo(() => (employees.data ?? []).filter((employee) => {
    const term = employeeSearch.trim().toLocaleLowerCase('vi')
    const matchesSearch = !term || `${employee.name} ${employee.code} ${employee.job_title}`.toLocaleLowerCase('vi').includes(term)
    return matchesSearch && (employeeFilter === 'all' || employee.status === employeeFilter)
  }), [employees.data, employeeFilter, employeeSearch])
  const visibleRules = useMemo(() => (commissionRules.data ?? []).filter((rule) => {
    const term = ruleSearch.trim().toLocaleLowerCase('vi')
    const matchesSearch = !term || `${rule.offering?.name ?? ''} ${rule.offering?.code ?? ''} ${beneficiaryLabels[rule.beneficiary_type] ?? rule.beneficiary_type} ${rule.beneficiary_employee?.name ?? ''}`.toLocaleLowerCase('vi').includes(term)
    return matchesSearch && (ruleFilter === 'all' || rule.is_active === (ruleFilter === 'active'))
  }), [commissionRules.data, ruleFilter, ruleSearch])
  const currentEmployeePage = Math.min(employeePage, Math.max(1, Math.ceil(visibleEmployees.length / pageSize)))
  const currentRulePage = Math.min(rulePage, Math.max(1, Math.ceil(visibleRules.length / pageSize)))
  const employeeRows = visibleEmployees.slice((currentEmployeePage - 1) * pageSize, currentEmployeePage * pageSize)
  const ruleRows = visibleRules.slice((currentRulePage - 1) * pageSize, currentRulePage * pageSize)

  function changePage(page: number) {
    if (tab === 'employees') setEmployeePage(page)
    else setRulePage(page)
    tableRef.current?.scrollTo({ top: 0 })
  }

  function openEmployeeEditor(employee?: Employee) {
    setEmployeeDraft(employee ? {
      code: employee.code, name: employee.name, job_title: employee.job_title,
      base_salary: employee.base_salary === null ? '' : String(employee.base_salary),
    } : { ...emptyEmployee })
    setEmployeeEditing(employee ?? null)
  }
  function openRuleEditor(rule?: CommissionRule) {
    setRuleDraft(rule ? {
      offering_id: String(rule.offering_id), beneficiary_type: rule.beneficiary_type,
      beneficiary_employee_id: rule.beneficiary_employee_id ? String(rule.beneficiary_employee_id) : '',
      basis: rule.basis, calculation_type: rule.calculation_type, value: String(rule.value),
      starts_on: rule.starts_on, ends_on: rule.ends_on ?? '', is_active: rule.is_active, notes: rule.notes ?? '',
    } : emptyRule())
    setRuleEditing(rule ?? null)
  }
  function submitEmployee(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const salary = Number(employeeDraft.base_salary)
    if (employeeDraft.base_salary.trim() !== '' && (!Number.isSafeInteger(salary) || salary < 0)) {
      toast.error('Lương cứng phải là số nguyên không âm.')
      return
    }
    saveEmployeeMutation.mutate()
  }
  function submitRule(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const value = Number(ruleDraft.value)
    if (!ruleDraft.offering_id || !ruleDraft.starts_on || !Number.isFinite(value) || value < 0) {
      toast.error('Hãy kiểm tra dịch vụ, ngày hiệu lực và mức hoa hồng.')
      return
    }
    if (ruleDraft.ends_on && ruleDraft.ends_on < ruleDraft.starts_on) {
      toast.error('Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.')
      return
    }
    if (ruleDraft.calculation_type === 'percentage' && value > 100) {
      toast.error('Tỷ lệ phần trăm không được vượt quá 100%.')
      return
    }
    if (['specific_employee', 'performer_employee'].includes(ruleDraft.beneficiary_type) && !ruleDraft.beneficiary_employee_id) {
      toast.error('Hãy chọn nhân sự nhận hoa hồng.')
      return
    }
    saveRuleMutation.mutate()
  }
  function changeEmployeeStatus(employee: Employee) {
    const message = employee.status === 'active'
      ? `Tạm ngưng ${employee.name}? Nhân viên sẽ không còn xuất hiện trong các lựa chọn nhân sự đang hoạt động.`
      : `Kích hoạt lại ${employee.name}?`
    if (window.confirm(message)) toggleEmployeeMutation.mutate(employee.id)
  }

  return <section className="team-page">
    <header className="team-heading">
      <div><h1>Nhân sự & hoa hồng</h1><p>Quản lý hồ sơ đội ngũ và mức hưởng theo dịch vụ.</p></div>
      {tab === 'employees'
        ? <button className="primary-button" onClick={() => openEmployeeEditor()}><UsersRound size={16} /> Thêm nhân viên</button>
        : <button className="primary-button" disabled={!commissionOptions.data?.offerings.length} onClick={() => openRuleEditor()}><WalletCards size={16} /> Thêm quy tắc</button>}
    </header>

    <div className="team-tabs" role="tablist" aria-label="Quản lý nhân sự và hoa hồng">
      <button type="button" role="tab" aria-selected={tab === 'employees'} className={tab === 'employees' ? 'selected' : ''} onClick={() => setTab('employees')}><UsersRound size={16} /> Nhân sự <span>{employees.data?.length ?? '—'}</span></button>
      <button type="button" role="tab" aria-selected={tab === 'commissions'} className={tab === 'commissions' ? 'selected' : ''} onClick={() => setTab('commissions')}><WalletCards size={16} /> Hoa hồng <span>{commissionRules.data?.length ?? '—'}</span></button>
    </div>

    {tab === 'employees' ? <section className="team-panel" aria-label="Danh sách nhân sự">
      <div className="team-panel-heading"><div><h2>Danh sách nhân sự</h2><p>Hồ sơ đã tạm ngưng vẫn được giữ để bảo toàn lịch sử vận hành.</p></div><strong>{employees.data?.filter((employee) => employee.status === 'active').length ?? '—'} <small>đang hoạt động</small></strong></div>
      <div className="team-toolbar">
        <label className="team-search"><Search size={16} /><span className="sr-only">Tìm nhân viên</span><input value={employeeSearch} onChange={(event) => { setEmployeeSearch(event.target.value); setEmployeePage(1) }} placeholder="Tìm theo tên, mã hoặc vị trí…" /></label>
        <label className="team-filter"><span className="sr-only">Lọc trạng thái nhân viên</span><select value={employeeFilter} onChange={(event) => { setEmployeeFilter(event.target.value as EmployeeFilter); setEmployeePage(1) }}><option value="all">Mọi trạng thái</option><option value="active">Đang hoạt động</option><option value="inactive">Tạm ngưng</option></select></label>
        <span className="team-result-count">{visibleEmployees.length} nhân viên</span>
      </div>
      {employees.isPending ? <div className="team-state">Đang tải danh sách nhân viên…</div> : employees.isError ? <div className="team-state team-error">Chưa tải được danh sách nhân viên. <button onClick={() => void employees.refetch()}>Thử lại</button></div> : !visibleEmployees.length ? <div className="team-state"><UserRound size={26} /><b>{employees.data?.length ? 'Không tìm thấy nhân viên phù hợp' : 'Chưa có hồ sơ nhân viên'}</b><span>{employees.data?.length ? 'Thử đổi từ khóa hoặc bộ lọc trạng thái.' : 'Thêm nhân viên đầu tiên để bắt đầu quản lý đội ngũ.'}</span>{!employees.data?.length && <button className="primary-button" onClick={() => openEmployeeEditor()}>Thêm nhân viên</button>}</div> : <div className="team-table-scroll" ref={tableRef} tabIndex={0} role="region" aria-label="Bảng nhân sự, có thể cuộn"><table className="team-table"><thead><tr><th>NHÂN VIÊN</th><th>VỊ TRÍ</th><th>LƯƠNG CỨNG</th><th>TRẠNG THÁI</th><th><span className="sr-only">Thao tác</span></th></tr></thead><tbody>{employeeRows.map((employee) => <tr key={employee.id}>
        <td><div className="team-person"><span>{employee.name.slice(0, 1).toLocaleUpperCase('vi')}</span><div><b title={employee.name}>{employee.name}</b><small title={employee.code}>{employee.code}</small></div></div></td>
        <td><div className="team-role"><b title={employee.job_title}>{employee.job_title}</b></div></td>
        <td className="team-money">{employee.base_salary === null ? '—' : `${formatVnd(employee.base_salary)} ₫`}</td>
        <td><span className={`status ${employee.status === 'active' ? 'active' : 'inactive'}`}><i />{employee.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng'}</span></td>
        <td><div className="team-row-actions"><button className="team-text-action" disabled={anyMutationPending} onClick={() => changeEmployeeStatus(employee)}>{employee.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'}</button><button className="icon-button row-edit" aria-label={`Chỉnh sửa ${employee.name}`} disabled={anyMutationPending} onClick={() => openEmployeeEditor(employee)}><Ellipsis size={18} /></button></div></td>
      </tr>)}</tbody></table></div>}
      {!employees.isPending && !employees.isError && visibleEmployees.length > pageSize && <TablePagination page={currentEmployeePage} total={visibleEmployees.length} label="nhân viên" onPageChange={changePage} />}
    </section> : <section className="team-panel" aria-label="Quy tắc hoa hồng">
      <div className="team-panel-heading"><div><h2>Quy tắc hoa hồng</h2><p>Mức áp dụng theo dịch vụ, người nhận và khoảng ngày hiệu lực.</p></div><strong>{commissionRules.data?.filter((rule) => rule.is_active).length ?? '—'} <small>đang áp dụng</small></strong></div>
      <div className="team-toolbar">
        <label className="team-search"><Search size={16} /><span className="sr-only">Tìm quy tắc hoa hồng</span><input value={ruleSearch} onChange={(event) => { setRuleSearch(event.target.value); setRulePage(1) }} placeholder="Tìm theo dịch vụ hoặc nhân viên…" /></label>
        <label className="team-filter"><span className="sr-only">Lọc trạng thái quy tắc</span><select value={ruleFilter} onChange={(event) => { setRuleFilter(event.target.value as CommissionFilter); setRulePage(1) }}><option value="all">Mọi trạng thái</option><option value="active">Đang áp dụng</option><option value="inactive">Tạm ngưng</option></select></label>
        <span className="team-result-count">{visibleRules.length} quy tắc</span>
      </div>
      {commissionRules.isPending || commissionOptions.isPending ? <div className="team-state">Đang tải quy tắc hoa hồng…</div> : commissionRules.isError || commissionOptions.isError ? <div className="team-state team-error">Chưa tải được danh sách hoa hồng. <button onClick={() => { void commissionRules.refetch(); void commissionOptions.refetch() }}>Thử lại</button></div> : !visibleRules.length ? <div className="team-state"><WalletCards size={26} /><b>{commissionRules.data?.length ? 'Không tìm thấy quy tắc phù hợp' : 'Chưa có quy tắc hoa hồng'}</b><span>{commissionRules.data?.length ? 'Thử đổi từ khóa hoặc bộ lọc trạng thái.' : 'Tạo quy tắc đầu tiên cho một dịch vụ.'}</span>{!commissionRules.data?.length && Boolean(commissionOptions.data?.offerings.length) && <button className="primary-button" onClick={() => openRuleEditor()}>Thêm quy tắc</button>}</div> : <div className="team-table-scroll" ref={tableRef} tabIndex={0} role="region" aria-label="Bảng hoa hồng, có thể cuộn"><table className="team-table commission-table"><thead><tr><th>DỊCH VỤ</th><th>NGƯỜI NHẬN</th><th>CĂN CỨ</th><th>MỨC HƯỞNG</th><th>HIỆU LỰC</th><th>TRẠNG THÁI</th><th><span className="sr-only">Thao tác</span></th></tr></thead><tbody>{ruleRows.map((rule) => <tr key={rule.id}>
        <td><div className="team-role"><b title={rule.offering?.name}>{rule.offering?.name ?? 'Dịch vụ đã lưu trữ'}</b><small title={rule.offering?.code}>{rule.offering?.code ?? `Mã dịch vụ ${rule.offering_id}`}</small></div></td>
        <td><div className="team-role"><b>{beneficiaryLabels[rule.beneficiary_type] ?? rule.beneficiary_type}</b><small title={rule.beneficiary_employee?.name}>{rule.beneficiary_employee?.name ?? (rule.beneficiary_employee_id ? `Nhân viên #${rule.beneficiary_employee_id}` : 'Theo phân công dịch vụ')}</small></div></td>
        <td>{rule.basis === 'payment' ? 'Thanh toán' : 'Hoàn thành'}</td>
        <td className="team-money">{rule.calculation_type === 'percentage' ? `${Number(rule.value).toLocaleString('vi-VN')}%` : `${formatVnd(Number(rule.value))} ₫`}</td>
        <td><div className="team-role"><b>{formatDate(rule.starts_on)} →</b><small>{rule.ends_on ? formatDate(rule.ends_on) : 'Vô thời hạn'}</small></div></td>
        <td><span className={`status ${rule.is_active ? 'active' : 'inactive'}`}><i />{rule.is_active ? 'Đang áp dụng' : 'Tạm ngưng'}</span></td>
        <td><div className="team-row-actions"><button className="team-text-action" disabled={anyMutationPending} onClick={() => toggleRuleMutation.mutate(rule.id)}>{rule.is_active ? 'Tạm ngưng' : 'Kích hoạt'}</button><button className="icon-button row-edit" aria-label={`Chỉnh sửa quy tắc cho ${rule.offering?.name ?? 'dịch vụ'}`} disabled={anyMutationPending} onClick={() => openRuleEditor(rule)}><Ellipsis size={18} /></button></div></td>
      </tr>)}</tbody></table></div>}
      {!commissionRules.isPending && !commissionOptions.isPending && !commissionRules.isError && !commissionOptions.isError && visibleRules.length > pageSize && <TablePagination page={currentRulePage} total={visibleRules.length} label="quy tắc" onPageChange={changePage} />}
      {!commissionOptions.isPending && !commissionOptions.data?.offerings.length && <p className="team-inline-note">Cần có ít nhất một dịch vụ trước khi tạo quy tắc hoa hồng.</p>}
    </section>}

    {employeeEditing !== false && <DialogShell title={employeeEditing ? 'Cập nhật nhân viên' : 'Thêm nhân viên'} description="Hồ sơ nhân sự được lưu cùng lịch sử hoa hồng và vận hành." busy={saveEmployeeMutation.isPending} error={saveEmployeeMutation.error?.message} onClose={closeEmployeeEditor}>
      <form className="team-form" onSubmit={submitEmployee}>
        <div><label><span>Họ và tên <b>*</b></span><input autoFocus required maxLength={120} value={employeeDraft.name} onChange={(event) => setEmployeeDraft({ ...employeeDraft, name: event.target.value })} /></label></div>
        <div className="team-form-grid"><label><span>Vị trí <b>*</b></span><input required maxLength={120} value={employeeDraft.job_title} onChange={(event) => setEmployeeDraft({ ...employeeDraft, job_title: event.target.value })} placeholder="Ví dụ: Kỹ thuật viên" /></label><label>Lương cứng (₫)<input type="number" min="0" step="1" placeholder="Có thể để trống" value={employeeDraft.base_salary} onChange={(event) => setEmployeeDraft({ ...employeeDraft, base_salary: event.target.value })} /></label></div>

        <p className="team-form-hint">Nhân viên mới được kích hoạt mặc định. Có thể tạm ngưng hồ sơ sau khi lưu.</p>
        <footer><button type="button" className="secondary-button" disabled={saveEmployeeMutation.isPending} onClick={() => setEmployeeEditing(false)}>Hủy</button><button className="primary-button" disabled={saveEmployeeMutation.isPending}>{saveEmployeeMutation.isPending ? 'Đang lưu…' : employeeEditing ? 'Lưu thay đổi' : 'Thêm nhân viên'}</button></footer>
      </form>
    </DialogShell>}

    {ruleEditing !== false && <DialogShell title={ruleEditing ? 'Cập nhật quy tắc hoa hồng' : 'Thêm quy tắc hoa hồng'} description="Quy tắc mới chỉ áp dụng cho giao dịch phát sinh trong thời hạn hiệu lực." busy={saveRuleMutation.isPending} error={saveRuleMutation.error?.message} onClose={closeRuleEditor}>
      <form className="team-form" onSubmit={submitRule}>
        <label><span>Dịch vụ hoặc gói liệu trình <b>*</b></span><select autoFocus required value={ruleDraft.offering_id} onChange={(event) => setRuleDraft({ ...ruleDraft, offering_id: event.target.value })}><option value="">Chọn dịch vụ</option>{commissionOptions.data?.offerings.map((offering) => <option key={offering.id} value={offering.id}>{offering.name} · {offering.type === 'package' ? 'Gói liệu trình' : 'Dịch vụ'}{offering.status !== 'active' ? ' · Tạm ngưng' : ''}</option>)}</select></label>
        <div className="team-form-grid"><label><span>Người nhận hoa hồng <b>*</b></span><select required value={ruleDraft.beneficiary_type} onChange={(event) => setRuleDraft({ ...ruleDraft, beneficiary_type: event.target.value, beneficiary_employee_id: '' })}>{Object.entries(beneficiaryLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>{['specific_employee', 'performer_employee'].includes(ruleDraft.beneficiary_type) && <label><span>Nhân viên <b>*</b></span><select required value={ruleDraft.beneficiary_employee_id} onChange={(event) => setRuleDraft({ ...ruleDraft, beneficiary_employee_id: event.target.value })}><option value="">Chọn nhân viên</option>{(commissionOptions.data?.employees ?? []).filter((employee) => ruleDraft.beneficiary_type !== 'performer_employee' || employee.status === 'active' || (ruleEditing && employee.id === ruleEditing.beneficiary_employee_id)).map((employee) => <option key={employee.id} value={employee.id}>{employee.name} · {employee.code}{employee.status !== 'active' ? ' · Tạm ngưng' : ''}</option>)}</select></label>}</div>
        <div className="team-form-grid"><label><span>Căn cứ tính <b>*</b></span><select value={ruleDraft.basis} onChange={(event) => setRuleDraft({ ...ruleDraft, basis: event.target.value })}><option value="payment">Thanh toán</option><option value="completion">Hoàn thành</option></select></label><label><span>Loại mức hưởng <b>*</b></span><select value={ruleDraft.calculation_type} onChange={(event) => setRuleDraft({ ...ruleDraft, calculation_type: event.target.value })}><option value="percentage">Phần trăm</option><option value="fixed">Số tiền cố định (₫)</option></select></label></div>
        <div className="team-form-grid"><label><span>{ruleDraft.calculation_type === 'percentage' ? 'Tỷ lệ (%)' : 'Số tiền (₫)'} <b>*</b></span><input required type="number" min="0" max={ruleDraft.calculation_type === 'percentage' ? 100 : undefined} step="0.01" value={ruleDraft.value} onChange={(event) => setRuleDraft({ ...ruleDraft, value: event.target.value })} /></label><label><span>Ngày bắt đầu <b>*</b></span><DateInput required label="Ngày bắt đầu" value={ruleDraft.starts_on} onChange={(value) => setRuleDraft({ ...ruleDraft, starts_on: value })} /></label></div>
        <div className="team-form-grid"><label>Ngày kết thúc<DateInput label="Ngày kết thúc" min={ruleDraft.starts_on} value={ruleDraft.ends_on} onChange={(value) => setRuleDraft({ ...ruleDraft, ends_on: value })} /></label><label className="team-checkbox"><input type="checkbox" checked={ruleDraft.is_active} onChange={(event) => setRuleDraft({ ...ruleDraft, is_active: event.target.checked })} /> Đang áp dụng</label></div>
        <label>Ghi chú<textarea rows={3} maxLength={1000} value={ruleDraft.notes} onChange={(event) => setRuleDraft({ ...ruleDraft, notes: event.target.value })} placeholder="Ghi chú nội bộ nếu cần" /></label>
        <p className="team-form-hint">Không thể xóa quy tắc đã dùng trong giao dịch. Hãy tạm ngưng hoặc điều chỉnh ngày hiệu lực; hoa hồng đã ghi nhận được giữ nguyên.</p>
        <footer><button type="button" className="secondary-button" disabled={saveRuleMutation.isPending} onClick={() => setRuleEditing(false)}>Hủy</button><button className="primary-button" disabled={saveRuleMutation.isPending}>{saveRuleMutation.isPending ? 'Đang lưu…' : ruleEditing ? 'Lưu thay đổi' : 'Thêm quy tắc'}</button></footer>
      </form>
    </DialogShell>}
  </section>
}

function TablePagination({ page, total, label, onPageChange }: { page: number; total: number; label: string; onPageChange: (page: number) => void }) {
  const pages = Math.ceil(total / pageSize)
  return <nav className="team-pagination" aria-label={`Phân trang ${label}`}>
    <span aria-live="polite">Hiển thị {(page - 1) * pageSize + 1}–{Math.min(page * pageSize, total)} / {total} {label}</span>
    <div><button type="button" className="secondary-button" disabled={page === 1} onClick={() => onPageChange(page - 1)}>Trước</button><span>Trang {page} / {pages}</span><button type="button" className="secondary-button" disabled={page === pages} onClick={() => onPageChange(page + 1)}>Sau</button></div>
  </nav>
}

function DialogShell({ title, description, busy, onClose, children, error }: { title: string; description: string; busy: boolean; onClose: () => void; children: ReactNode; error?: string }) {
  return <Popup title={title} description={description} busy={busy} error={error} onClose={onClose}>{children}</Popup>
}

function formatDate(date: string): string {
  const [year, month, day] = date.split('-')
  return `${day}/${month}/${year}`
}
