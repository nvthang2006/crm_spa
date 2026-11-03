import assert from 'node:assert/strict'
import { test } from 'node:test'
import { getCurrentMonthRange, getPayrollErrorMessage, validatePayrollRange, generatePayroll, createPayrollAdjustment } from '../src/lib/payroll.ts'

test('kỳ tháng theo ngày Việt Nam, kể cả khi máy dùng múi giờ khác', () => {
  assert.deepEqual(getCurrentMonthRange(new Date('2026-10-31T18:00:00Z')), { start: '2026-11-01', end: '2026-11-30' })
  assert.deepEqual(getCurrentMonthRange(new Date('2028-02-01T00:00:00Z')), { start: '2028-02-01', end: '2028-02-29' })
})

test('chặn ngày thiếu, ngày không tồn tại và khoảng đảo trước khi gọi RPC', async () => {
  let called = false
  const client = { rpc: async () => { called = true; return { data: {}, error: null } } }
  for (const [start, end] of [['', '2026-11-01'], ['2026-02-30', '2026-03-01'], ['2026-11-02', '2026-11-01']]) {
    await assert.rejects(generatePayroll(client, start, end), /Vui lòng|Ngày kết thúc/)
  }
  assert.equal(called, false)
  assert.doesNotThrow(() => validatePayrollRange('2026-11-01', '2026-11-01'))
})

test('Việt hóa lỗi thiếu RPC, quyền, mạng và object PostgREST', () => {
  assert.match(getPayrollErrorMessage({ code: 'PGRST202', message: 'Could not find the function' }), /chưa cập nhật/)
  assert.match(getPayrollErrorMessage({ code: '42501', message: 'permission denied' }), /quyền quản lý/)
  assert.match(getPayrollErrorMessage(new Error('Failed to fetch')), /Không kết nối/)
  assert.equal(getPayrollErrorMessage({ code: 'P0001', message: 'Kỳ lương đã khóa, không thể tính lại.' }), 'Kỳ lương đã khóa, không thể tính lại.')
  assert.equal(getPayrollErrorMessage({ message: 'invalid SQL input' }, 'Vui lòng thử lại.'), 'Vui lòng thử lại.')
})

test('điều chỉnh lương chỉ gửi nhân viên và số tiền nguyên dương an toàn', async () => {
  const calls = []
  const client = { rpc: async (name, payload) => { calls.push({ name, payload }); return { data: { id: 1 }, error: null } } }
  const draft = { employee_id: '4', type: 'bonus', amount: '1000', reason: '  Thưởng ca tối  ' }
  for (const changes of [{ employee_id: '1.5' }, { amount: 'Infinity' }, { amount: '1.5' }, { amount: '0' }, { type: 'x' }, { reason: ' ' }]) {
    await assert.rejects(createPayrollAdjustment(client, 2, { ...draft, ...changes }))
  }
  assert.equal(calls.length, 0)
  await createPayrollAdjustment(client, 2, draft)
  assert.equal(calls[0].payload.p_reason, 'Thưởng ca tối')
  assert.equal(calls[0].payload.p_amount, 1000)
})
