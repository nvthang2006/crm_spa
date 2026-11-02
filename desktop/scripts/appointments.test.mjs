import assert from 'node:assert/strict'
import { test } from 'node:test'
import { loadAppointmentPackages, parseAppointmentDate, saveAppointment } from '../src/lib/appointments.ts'

test('ngày Việt Nam được kiểm tra theo ngày thực tế và năm nhuận', () => {
  assert.equal(parseAppointmentDate('29/02/2028'), '2028-02-29')
  for (const value of ['29/02/2026', '31/04/2026', '2026-10-30', '1/1/2026', '']) {
    assert.equal(parseAppointmentDate(value), null)
  }
})

test('lọc thẻ chưa đến ngày bắt đầu và hết buổi, truy vấn giữ điều kiện hết hạn', async () => {
  const calls = []
  const query = {}
  for (const name of ['select', 'eq', 'lte', 'or', 'order']) {
    query[name] = (...args) => { calls.push([name, ...args]); return query }
  }
  query.then = (resolve) => resolve({ data: [
    { id: 1, starts_on: null, sessions_used: 0, sessions_total: 3 },
    { id: 2, starts_on: '2099-01-02', sessions_used: 0, sessions_total: 3 },
    { id: 3, starts_on: '2099-01-01', sessions_used: 3, sessions_total: 3 },
  ], error: null })
  const rows = await loadAppointmentPackages({ from: () => query }, 4, 5, '2099-01-01')
  assert.deepEqual(rows.map((item) => item.id), [1])
  assert.deepEqual(calls.filter(([name]) => name === 'or'), [['or', 'expires_on.is.null,expires_on.gte.2099-01-01']])
  assert.deepEqual(calls.filter(([name]) => name === 'order').map(([, key]) => key), ['expires_on', 'purchased_on', 'id'])
})

test('lưu lịch gửi giờ null và giữ giờ cũ khi sửa', async () => {
  const calls = []
  const client = { rpc: async (name, payload) => { calls.push({ name, payload }); return { data: { id: 1 }, error: null } } }
  const draft = { id: null, customerId: 1, offeringId: 2, primaryEmployeeId: null, supportEmployeeId: null, resourceId: null, date: '2026-10-30', startTime: null, endTime: null, paymentType: 'service', packageId: null, source: 'booked', notes: '', overrideReason: null }
  await saveAppointment(client, draft)
  await saveAppointment(client, { ...draft, id: 1, startTime: '09:00', endTime: '10:00' })
  assert.equal(calls[0].payload.p_start_time, null)
  assert.equal(calls[0].payload.p_end_time, null)
  assert.equal(calls[1].payload.p_start_time, '09:00')
  assert.equal(calls[1].payload.p_end_time, '10:00')
})
