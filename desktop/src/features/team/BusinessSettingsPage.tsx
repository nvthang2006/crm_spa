import { Popup } from '../../Popup'
import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, Clock3, MapPin } from 'lucide-react'
import { toast } from 'sonner'
import type { AppSupabaseClient } from '../../lib/supabase'
import { loadBusinessSettings, saveBusinessSettings, type BusinessSettingsDraft } from '../../lib/team'
import './team.css'

const emptyDraft: BusinessSettingsDraft = { business_name: '', address: '', phone: '' }

export default function BusinessSettingsPage({ client, onEditorStateChange }: { client: AppSupabaseClient; onEditorStateChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const settings = useQuery({ queryKey: ['business-settings'], queryFn: () => loadBusinessSettings(client) })
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<BusinessSettingsDraft | null>(null)
  const currentDraft = draft ?? {
    business_name: settings.data?.business_name ?? emptyDraft.business_name,
    address: settings.data?.address ?? emptyDraft.address,
    phone: settings.data?.phone ?? emptyDraft.phone,
  }
  const save = useMutation({
    mutationFn: () => saveBusinessSettings(client, currentDraft),
    onSuccess: () => {
      toast.success('Đã cập nhật thông tin cơ sở.')
      setEditing(false)
      setDraft(null)
      void queryClient.invalidateQueries({ queryKey: ['business-settings'] })
    },
    onError: (error: Error) => toast.error(error.message || 'Không thể lưu thông tin cơ sở.'),
  })

  useEffect(() => {
    onEditorStateChange(editing || save.isPending)
    return () => onEditorStateChange(false)
  }, [editing, save.isPending, onEditorStateChange])

  const changed = Boolean(settings.data && (
    currentDraft.business_name.trim() !== settings.data.business_name || currentDraft.address.trim() !== settings.data.address || (currentDraft.phone.trim() || '') !== (settings.data.phone ?? '')
  ))

  function startEditing() {
    if (!settings.data) return
    setDraft({ business_name: settings.data.business_name, address: settings.data.address, phone: settings.data.phone ?? '' })
    setEditing(true)
  }
  function cancelEditing() {
    if (settings.data) {
      setDraft({ business_name: settings.data.business_name, address: settings.data.address, phone: settings.data.phone ?? '' })
    } else {
      setDraft(null)
    }
    setEditing(false)
  }
  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!currentDraft.business_name.trim()) {
      toast.error('Tên cơ sở không được để trống.')
      return
    }
    if (changed) save.mutate()
  }

  return <section className="business-settings-page">
    <header className="business-settings-heading"><div><h1>Cấu hình cơ sở</h1><p>Thông tin nhận diện dùng trong phần mềm và chứng từ.</p></div></header>
    {settings.isPending ? <div className="business-settings-state">Đang tải cấu hình cơ sở…</div> : settings.isError ? <div className="business-settings-state">Chưa tải được cấu hình. <button onClick={() => void settings.refetch()}>Thử lại</button></div> : settings.data && <section className="business-settings-sheet" aria-label="Thông tin cơ sở">
      <header><span className="business-settings-symbol"><Building2 size={18} /></span><div><h2>Thông tin cơ sở</h2><p>Chỉnh sửa hồ sơ cơ sở đang kết nối với dữ liệu Supabase.</p></div><span className="business-settings-badge"><i className="online-dot" /> Đã cấu hình</span></header>
      <dl className="business-settings-summary"><div><dt>Tên cơ sở</dt><dd>{settings.data.business_name}</dd></div><div><dt>Địa chỉ</dt><dd>{settings.data.address || 'Chưa cập nhật'}</dd></div><div><dt>Số điện thoại</dt><dd>{settings.data.phone || 'Chưa cập nhật'}</dd></div></dl>
      <div className="business-settings-actions"><button type="button" className="primary-button" onClick={startEditing}>Chỉnh sửa thông tin</button></div>
    </section>}
    {editing && <Popup title="Chỉnh sửa thông tin cơ sở" description="Thông tin nhận diện dùng trong phần mềm và chứng từ." busy={save.isPending} onClose={cancelEditing}>
      <form onSubmit={submit}>
        <label><span>Tên cơ sở <b>*</b></span><input autoFocus required maxLength={120} value={currentDraft.business_name} onChange={(event) => setDraft({ ...currentDraft, business_name: event.target.value })} /></label>
        <label>Địa chỉ<input maxLength={240} value={currentDraft.address} onChange={(event) => setDraft({ ...currentDraft, address: event.target.value })} /></label>
        <label>Số điện thoại<input type="tel" maxLength={32} value={currentDraft.phone} onChange={(event) => setDraft({ ...currentDraft, phone: event.target.value })} /></label>
        <p className="popup-note"><MapPin size={15} /> Thông tin mới không thay đổi chứng từ đã phát hành.</p>
        <p className="popup-note"><Clock3 size={15} /> Múi giờ Việt Nam (UTC+07:00).</p>
        {save.isError && <p className="popup-error" role="alert">{save.error.message || 'Không thể lưu thông tin cơ sở.'}</p>}
        <footer><button type="button" className="secondary-button" disabled={save.isPending} onClick={cancelEditing}>Hủy</button><button className="primary-button" disabled={save.isPending || !changed}>{save.isPending ? 'Đang lưu…' : 'Lưu thông tin'}</button></footer>
      </form>
    </Popup>}
  </section>
}
