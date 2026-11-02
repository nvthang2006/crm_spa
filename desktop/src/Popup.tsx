import { useEffect, useId, useRef, type ReactNode } from 'react'
import { X } from 'lucide-react'
import './forms.css'

export function Popup({ title, description, busy = false, onClose, children, error }: {
  title: string; description?: string; busy?: boolean; onClose: () => void; children: ReactNode; error?: string
}) {
  const dialog = useRef<HTMLDialogElement>(null)
  const titleId = useId()
  const descriptionId = useId()
  useEffect(() => {
    const element = dialog.current
    if (!element) return
    const previousFocus = document.activeElement
    element.showModal()
    element.querySelector<HTMLElement>('[autofocus], input:not([type="hidden"]), select, textarea')?.focus({ preventScroll: true })
    return () => {
      element.close()
      if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus({ preventScroll: true })
    }
  }, [])
  return <dialog className="app-popup" ref={dialog} aria-labelledby={titleId} aria-describedby={description ? descriptionId : undefined}
    onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}>
    <header className="app-popup-header"><div><h2 id={titleId}>{title}</h2>{description && <p id={descriptionId}>{description}</p>}</div><button type="button" className="icon-button" aria-label="Đóng popup" disabled={busy} onClick={onClose}><X size={19} /></button></header>
    {error && <p className="popup-error popup-header-error" role="alert">{error}</p>}
    {children}
  </dialog>
}
