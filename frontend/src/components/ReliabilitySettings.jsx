import { useCallback, useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

const roleLabel = (role) => role === 'client' ? 'Client workspace' : 'Freelancer workspace'
const readable = (value) => String(value || '').replaceAll('_', ' ')

function eventDescription(event) {
  if (event.status === 'pending') return 'Under fair review. This does not change your marketplace reach while it is pending.'
  if (event.event_type === 'project_completed') return 'Completed a project through TalentXpanse.'
  if (event.event_type === 'positive_review') return 'Received positive project feedback.'
  if (event.event_type === 'identity_verified' || event.event_type === 'company_verified') return 'Completed marketplace verification.'
  if (event.event_type === 'moderation_action') return event.reason_code === 'serious_violation' ? 'A confirmed serious marketplace policy violation.' : 'A confirmed marketplace policy warning.'
  return event.details || readable(event.event_type)
}

export default function ReliabilitySettings() {
  const { user, errorMessage } = useAuth()
  const [summaries, setSummaries] = useState(null)
  const [appeals, setAppeals] = useState([])
  const [appealEventId, setAppealEventId] = useState(null)
  const [appealReason, setAppealReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      setError('')
      const [{ data }, { data: appealsData }] = await Promise.all([api.get('/reliability'), api.get('/reliability-appeals')])
      setSummaries(data.data)
      setAppeals(appealsData.data || [])
    } catch (requestError) { setError(errorMessage(requestError)) }
  }, [errorMessage])

  useEffect(() => { if (user?.id) load() }, [load, user?.id])
  const submitAppeal = async (event) => {
    event.preventDefault(); setBusy(true); setError('')
    try { await api.post(`/reliability-events/${appealEventId}/appeals`, { reason: appealReason.trim() }); setAppealEventId(null); setAppealReason(''); await load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  if (error) return <p className="form-notice" role="alert">{error}</p>
  if (!summaries) return <p className="settings-loading" aria-live="polite">Loading your marketplace reliability...</p>
  const workspaces = Object.values(summaries)
  const events = workspaces.flatMap((summary) => summary.recent_events?.map((event) => ({ ...event, role: summary.role })) || []).sort((first, second) => new Date(second.created_at) - new Date(first.created_at))

  return <div className="reliability-settings">
    <section className="settings-card reliability-intro"><p className="eyebrow">Marketplace reliability</p><h2>Build trust through real work.</h2><p>Your public profile shows a simple tier. Your private account health and marketplace reach only change after a fair review of verified platform activity—not from a report or accusation alone.</p></section>
    <div className="reliability-workspaces">{workspaces.map((summary) => <article className="settings-card reliability-workspace" key={summary.role}><header><div><p className="eyebrow">{roleLabel(summary.role)}</p><h2>{summary.tier_label}</h2></div><span className={`reliability-reach ${summary.search_visibility}`}>{summary.visibility_label}</span></header><div className="reliability-score"><div><b>{summary.score}</b><span>/ 100 private account health</span></div><div className="reliability-meter" role="progressbar" aria-label={`${roleLabel(summary.role)} account health`} aria-valuemin="0" aria-valuemax="100" aria-valuenow={summary.score}><span style={{ width: `${summary.score}%` }} /></div></div><div className="reliability-facts"><span><b>{summary.completed_projects_count}</b><small>completed projects</small></span><span><b>{summary.positive_reviews_count}</b><small>positive reviews</small></span><span><b>{summary.average_rating ? `${Number(summary.average_rating).toFixed(1)} / 5` : '—'}</b><small>project rating</small></span></div><p className="reliability-next"><b>Next step</b>{summary.next_step}</p></article>)}</div>
    <section className="settings-card reliability-history"><header><div><p className="eyebrow">Account history</p><h2>Reliability activity</h2></div><p>Confirmed items can expire over time. Pending concerns never reduce reach.</p></header>{events.length ? <div>{events.map((event) => { const appeal = appeals.find((item) => item.marketplace_reliability_event_id === event.id); const canAppeal = event.status === 'confirmed' && event.points < 0; return <article key={`${event.role}-${event.id}`}><span className={`reliability-event-icon ${event.points < 0 ? 'negative' : 'positive'}`}>{event.status === 'pending' ? '?' : event.points < 0 ? '!' : '+'}</span><div><b>{eventDescription(event)}</b><small>{roleLabel(event.role)} · {new Date(event.created_at).toLocaleDateString()}{event.expires_at ? ` · Expires ${new Date(event.expires_at).toLocaleDateString()}` : ''}</small>{event.resolution_note && <p>Operations note: {event.resolution_note}</p>}{canAppeal && <div className="reliability-appeal-action">{appeal ? <span>Appeal {readable(appeal.status)}</span> : <button onClick={() => { setAppealEventId(event.id); setAppealReason('') }}>Request an appeal</button>}</div>}{appealEventId === event.id && <form className="reliability-appeal-form" onSubmit={submitAppeal}><label>Explain what should be reviewed again<textarea required minLength="20" maxLength="2000" value={appealReason} onChange={(input) => setAppealReason(input.target.value)} /></label><div><button className="button button-primary" disabled={busy || appealReason.trim().length < 20}>{busy ? 'Sending…' : 'Submit appeal'}</button><button type="button" disabled={busy} onClick={() => setAppealEventId(null)}>Cancel</button></div></form>}</div><em className={event.status}>{event.status === 'pending' ? 'Under review' : event.points > 0 ? `+${event.points}` : String(event.points)}</em></article>})}</div> : <p className="reliability-empty">Complete projects, reviews, verification, and future operations reviews will appear here.</p>}</section>
  </div>
}
