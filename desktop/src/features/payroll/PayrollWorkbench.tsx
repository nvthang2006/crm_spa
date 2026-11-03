import { useState, useMemo } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Calculator,
  Download,
  Lock,
  Plus,
  Check,
  X,
  FileSpreadsheet,
} from 'lucide-react'
import { toast } from 'sonner'
import type { AppSupabaseClient } from '../../lib/supabase'
import {
  getCurrentMonthRange,
  getPayrollErrorMessage,
  validatePayrollRange,
  loadPayrollPeriods,
  loadPayrollPeriodById,
  generatePayroll,
  lockPayroll,
  createPayrollAdjustment,
  reviewPayrollAdjustment,
  exportPayrollCsv,
  type PayrollAdjustmentDraft,
} from '../../lib/payroll'
import { loadEmployees } from '../../lib/team'
import { formatVnd } from '../../lib/offerings'
import { Popup } from '../../Popup'
import { DateInput } from '../../DateInput'
import './payroll.css'

type Props = {
  client: AppSupabaseClient
  onEditorStateChange?: (open: boolean) => void
}

const emptyAdjustment: PayrollAdjustmentDraft = {
  employee_id: '',
  type: 'bonus',
  amount: '',
  reason: '',
}

export default function PayrollWorkbench({ client, onEditorStateChange }: Props) {
  const queryClient = useQueryClient()
  const initialRange = useMemo(() => getCurrentMonthRange(), [])
  const [periodStart, setPeriodStart] = useState(initialRange.start)
  const [periodEnd, setPeriodEnd] = useState(initialRange.end)
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null)

  const [showAdjustmentModal, setShowAdjustmentModal] = useState(false)
  const [adjustmentDraft, setAdjustmentDraft] = useState<PayrollAdjustmentDraft>(emptyAdjustment)
  const [adjError, setAdjError] = useState<string | undefined>()
  const [rangeError, setRangeError] = useState<string | undefined>()

  // Load list of periods
  const periodsQuery = useQuery({
    queryKey: ['payroll-periods'],
    queryFn: () => loadPayrollPeriods(client),
  })

  // Determine current active period ID
  const activePeriodId = useMemo(() => {
    if (selectedPeriodId) return selectedPeriodId
    const found = (periodsQuery.data ?? []).find(
      (p) => p.period_start === periodStart && p.period_end === periodEnd
    )
    return found ? found.id : null
  }, [selectedPeriodId, periodsQuery.data, periodStart, periodEnd])

  // Load details of active period (lines & adjustments)
  const periodDetailQuery = useQuery({
    queryKey: ['payroll-period-detail', activePeriodId],
    queryFn: () => (activePeriodId ? loadPayrollPeriodById(client, activePeriodId) : null),
    enabled: Boolean(activePeriodId),
  })

  // Load employees for adjustments form
  const employeesQuery = useQuery({
    queryKey: ['active-employees-payroll'],
    queryFn: () => loadEmployees(client),
    staleTime: 60_000,
  })

  // Generate / calculate payroll
  const generateMutation = useMutation({
    mutationFn: () => generatePayroll(client, periodStart, periodEnd),
    onSuccess: (data) => {
      toast.success(`Đã tính bảng lương kỳ ${data.code}.`)
      setSelectedPeriodId(data.id)
      queryClient.invalidateQueries({ queryKey: ['payroll-periods'] })
      queryClient.invalidateQueries({ queryKey: ['payroll-period-detail', data.id] })
    },
    onError: (err: unknown) => {
      toast.error(getPayrollErrorMessage(err, 'Chưa tính được bảng lương. Vui lòng thử lại.'))
    },
  })

  // Lock payroll
  const lockMutation = useMutation({
    mutationFn: (periodId: number) => lockPayroll(client, periodId),
    onSuccess: (data) => {
      toast.success(`Đã chốt và khóa kỳ lương ${data.code}.`)
      queryClient.invalidateQueries({ queryKey: ['payroll-periods'] })
      queryClient.invalidateQueries({ queryKey: ['payroll-period-detail', data.id] })
    },
    onError: (err: unknown) => {
      toast.error(getPayrollErrorMessage(err, 'Chưa chốt được kỳ lương. Vui lòng thử lại.'))
    },
  })

  // Create adjustment
  const adjustmentMutation = useMutation({
    mutationFn: () => {
      if (!activePeriodId) throw new Error('Chưa chọn kỳ lương.')
      return createPayrollAdjustment(client, activePeriodId, adjustmentDraft)
    },
    onSuccess: () => {
      toast.success('Đã lưu phiếu điều chỉnh lương.')
      setShowAdjustmentModal(false)
      onEditorStateChange?.(false)
      setAdjustmentDraft(emptyAdjustment)
      queryClient.invalidateQueries({ queryKey: ['payroll-periods'] })
      queryClient.invalidateQueries({ queryKey: ['payroll-period-detail', activePeriodId] })
    },
    onError: (err: unknown) => {
      setAdjError(getPayrollErrorMessage(err, 'Chưa tạo được phiếu điều chỉnh. Vui lòng thử lại.'))
    },
  })

  // Review adjustment (approve / reject)
  const reviewMutation = useMutation({
    mutationFn: ({ id, approve }: { id: number; approve: boolean }) =>
      reviewPayrollAdjustment(client, id, approve),
    onSuccess: (_, vars) => {
      toast.success(vars.approve ? 'Đã duyệt phiếu điều chỉnh.' : 'Đã từ chối phiếu điều chỉnh.')
      queryClient.invalidateQueries({ queryKey: ['payroll-periods'] })
      queryClient.invalidateQueries({ queryKey: ['payroll-period-detail', activePeriodId] })
    },
    onError: (err: unknown) => {
      toast.error(getPayrollErrorMessage(err, 'Chưa duyệt được phiếu điều chỉnh. Vui lòng thử lại.'))
    },
  })

  const currentPeriod = periodDetailQuery.data?.period
  const currentLines = periodDetailQuery.data?.lines ?? []
  const currentAdjustments = periodDetailQuery.data?.adjustments ?? []

  const stats = useMemo(() => {
    if (!currentPeriod) {
      return { base: 0, commission: 0, net: 0 }
    }
    return {
      base: currentPeriod.total_base_salary,
      commission: currentPeriod.total_commission,
      net: currentPeriod.total_net_pay,
    }
  }, [currentPeriod])

  const payrollBusy = generateMutation.isPending || lockMutation.isPending || adjustmentMutation.isPending || reviewMutation.isPending

  const calculatePayroll = () => {
    try {
      validatePayrollRange(periodStart, periodEnd)
      setRangeError(undefined)
      generateMutation.mutate()
    } catch (error) {
      setRangeError(getPayrollErrorMessage(error))
    }
  }

  const openAdjustmentEditor = () => {
    setAdjustmentDraft(emptyAdjustment)
    setAdjError(undefined)
    setShowAdjustmentModal(true)
    onEditorStateChange?.(true)
  }

  const closeAdjustmentEditor = () => {
    setShowAdjustmentModal(false)
    onEditorStateChange?.(false)
  }

  return (
    <div className="payroll-page">
      {/* Top Heading */}
      <div className="payroll-heading">
        <div>
          <h1>Bảng lương & hoa hồng</h1>
          <p>Tính lương theo kỳ, theo dõi hoa hồng doanh số và chốt kỳ kế toán.</p>
        </div>
        <div className="payroll-heading-actions">
          <div className="payroll-date-group">
            <span>Từ:</span>
            <DateInput
              label="Ngày bắt đầu kỳ lương"
              required
              value={periodStart}
              max={periodEnd || undefined}
              disabled={payrollBusy}
              onChange={(value) => {
                setPeriodStart(value)
                setSelectedPeriodId(null)
                setRangeError(undefined)
              }}
            />
            <span>Đến:</span>
            <DateInput
              label="Ngày kết thúc kỳ lương"
              required
              value={periodEnd}
              min={periodStart || undefined}
              disabled={payrollBusy}
              onChange={(value) => {
                setPeriodEnd(value)
                setSelectedPeriodId(null)
                setRangeError(undefined)
              }}
            />
          </div>

          {currentPeriod && (
            <button
              type="button"
              className="secondary-button"
              onClick={() => exportPayrollCsv(currentPeriod, currentLines)}
            >
              <Download size={15} /> Xuất CSV
            </button>
          )}

          <button
            type="button"
            className="primary-button"
            disabled={payrollBusy || periodsQuery.isPending || periodDetailQuery.isFetching || currentPeriod?.status === 'locked'}
            onClick={calculatePayroll}
          >
            <Calculator size={15} />
            {generateMutation.isPending ? 'Đang tính toán…' : 'Tính bảng lương'}
          </button>
        </div>
      </div>

      {rangeError && <div className="error-box" role="alert">{rangeError}</div>}
      {periodsQuery.isError && (
        <div className="error-box" role="alert">
          {getPayrollErrorMessage(periodsQuery.error, 'Chưa tải được danh sách kỳ lương.')}
          <button type="button" onClick={() => void periodsQuery.refetch()}>Thử lại</button>
        </div>
      )}
      {periodDetailQuery.isError && (
        <div className="error-box" role="alert">
          {getPayrollErrorMessage(periodDetailQuery.error, 'Chưa tải được chi tiết kỳ lương.')}
          <button type="button" onClick={() => void periodDetailQuery.refetch()}>Thử lại</button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="payroll-summary-grid">
        <div className="payroll-kpi-card">
          <span className="payroll-kpi-label">Tổng quỹ lương cứng</span>
          <span className="payroll-kpi-value">{formatVnd(stats.base)}</span>
          <span className="payroll-kpi-note">Theo nhân sự đang hoạt động</span>
        </div>
        <div className="payroll-kpi-card">
          <span className="payroll-kpi-label">Hoa hồng dịch vụ & bán gói</span>
          <span className="payroll-kpi-value">{formatVnd(stats.commission)}</span>
          <span className="payroll-kpi-note">Hoa hồng phát sinh trong kỳ</span>
        </div>
        <div className="payroll-kpi-card">
          <span className="payroll-kpi-label">Thực lĩnh dự kiến</span>
          <span className="payroll-kpi-value" style={{ color: '#0f766e' }}>
            {formatVnd(stats.net)}
          </span>
          <span className="payroll-kpi-note">
            {currentPeriod?.status === 'locked' ? 'Đã khóa dữ liệu' : 'Bản nháp có thể tính lại'}
          </span>
        </div>
      </div>

      {/* Current Period Detail Panel */}
      <section className="payroll-panel">
        <div className="payroll-panel-header">
          <div>
            <h2>Chi tiết bảng lương</h2>
            <p>
              {currentPeriod
                ? `${currentPeriod.code} · ${currentPeriod.period_start} đến ${currentPeriod.period_end}`
                : 'Chưa có dữ liệu cho kỳ này. Bấm "Tính bảng lương" để tạo kỳ.'}
            </p>
          </div>
          <div className="payroll-panel-actions">
            {currentPeriod && (
              <>
                <span className={`payroll-badge ${currentPeriod.status}`}>
                  {currentPeriod.status === 'locked' ? 'Đã chốt kỳ lương' : 'Bản nháp'}
                </span>

                {currentPeriod.status !== 'locked' && (
                  <>
                    <button
                      type="button"
                      className="secondary-button"
                      disabled={payrollBusy || periodDetailQuery.isFetching}
                      onClick={openAdjustmentEditor}
                    >
                      <Plus size={14} /> Thêm phiếu điều chỉnh
                    </button>
                    <button
                      type="button"
                      className="primary-button"
                      disabled={payrollBusy || periodDetailQuery.isFetching}
                      onClick={() => {
                        if (
                          window.confirm(
                            `Bạn có chắc chắn muốn chốt và khóa kỳ lương ${currentPeriod.code}? Sau khi khóa sẽ không thể sửa đổi.`
                          )
                        ) {
                          lockMutation.mutate(currentPeriod.id)
                        }
                      }}
                    >
                      <Lock size={14} /> Chốt & khóa
                    </button>
                  </>
                )}
              </>
            )}
          </div>
        </div>

        {periodsQuery.isPending || (activePeriodId && periodDetailQuery.isPending) ? (
          <div className="payroll-empty" role="status">Đang tải bảng lương…</div>
        ) : periodsQuery.isError || periodDetailQuery.isError ? (
          <div className="payroll-empty">Dữ liệu bảng lương chưa tải được. Vui lòng thử lại.</div>
        ) : currentPeriod ? (
          <div className="payroll-table-scroll">
            <table className="payroll-table">
              <thead>
                <tr>
                  <th>Mã NV</th>
                  <th>Tên nhân viên</th>
                  <th>Lương cứng</th>
                  <th>Ngày công</th>
                  <th>Hoa hồng</th>
                  <th>Thưởng / Tips</th>
                  <th>Khấu trừ</th>
                  <th>Thực lĩnh</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                {currentLines.length === 0 ? (
                  <tr>
                    <td colSpan={9} style={{ textAlign: 'center', padding: '30px' }}>
                      Chưa có dòng lương nào trong kỳ.
                    </td>
                  </tr>
                ) : (
                  currentLines.map((line) => (
                    <tr key={line.id}>
                      <td>
                        <code>{line.employee?.code || '—'}</code>
                      </td>
                      <td>
                        <strong>{line.employee?.name || '—'}</strong>
                        <div style={{ color: '#888', fontSize: '11px' }}>
                          {line.employee?.job_title}
                        </div>
                      </td>
                      <td className="payroll-money">{formatVnd(line.base_salary)}</td>
                      <td>{line.work_days} công</td>
                      <td className="payroll-money">{formatVnd(line.commission_amount)}</td>
                      <td className="payroll-money" style={{ color: '#047857' }}>
                        {formatVnd(line.tips)}
                      </td>
                      <td className="payroll-money" style={{ color: '#b91c1c' }}>
                        {formatVnd(line.deductions)}
                      </td>
                      <td className="payroll-money bold">{formatVnd(line.net_pay)}</td>
                      <td>
                        <span className={`payroll-badge ${line.status}`}>
                          {line.status === 'locked' ? 'Đã khóa' : 'Bản nháp'}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="payroll-empty">
            <FileSpreadsheet size={32} />
            <b>Chưa có dữ liệu kỳ lương</b>
            <span>Chọn khoảng ngày ở trên và bấm "Tính bảng lương" để lập bảng lương.</span>
          </div>
        )}
      </section>

      {/* Adjustments Section */}
      {currentPeriod && (
        <section className="payroll-panel">
          <div className="payroll-panel-header">
            <div>
              <h2>Phiếu điều chỉnh lương (Thưởng / Phạt / Tips)</h2>
              <p>Phiếu cần được duyệt hoặc từ chối trước khi chốt kỳ lương.</p>
            </div>
          </div>

          <div className="payroll-table-scroll">
            <table className="payroll-table">
              <thead>
                <tr>
                  <th>Mã phiếu</th>
                  <th>Nhân viên</th>
                  <th>Loại</th>
                  <th>Số tiền</th>
                  <th>Lý do</th>
                  <th>Trạng thái</th>
                  <th style={{ textAlign: 'right' }}>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                {currentAdjustments.length === 0 ? (
                  <tr>
                    <td colSpan={7} style={{ textAlign: 'center', padding: '24px' }}>
                      Chưa có phiếu điều chỉnh nào trong kỳ.
                    </td>
                  </tr>
                ) : (
                  currentAdjustments.map((adj) => (
                    <tr key={adj.id}>
                      <td>
                        <code>{adj.code}</code>
                      </td>
                      <td>
                        <strong>{adj.employee?.name || `NV #${adj.employee_id}`}</strong>
                      </td>
                      <td>
                        <span className={`payroll-badge ${adj.type === 'bonus' ? 'approved' : 'rejected'}`}>
                          {adj.type === 'bonus' ? 'Thưởng / tips' : 'Khấu trừ'}
                        </span>
                      </td>
                      <td className="payroll-money bold">{formatVnd(adj.amount)}</td>
                      <td>{adj.reason}</td>
                      <td>
                        <span className={`payroll-badge ${adj.status}`}>
                          {adj.status === 'pending'
                            ? 'Chờ duyệt'
                            : adj.status === 'approved'
                            ? 'Đã duyệt'
                            : 'Từ chối'}
                        </span>
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {adj.status === 'pending' && currentPeriod.status !== 'locked' ? (
                          <div style={{ display: 'inline-flex', gap: '6px' }}>
                            <button
                              type="button"
                              className="secondary-button"
                              style={{ padding: '0 8px', height: '30px' }}
                              disabled={payrollBusy}
                              title="Duyệt"
                              onClick={() =>
                                reviewMutation.mutate({ id: adj.id, approve: true })
                              }
                            >
                              <Check size={14} style={{ color: '#047857' }} /> Duyệt
                            </button>
                            <button
                              type="button"
                              className="secondary-button"
                              style={{ padding: '0 8px', height: '30px' }}
                              disabled={payrollBusy}
                              title="Từ chối"
                              onClick={() =>
                                reviewMutation.mutate({ id: adj.id, approve: false })
                              }
                            >
                              <X size={14} style={{ color: '#b91c1c' }} /> Từ chối
                            </button>
                          </div>
                        ) : (
                          <span style={{ color: '#888', fontSize: '11px' }}>Đã xử lý</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* History Periods List */}
      <section className="payroll-panel">
        <div className="payroll-panel-header">
          <div>
            <h2>Lịch sử các kỳ lương</h2>
            <p>Danh sách các kỳ đã lập hoặc đã chốt.</p>
          </div>
        </div>

        <div className="payroll-table-scroll">
          <table className="payroll-table">
            <thead>
              <tr>
                <th>Mã kỳ</th>
                <th>Thời gian</th>
                <th>Tổng quỹ lương cứng</th>
                <th>Hoa hồng</th>
                <th>Thực lĩnh</th>
                <th>Trạng thái</th>
                <th style={{ textAlign: 'right' }}>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              {periodsQuery.isPending ? (
                <tr><td colSpan={7} role="status">Đang tải danh sách kỳ lương…</td></tr>
              ) : periodsQuery.isError ? (
                <tr><td colSpan={7}>Chưa tải được danh sách kỳ lương.</td></tr>
              ) : (periodsQuery.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '24px' }}>
                    Chưa có kỳ lương nào được tạo.
                  </td>
                </tr>
              ) : (
                (periodsQuery.data ?? []).map((p) => (
                  <tr key={p.id}>
                    <td>
                      <code>{p.code}</code>
                    </td>
                    <td>
                      {p.period_start} đến {p.period_end}
                    </td>
                    <td className="payroll-money">{formatVnd(p.total_base_salary)}</td>
                    <td className="payroll-money">{formatVnd(p.total_commission)}</td>
                    <td className="payroll-money bold">{formatVnd(p.total_net_pay)}</td>
                    <td>
                      <span className={`payroll-badge ${p.status}`}>
                        {p.status === 'locked' ? 'Đã chốt' : 'Bản nháp'}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <button
                        type="button"
                        className="secondary-button"
                        style={{ height: '30px' }}
                        disabled={payrollBusy}
                        onClick={() => {
                          setRangeError(undefined)
                          setSelectedPeriodId(p.id)
                          setPeriodStart(p.period_start)
                          setPeriodEnd(p.period_end)
                        }}
                      >
                        Xem chi tiết
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {/* Modal create adjustment */}
      {showAdjustmentModal && (
        <Popup
          title="Tạo phiếu điều chỉnh lương"
          description="Thêm khoản thưởng, tips ngoài hóa đơn hoặc khoản khấu trừ trong kỳ lương."
          busy={adjustmentMutation.isPending}
          error={adjError}
          onClose={closeAdjustmentEditor}
        >
          <form
            onSubmit={(e) => {
              e.preventDefault()
              adjustmentMutation.mutate()
            }}
          >
            <div className="modal-body">
              <label>
                Nhân viên <b>*</b>
                <select
                  required
                  value={adjustmentDraft.employee_id}
                  onChange={(e) =>
                    setAdjustmentDraft((prev) => ({ ...prev, employee_id: e.target.value }))
                  }
                >
                  <option value="">-- Chọn nhân viên --</option>
                  {(employeesQuery.data ?? []).filter((emp) => currentLines.some((line) => line.employee_id === emp.id)).map((emp) => (
                    <option key={emp.id} value={emp.id}>
                      {emp.code} · {emp.name} ({emp.job_title})
                    </option>
                  ))}
                </select>
              </label>

              {employeesQuery.isPending && <p role="status">Đang tải nhân viên…</p>}
              {employeesQuery.isError && <p className="form-error" role="alert">Chưa tải được nhân viên. Đóng và mở lại để thử lại.</p>}

              <div className="form-grid">
                <label>
                  Loại điều chỉnh <b>*</b>
                  <select
                    value={adjustmentDraft.type}
                    onChange={(e) =>
                      setAdjustmentDraft((prev) => ({
                        ...prev,
                        type: e.target.value as 'bonus' | 'deduction',
                      }))
                    }
                  >
                    <option value="bonus">Thưởng / Tips (+)</option>
                    <option value="deduction">Khấu trừ (-)</option>
                  </select>
                </label>

                <label>
                  Số tiền (₫) <b>*</b>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    required
                    placeholder="VD: 200000"
                    value={adjustmentDraft.amount}
                    onChange={(e) =>
                      setAdjustmentDraft((prev) => ({ ...prev, amount: e.target.value }))
                    }
                  />
                </label>
              </div>

              <label>
                Lý do điều chỉnh <b>*</b>
                <textarea
                  required
                  placeholder="Lý do thưởng nóng, tiền tip khách tip riêng hoặc phạt vi phạm nội quy..."
                  value={adjustmentDraft.reason}
                  onChange={(e) =>
                    setAdjustmentDraft((prev) => ({ ...prev, reason: e.target.value }))
                  }
                />
              </label>
            </div>

            <footer>
              <button
                type="button"
                className="secondary-button"
                onClick={closeAdjustmentEditor}
              >
                Hủy
              </button>
              <button
                type="submit"
                className="primary-button"
                disabled={adjustmentMutation.isPending}
              >
                {adjustmentMutation.isPending ? 'Đang lưu…' : 'Lưu phiếu'}
              </button>
            </footer>
          </form>
        </Popup>
      )}
    </div>
  )
}
