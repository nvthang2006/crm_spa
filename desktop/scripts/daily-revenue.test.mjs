import assert from 'node:assert/strict'
import { test } from 'node:test'
import { commissionBase, previewShiftCommission, recordDailyShift, recordedShiftClassification, validateShiftEntry } from '../src/lib/dailyRevenue.ts'

const workDate = '2026-11-02'
const offering = { id: 2, price: 5000000, sessions_total: 10 }
const row = {
  tempId: 'd69ba38b-c535-48dc-b6cc-214aa300ef07', classification: 'retail', customerId: 1,
  offeringId: 2, technicianId: 3, collected: 500000, paymentMethod: 'cash', notes: '',
  commissionType: 'percentage', commissionValue: '',
}
const rule = {
  id: 1, offering_id: 2, beneficiary_type: 'primary_technician', beneficiary_employee_id: null,
  basis: 'completion', calculation_type: 'percentage', value: 10, starts_on: '2026-01-01',
  ends_on: null, is_active: true,
}

test('uses the latest effective personal rule and preserves independent recipient commissions', () => {
  const rules = [rule,
    { ...rule, id: 2, beneficiary_type: 'performer_employee', beneficiary_employee_id: 3, value: 12 },
    { ...rule, id: 3, beneficiary_type: 'performer_employee', beneficiary_employee_id: 3, value: 15, starts_on: workDate },
    { ...rule, id: 4, beneficiary_type: 'specific_employee', beneficiary_employee_id: 4, calculation_type: 'fixed', value: 20000 },
    { ...rule, id: 5, beneficiary_type: 'performer_employee', beneficiary_employee_id: 3, value: 99, starts_on: '2026-11-03' },
    { ...rule, id: 6, value: 99, ends_on: '2026-11-01' },
    { ...rule, id: 7, beneficiary_type: 'support_technician', value: 99 },
  ]
  assert.equal(previewShiftCommission(row, offering, rules, workDate), 95000)
  assert.equal(previewShiftCommission({ ...row, technicianId: 7 }, offering, rules, workDate), 70000)
})

test('a local override replaces performer commission and supports explicit zero', () => {
  const rules = [rule, { ...rule, id: 2, beneficiary_type: 'specific_employee', beneficiary_employee_id: 4, calculation_type: 'fixed', value: 20000 }]
  assert.equal(previewShiftCommission({ ...row, commissionValue: '5' }, offering, rules, workDate), 45000)
  assert.equal(previewShiftCommission({ ...row, commissionType: 'fixed', commissionValue: '80000' }, offering, rules, workDate), 100000)
  assert.equal(previewShiftCommission({ ...row, commissionValue: '0' }, offering, rules, workDate), 20000)
  assert.equal(previewShiftCommission({ ...row, commissionValue: ' ' }, offering, rules, workDate), 70000)
})

test('uses the per-session list price for a prepaid package with no collection', () => {
  const packageRow = { ...row, classification: 'package', collected: 0 }
  assert.equal(commissionBase(packageRow, offering), 500000)
  assert.equal(previewShiftCommission(packageRow, offering, [rule], workDate), 50000)
  assert.equal(commissionBase(packageRow, { ...offering, sessions_total: 0 }), 5000000)
  assert.equal(commissionBase({ ...packageRow, collected: 90000 }, offering), 90000)
})

test('rejects negative, nonfinite, fractional money and out-of-range commission values', () => {
  assert.equal(validateShiftEntry(row), null)
  for (const value of [-1, 0.5, Number.NaN, Number.POSITIVE_INFINITY]) assert.ok(validateShiftEntry({ ...row, collected: value }))
  for (const value of ['-1', '100.01', '10.123', 'Infinity']) assert.ok(validateShiftEntry({ ...row, commissionValue: value }))
  for (const value of ['1.5', '10000000000']) assert.ok(validateShiftEntry({ ...row, commissionType: 'fixed', commissionValue: value }))
  assert.equal(validateShiftEntry({ ...row, commissionValue: '10.25' }), null)
  assert.equal(validateShiftEntry({ ...row, commissionType: 'fixed', commissionValue: '0' }), null)
})

test('sends one atomic shift RPC with the stable request ID on retry', async () => {
  const calls = []
  const client = { rpc: async (name, args) => {
    calls.push({ name, args })
    return calls.length === 1 ? { data: null, error: { message: 'timeout' } } : { data: { invoice: { id: 10, code: 'HD-10' }, commission_amount: 50000 }, error: null }
  } }
  const packageRow = { ...row, classification: 'package', collected: 0, commissionValue: '10' }
  await assert.rejects(recordDailyShift(client, packageRow, workDate, 9), (error) => error.message === 'timeout')
  await recordDailyShift(client, packageRow, workDate, 9)
  assert.equal(calls.length, 2)
  assert.deepEqual(calls[0], calls[1])
  assert.equal(calls[0].name, 'record_daily_shift')
  assert.equal(calls[0].args.p_request_id, row.tempId)
  assert.equal(calls[0].args.p_customer_package_id, 9)
  assert.equal(calls[0].args.p_work_date, workDate)
  assert.equal(calls[0].args.p_commission_type, 'percentage')
  assert.equal(calls[0].args.p_commission_value, 10)
})

test('leaves default rules untouched and never attaches a prepaid card to retail', async () => {
  let payload
  const client = { rpc: async (_name, args) => { payload = args; return { data: { invoice: { id: 11 } }, error: null } } }
  await recordDailyShift(client, row, workDate, 9)
  assert.equal(payload.p_commission_type, null)
  assert.equal(payload.p_commission_value, null)
  assert.equal(payload.p_customer_package_id, null)
})

test('does not send invalid data and translates RPC compatibility or replay errors', async () => {
  const client = { rpc: async () => assert.fail('Không được gọi RPC với dữ liệu không hợp lệ') }
  await assert.rejects(recordDailyShift(client, { ...row, commissionValue: '101' }, workDate, null), /0 đến 100/)
  await assert.rejects(recordDailyShift(client, row, '', null), /ngày làm việc/)
  for (const [code, message] of [['PGRST202', /chưa được cập nhật/], ['22023', /nội dung khác/]]) {
    await assert.rejects(recordDailyShift({ rpc: async () => ({ data: null, error: { code } }) }, row, workDate, null), message)
  }
})

test('classifies new records by explicit notes and keeps compatibility with old records', () => {
  assert.equal(recordedShiftClassification({ notes: '[Ca gói] Phụ thu', total_received: 50000, type: 'service' }), 'package')
  assert.equal(recordedShiftClassification({ notes: '[Ca lẻ] Miễn phí', total_received: 0, type: 'service' }), 'retail')
  assert.equal(recordedShiftClassification({ notes: null, total_received: 0, type: 'service' }), 'package')
})
