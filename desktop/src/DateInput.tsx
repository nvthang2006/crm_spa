import { useEffect, useRef, useState } from 'react'
import { CalendarDays } from 'lucide-react'
import { parseAppointmentDate } from './lib/appointments'
import './forms.css'

function displayDate(value: string): string {
  return value ? value.split('-').reverse().join('/') : ''
}

export function DateInput({ value, onChange, label, required = false, min, max, disabled = false }: {
  value: string; onChange: (value: string) => void; label: string; required?: boolean; min?: string; max?: string; disabled?: boolean
}) {
  const [text, setText] = useState(displayDate(value))
  const lastEmitted = useRef(value)
  const input = useRef<HTMLInputElement>(null)
  useEffect(() => {
    if (lastEmitted.current !== value) {
      lastEmitted.current = value
      setText(displayDate(value))
    }
  }, [value])
  useEffect(() => {
    const parsed = parseAppointmentDate(text)
    const invalid = Boolean(text && (!parsed || (min && parsed < min) || (max && parsed > max)))
    input.current?.setCustomValidity(invalid ? 'Nhập ngày hợp lệ theo dd/mm/yyyy và trong khoảng cho phép.' : '')
  }, [text, min, max])
  function updateText(next: string) {
    setText(next)
    const parsed = parseAppointmentDate(next) ?? ''
    lastEmitted.current = parsed
    onChange(parsed)
  }
  return <span className="vn-date-control">
    <input ref={input} type="text" aria-label={label} inputMode="numeric" placeholder="dd/mm/yyyy" maxLength={10} required={required} disabled={disabled} value={text} onChange={(event) => updateText(event.target.value)} />
    <span className="vn-date-picker"><CalendarDays size={16} aria-hidden="true" /><input type="date" aria-label={'Mở lịch ' + label.toLowerCase()} title="Mở lịch" disabled={disabled} min={min} max={max} value={value} onChange={(event) => { lastEmitted.current = event.target.value; setText(displayDate(event.target.value)); onChange(event.target.value) }} /></span>
  </span>
}
