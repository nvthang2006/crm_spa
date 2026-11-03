import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createCommissionRuleDraft, saveCommissionRule, validateCommissionRuleDraft } from '../src/lib/team.ts'

function savedRule(overrides = {}) {
  return {
    id: 8, offering_id: 2, beneficiary_employee_id: 6, beneficiary_type: 'specific_employee',
    basis: 'payment', calculation_type: 'percentage', value: 12, starts_on: '2026-01-01',
    ends_on: null, is_active: true, notes: null, offering: null, beneficiary_employee: null,
    ...overrides,
  }
}

test('mức mới chọn nhân viên thực hiện và ghi nhận khi hoàn thành dịch vụ', async () => {
  const draft = { ...createCommissionRuleDraft(), offering_id: '2', beneficiary_employee_id: '6' }
  const calls = []
  const client = { rpc: async (name, payload) => { calls.push({ name, payload }); return { data: 10, error: null } } }
  assert.equal(await saveCommissionRule(client, draft, null), 10)
  assert.equal(calls[0].name, 'save_commission_rule')
  assert.equal(calls[0].payload.p_beneficiary_type, 'performer_employee')
  assert.equal(calls[0].payload.p_beneficiary_employee_id, 6)
  assert.equal(calls[0].payload.p_basis, 'completion')
})

test('sửa mức cũ giữ loại người nhận và thời điểm ghi nhận, không đổi ngầm sang người thực hiện', async () => {
  const calls = []
  const client = { rpc: async (name, payload) => { calls.push({ name, payload }); return { data: 8, error: null } } }
  for (const beneficiary_type of ['primary_technician', 'support_technician', 'specific_employee', 'performer_employee']) {
    const rule = savedRule({ beneficiary_type, beneficiary_employee_id: ['specific_employee', 'performer_employee'].includes(beneficiary_type) ? 6 : null })
    const draft = { ...createCommissionRuleDraft(rule), value: '15' }
    await saveCommissionRule(client, draft, rule.id)
    assert.equal(calls.at(-1).payload.p_beneficiary_type, beneficiary_type)
    assert.equal(calls.at(-1).payload.p_beneficiary_employee_id, rule.beneficiary_employee_id)
    assert.equal(calls.at(-1).payload.p_basis, 'payment')
    assert.equal(calls.at(-1).payload.p_value, 15)
  }
})

test('không gửi RPC khi thiếu người nhận hoặc mức mới còn cấu hình cũ', async () => {
  let called = false
  const client = { rpc: async () => { called = true; return { data: 8, error: null } } }
  await assert.rejects(saveCommissionRule(client, { ...createCommissionRuleDraft(), offering_id: '2' }, null), /chọn nhân viên/)
  await assert.rejects(saveCommissionRule(client, createCommissionRuleDraft(savedRule()), null), /Quy tắc mới/)
  assert.equal(called, false)
})

test('mức phần trăm, khoảng ngày và số tiền rỗng được kiểm tra trước khi lưu', () => {
  const draft = { ...createCommissionRuleDraft(), offering_id: '2', beneficiary_employee_id: '6', starts_on: '2026-11-02' }
  assert.match(validateCommissionRuleDraft({ ...draft, value: '101' }, null), /100%/)
  assert.match(validateCommissionRuleDraft({ ...draft, ends_on: '2026-11-01' }, null), /Ngày kết thúc/)
  assert.match(validateCommissionRuleDraft({ ...draft, value: '' }, null), /mức hoa hồng/)
  assert.equal(validateCommissionRuleDraft({ ...draft, value: '0' }, null), null)
})
