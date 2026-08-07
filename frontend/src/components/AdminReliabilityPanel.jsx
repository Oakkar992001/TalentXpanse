import { useState } from 'react'

const readable = (value) => String(value || '').replaceAll('_', ' ')

function EventRow({ event, busy, onAction }) {
  const [note, setNote] = useState('')
  const actionPath = `/admin/reliability-events/${event.id}`
  const canReview = note.trim().length >= 10

  return <article className="admin-reliability-row">
    <div>
      <b>{event.user?.name || 'Removed user'} <span>{readable(event.role)}</span></b>
      <small>{event.user?.email}</small>
      <p><strong>{readable(event.reason_code || event.event_type)}</strong> · {event.details || 'No additional context was submitted.'}</p>
      <small>Reported {new Date(event.created_at).toLocaleString()} · A pending concern has no reach impact.</small>
    </div>
    <div className="admin-reliability-actions">
      <label>Operations decision note<textarea value={note} onChange={(event) => setNote(event.target.value)} placeholder="Explain the evidence and decision for the account record." maxLength="2000" /></label>
      <div><button className="admin-danger" disabled={busy === actionPath || !canReview} onClick={() => onAction(actionPath, { status: 'confirmed', resolution_note: note.trim() })}>Confirm concern</button><button disabled={busy === actionPath || !canReview} onClick={() => onAction(actionPath, { status: 'dismissed', resolution_note: note.trim() })}>Dismiss concern</button></div>
    </div>
  </article>
}

export default function AdminReliabilityPanel({ data, busy, onAction }) {
  if (!data) return <p className="admin-loading">Loading reliability backlog...</p>

  return <section className="admin-reliability">
    <header><div><p className="eyebrow">Fair operations review</p><h2>Reliability backlog</h2><p>Only confirmed, documented decisions affect private account health or marketplace reach. Reports and cancellation claims stay neutral while they are reviewed.</p></div></header>
    <section className="admin-reliability-metrics"><article><small>Pending review</small><b>{data.metrics?.pending || 0}</b></article><article><small>Reduced reach</small><b>{data.metrics?.reduced_reach || 0}</b></article><article><small>Limited reach</small><b>{data.metrics?.limited_reach || 0}</b></article></section>
    <section className="admin-reliability-section"><h3>Pending concerns</h3>{data.pending?.length ? data.pending.map((event) => <EventRow key={event.id} event={event} busy={busy} onAction={onAction} />) : <p className="admin-empty">No reliability concerns are waiting for review.</p>}</section>
    <section className="admin-reliability-section"><h3>Recent decisions</h3>{data.recent?.length ? <div className="admin-reliability-recent">{data.recent.map((event) => <article key={event.id}><div><b>{event.user?.name || 'Removed user'} · {readable(event.event_type)}</b><small>{readable(event.role)} · {new Date(event.reviewed_at || event.created_at).toLocaleDateString()}{event.expires_at ? ` · Expires ${new Date(event.expires_at).toLocaleDateString()}` : ''}</small></div><em className={event.points < 0 ? 'negative' : ''}>{event.points > 0 ? `+${event.points}` : event.points}</em></article>)}</div> : <p className="admin-empty">No confirmed reliability decisions yet.</p>}</section>
  </section>
}
