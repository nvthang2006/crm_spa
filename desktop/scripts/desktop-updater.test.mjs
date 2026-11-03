import assert from 'node:assert/strict'
import { test } from 'node:test'
import { MutationObserver, QueryClient } from '@tanstack/react-query'
import { DesktopUpdater } from '../src/lib/desktopUpdater.ts'
import { hasUnsavedShiftEntries } from '../src/lib/dailyRevenue.ts'

function artifact(version = '0.1.1') {
  const calls = { download: 0, install: 0, close: 0 }
  return {
    version, calls,
    async download() { calls.download++ },
    async install() { calls.install++ },
    async close() { calls.close++ },
  }
}

function deferred() {
  let resolve
  let reject
  const promise = new Promise((done, fail) => { resolve = done; reject = fail })
  return { promise, resolve, reject }
}

test('keeps the downloaded artifact when checking the same version again', async () => {
  const original = artifact()
  const refreshed = artifact()
  const results = [original, refreshed]
  const updater = new DesktopUpdater(async () => results.shift())
  await updater.check()
  await updater.download()
  updater.dismiss()
  await updater.check()
  assert.equal(updater.getSnapshot().update, original)
  assert.equal(updater.getSnapshot().downloaded, true)
  assert.equal(updater.getSnapshot().dismissed, false)
  assert.equal(refreshed.calls.close, 1)
  assert.equal(original.calls.close, 0)
  await updater.install(() => false)
  assert.equal(original.calls.install, 1)
  assert.equal(refreshed.calls.install, 0)
})

test('serializes automatic checks, manual checks, downloads and installation', async () => {
  const checking = deferred()
  const downloading = deferred()
  const update = artifact()
  update.download = () => downloading.promise
  let checks = 0
  const updater = new DesktopUpdater(() => { checks++; return checking.promise })
  const check = updater.check()
  assert.equal(await updater.check(), undefined)
  assert.equal(await updater.download(), false)
  checking.resolve(update)
  await check
  const download = updater.download()
  assert.equal(await updater.check(), undefined)
  assert.equal(await updater.download(), false)
  assert.equal(await updater.install(() => false), false)
  assert.equal(checks, 1)
  downloading.resolve()
  await download
  assert.equal(updater.getSnapshot().downloaded, true)
})

test('blocks installation before download and while an editor or operation blocks it', async () => {
  const update = artifact()
  const installing = deferred()
  update.install = async () => { update.calls.install++; await installing.promise }
  const updater = new DesktopUpdater(async () => update)
  await updater.check()
  assert.equal(await updater.install(() => false), false)
  await updater.download()
  assert.equal(await updater.install(() => true), false)
  assert.equal(update.calls.install, 0)
  const install = updater.install(() => false)
  assert.equal(await updater.install(() => false), false)
  assert.equal(update.calls.install, 1)
  installing.resolve()
  await install
})

test('blocks installation while a save continues after its screen unsubscribes', async () => {
  const client = new QueryClient()
  const saving = deferred()
  const observer = new MutationObserver(client, { mutationFn: () => saving.promise })
  const unsubscribe = observer.subscribe(() => {})
  const pending = observer.mutate()
  unsubscribe()
  const update = artifact()
  const updater = new DesktopUpdater(async () => update)
  await updater.check()
  await updater.download()
  assert.equal(await updater.install(() => client.isMutating() > 0), false)
  assert.equal(update.calls.install, 0)
  saving.resolve()
  await pending
  assert.equal(await updater.install(() => client.isMutating() > 0), true)
  assert.equal(update.calls.install, 1)
  client.clear()
})

test('allows retry after a failed check or download without losing the artifact', async () => {
  const update = artifact()
  let checks = 0
  const updater = new DesktopUpdater(async () => {
    if (++checks === 1) throw new Error('offline')
    return update
  })
  await assert.rejects(updater.check(), /offline/)
  assert.equal(updater.getSnapshot().busy, false)
  await updater.check()
  update.download = async () => { throw new Error('bad signature') }
  await assert.rejects(updater.download(), /bad signature/)
  assert.equal(updater.getSnapshot().busy, false)
  assert.equal(updater.getSnapshot().downloaded, false)
  assert.equal(await updater.install(() => false), false)
  update.download = async () => {}
  await updater.download()
  assert.equal(updater.getSnapshot().downloaded, true)
})

test('releases the previous artifact and requires download when a new version appears', async () => {
  const previous = artifact()
  const next = artifact('0.1.2')
  const results = [previous, next]
  const updater = new DesktopUpdater(async () => results.shift())
  await updater.check()
  await updater.download()
  await updater.check()
  assert.equal(previous.calls.close, 1)
  assert.equal(updater.getSnapshot().downloaded, false)
  assert.equal(await updater.install(() => false), false)
})

test('ignores stale check results after logout or effect cleanup', async () => {
  const oldCheck = deferred()
  const stale = artifact()
  const current = artifact('0.1.2')
  let checks = 0
  const updater = new DesktopUpdater(() => ++checks === 1 ? oldCheck.promise : Promise.resolve(current))
  const pending = updater.check()
  updater.reset()
  await updater.check()
  oldCheck.resolve(stale)
  await pending
  assert.equal(stale.calls.close, 1)
  assert.equal(updater.getSnapshot().update, current)
  assert.equal(updater.getSnapshot().busy, false)
})

test('does not restore a downloaded artifact after logout', async () => {
  const download = deferred()
  const update = artifact()
  update.download = () => download.promise
  const updater = new DesktopUpdater(async () => update)
  await updater.check()
  const pending = updater.download()
  updater.reset()
  download.resolve()
  assert.equal(await pending, false)
  assert.equal(updater.getSnapshot().update, null)
  assert.equal(updater.getSnapshot().downloaded, false)
  assert.equal(update.calls.close, 2)
})

test('keeps the downloaded update available to retry after an installation failure', async () => {
  const update = artifact()
  update.install = async () => { throw new Error('installer failed') }
  const updater = new DesktopUpdater(async () => update)
  await updater.check()
  await updater.download()
  await assert.rejects(updater.install(() => false), /installer failed/)
  assert.equal(updater.getSnapshot().busy, false)
  assert.equal(updater.getSnapshot().downloaded, true)
})

const blankRow = {
  tempId: 'draft-1', classification: 'retail', customerId: '', offeringId: '', technicianId: '',
  collected: 0, paymentMethod: 'cash', notes: '', commissionType: 'percentage', commissionValue: '',
}

test('allows updates for a blank shift and after all shifts have been saved', () => {
  assert.equal(hasUnsavedShiftEntries([blankRow]), false)
  assert.equal(hasUnsavedShiftEntries([]), false)
})

test('detects each editable field and additional unsaved shift rows', () => {
  const changes = {
    classification: 'package', customerId: 1, offeringId: 2, technicianId: 3,
    collected: 500000, paymentMethod: 'vietqr', notes: 'Ghi chú ca làm',
    commissionType: 'fixed', commissionValue: '0',
  }
  for (const [field, value] of Object.entries(changes)) {
    assert.equal(hasUnsavedShiftEntries([{ ...blankRow, [field]: value }]), true, field)
  }
  assert.equal(hasUnsavedShiftEntries([blankRow, { ...blankRow, tempId: 'draft-2' }]), true)
})
