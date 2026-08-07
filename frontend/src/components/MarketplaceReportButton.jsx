import { useEffect, useId, useState } from 'react'
import { createPortal } from 'react-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../marketplace-report.css'

function FlagIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M5 21V4m0 1c4-2 6 2 11 0l3 2v9c-5 2-7-2-11 0" /></svg>
}

export default function MarketplaceReportButton({ targetType, targetId, compact = false }) {
  const { user, errorMessage } = useAuth()
  const [reason, setReason] = useState('spam')
  const [details, setDetails] = useState('')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [open, setOpen] = useState(false)
  const reasonId = useId()
  const detailsId = useId()

  useEffect(() => {
    if (!open) return undefined
    const closeOnEscape = (event) => { if (event.key === 'Escape') setOpen(false) }
    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [open])

  if (!user) return null
  const label = targetType === 'message' ? 'Report this message' : 'Report'
  const openDialog = () => { setError(''); setNotice(''); setOpen(true) }
  const closeDialog = () => { if (!busy) setOpen(false) }
  const submit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const { data } = await api.post('/reports', { target_type: targetType, target_id: targetId, reason, details })
      setNotice(data.created ? 'Thank you. Your report was sent for a private operations review.' : 'You already reported this item. Our team will review the existing report.')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  return <><button type="button" className={`marketplace-report-trigger ${compact ? 'compact' : ''}`} onClick={openDialog} aria-label={label}>{compact ? <FlagIcon /> : <><FlagIcon /><span>Report</span></>}</button>{open && createPortal(<div className="marketplace-report-dialog-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) closeDialog() }}><section className="marketplace-report-dialog" role="dialog" aria-modal="true" aria-labelledby={`${reasonId}-title`}><header><div><p className="eyebrow">Safety and trust</p><h2 id={`${reasonId}-title`}>{label}</h2><p>Reports are private. They never reduce someone’s reach automatically; TalentXpanse reviews the evidence first.</p></div><button type="button" className="marketplace-report-close" onClick={closeDialog} disabled={busy} aria-label="Close report dialog">×</button></header>{notice ? <div className="marketplace-report-success"><b>{notice}</b><button type="button" className="button button-primary" onClick={closeDialog}>Done</button></div> : <form onSubmit={submit}><label htmlFor={reasonId}>What is the issue?</label><select id={reasonId} value={reason} onChange={(event) => setReason(event.target.value)}><option value="spam">Spam</option><option value="fraud">Fraud or scam</option><option value="abuse">Abuse or harassment</option><option value="inappropriate_content">Inappropriate content</option><option value="other">Other</option></select><label htmlFor={detailsId}>Additional details <small>Optional</small></label><textarea id={detailsId} value={details} onChange={(event) => setDetails(event.target.value)} maxLength="1500" placeholder="Tell us what happened. Do not include passwords or payment details." />{error && <p className="form-notice" role="alert">{error}</p>}<footer><button type="button" className="button button-outline" onClick={closeDialog} disabled={busy}>Cancel</button><button disabled={busy} type="submit" className="button button-primary">{busy ? 'Sending…' : 'Send report'}</button></footer></form>}</section></div>, document.body)}</>
}
