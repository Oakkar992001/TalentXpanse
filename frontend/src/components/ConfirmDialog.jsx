import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import '../confirm-dialog.css'

export default function ConfirmDialog({ confirmation, onCancel, onConfirm }) {
  const cancelButton = useRef(null)
  const dialog = useRef(null)

  useEffect(() => {
    if (!confirmation) return undefined
    const previouslyFocused = document.activeElement
    const closeOnKeyboard = (event) => {
      if (event.key === 'Escape') onCancel()
      if (event.key !== 'Tab') return
      const focusable = [...(dialog.current?.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), textarea:not([disabled]), select:not([disabled])') || [])]
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable.at(-1)
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    cancelButton.current?.focus()
    document.addEventListener('keydown', closeOnKeyboard)
    return () => {
      document.removeEventListener('keydown', closeOnKeyboard)
      previouslyFocused?.focus?.()
    }
  }, [confirmation, onCancel])

  if (!confirmation) return null
  const { title, message, confirmLabel = 'Confirm', tone = 'danger' } = confirmation

  return createPortal(<div className="confirm-dialog-backdrop" onMouseDown={onCancel}>
    <section className="confirm-dialog" ref={dialog} role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message" onMouseDown={(event) => event.stopPropagation()}>
      <span className={`confirm-dialog-icon ${tone}`} aria-hidden="true">{tone === 'danger' ? '!' : '?'}</span>
      <div>
        <p className="eyebrow">Please confirm</p>
        <h2 id="confirm-dialog-title">{title}</h2>
        <p id="confirm-dialog-message">{message}</p>
      </div>
      <footer>
        <button type="button" className="button button-outline" ref={cancelButton} onClick={onCancel}>Cancel</button>
        <button type="button" className={`button ${tone === 'danger' ? 'confirm-dialog-danger' : 'button-primary'}`} onClick={onConfirm}>{confirmLabel}</button>
      </footer>
    </section>
  </div>, document.body)
}
