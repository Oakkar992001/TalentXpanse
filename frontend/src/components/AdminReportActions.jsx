import { useState } from 'react'

export default function AdminReportActions({ report, busy, onAction }) {
  const [reliabilityAction, setReliabilityAction] = useState('none')
  const [note, setNote] = useState('')
  const path = `/admin/reports/${report.id}`
  const requiresNote = reliabilityAction !== 'none'
  const canResolve = !requiresNote || note.trim().length >= 10

  if (!['open', 'reviewed'].includes(report.status)) return null

  return <div className="admin-report-actions">
    {report.status === 'open' && <button disabled={busy === path} onClick={() => onAction(path, { status: 'reviewed' })}>Start review</button>}
    {report.status === 'reviewed' && <><label>Reliability decision<select value={reliabilityAction} onChange={(event) => setReliabilityAction(event.target.value)}><option value="none">No reliability action</option><option value="warning">Confirmed warning (60 days)</option><option value="serious_violation">Serious violation (180 days)</option></select></label>{requiresNote && <textarea aria-label={`Evidence note for report ${report.id}`} value={note} onChange={(event) => setNote(event.target.value)} placeholder="Record the evidence supporting this action." maxLength="1000" />}<button disabled={busy === path || !canResolve} onClick={() => onAction(path, { status: 'resolved', reliability_action: reliabilityAction, reliability_note: note.trim() || null })}>{reliabilityAction === 'none' ? 'Resolve report' : 'Resolve with action'}</button></>}
    <button disabled={busy === path} onClick={() => onAction(path, { status: 'dismissed' })}>Dismiss</button>
  </div>
}
