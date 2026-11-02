import assert from 'node:assert/strict'
import { webcrypto } from 'node:crypto'
import { test } from 'node:test'
import { createInvoice, InvoiceRequestConflictError, payInvoice } from '../src/lib/invoices.ts'

const userId = '8b1e40c2-5e15-4d54-9f9b-6d234d4339a8'

class MemoryStorage {
  values = new Map()

  getItem(key) { return this.values.get(key) ?? null }
  setItem(key, value) { this.values.set(key, value) }
  removeItem(key) { this.values.delete(key) }
}

function createClient(results) {
  const calls = []
  return {
    calls,
    auth: { getSession: async () => ({ data: { session: { user: { id: userId } } }, error: null }) },
    rpc: async (name, payload) => {
      calls.push({ name, payload })
      return results.shift()
    },
  }
}

async function withBrowserStorage(storage, callback) {
  const previousWindow = globalThis.window
  globalThis.window = { crypto: webcrypto, localStorage: storage }
  try {
    return await callback()
  } finally {
    globalThis.window = previousWindow
  }
}

test('retries after an ambiguous timeout with the same request ID and clears it after success', async () => {
  await withBrowserStorage(new MemoryStorage(), async () => {
    const client = createClient([
      { data: null, error: { message: 'network timeout' } },
      { data: { invoice: { id: 41 } }, error: null },
    ])
    const values = { customerId: 1, offeringId: 2, appointmentId: null, employeeId: null, discountAmount: 1000 }

    await assert.rejects(createInvoice(client, values), (error) => error?.message === 'network timeout')
    const firstRequestId = client.calls[0].payload.p_request_id
    await createInvoice(client, values)

    assert.equal(client.calls[1].payload.p_request_id, firstRequestId)
    assert.equal(client.calls[1].name, 'create_invoice_idempotent')
    assert.equal(globalThis.window.localStorage.values.size, 0)
  })
})

test('keeps the same request ID if a user edits the form after an ambiguous timeout', async () => {
  await withBrowserStorage(new MemoryStorage(), async () => {
    const client = createClient([
      { data: null, error: { message: 'network timeout' } },
      { data: null, error: { code: '22023', message: 'Mã yêu cầu đã được dùng với nội dung khác.' } },
    ])

    await assert.rejects(
      createInvoice(client, { customerId: 1, offeringId: 2, appointmentId: null, employeeId: null, discountAmount: 1000 }),
      (error) => error?.message === 'network timeout',
    )
    const firstRequestId = client.calls[0].payload.p_request_id
    await assert.rejects(
      createInvoice(client, { customerId: 1, offeringId: 2, appointmentId: null, employeeId: null, discountAmount: 2000 }),
      (error) => error instanceof InvoiceRequestConflictError,
    )

    assert.equal(client.calls[1].payload.p_request_id, firstRequestId)
    assert.equal(globalThis.window.localStorage.values.size, 0)
  })
})

test('normalizes trimmed payment references before selecting the pending request ID', async () => {
  await withBrowserStorage(new MemoryStorage(), async () => {
    const client = createClient([
      { data: null, error: { message: 'network timeout' } },
      { data: { payment: { id: 51 } }, error: null },
    ])

    await assert.rejects(payInvoice(client, 7, 'vietqr', '  TX-900  '), (error) => error?.message === 'network timeout')
    const firstRequestId = client.calls[0].payload.p_request_id
    await payInvoice(client, 7, 'vietqr', 'TX-900')

    assert.equal(client.calls[1].payload.p_request_id, firstRequestId)
    assert.equal(client.calls[1].payload.p_reference, 'TX-900')
  })
})

test('refuses to send a financial request if its recovery ID cannot be persisted locally', async () => {
  await withBrowserStorage({
    getItem: () => null,
    setItem: () => { throw new Error('storage unavailable') },
    removeItem: () => {},
  }, async () => {
    const client = createClient([{ data: { invoice: { id: 41 } }, error: null }])

    await assert.rejects(
      createInvoice(client, { customerId: 1, offeringId: 2, appointmentId: null, employeeId: null, discountAmount: 1000 }),
      /storage unavailable/,
    )
    assert.equal(client.calls.length, 0)
  })
})
