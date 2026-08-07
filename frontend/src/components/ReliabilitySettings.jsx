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
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      setError('')
      const { data } = await api.get('/reliability')
      setSummaries(data.data)
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }, [errorMessage])

  useEffect(() => { if (user?.id) load() }, [load, user?.id])

  if (error) return <p className="form-notice" role="alert">{error}</p>
  if (!summaries) return <p className="settings-loading" aria-live="polite">Loading your marketplace reliability...</p>

  const workspaces = Object.values(summaries)

  return <div className="reliability-settings">
    <section className="settings-card reliability-intro">
      <p className="eyebrow">Marketplace reliability</p>
      <h2>Build trust through real work.</h2>
      <p>Your public profile shows a simple tier. Your private account health and marketplace reach are only changed after a fair review of verified platform activity—never from a report or an accusation alone.</p>
    </section>

    <div className="reliability-workspaces">
      {workspaces.map((summary) => <article className="settings-card reliability-workspace" key={summary.role}>
        <header><div><p className="eyebrow">{roleLabel(summary.role)}</p><h2>{summary.tier_label}</h2></div><span className={`reliability-reach ${summary.search_visibility}`}>{summary.visibility_label}</span></header>
        <div className="reliability-score"><div><b>{summary.score}</b><span>/ 100 private account health</span></div><div className="reliability-meter" role="progressbar" aria-label={`${roleLabel(summary.role)} account health`} aria-valuemin="0" aria-valuemax="100" aria-valuenow={summary.score}><span style={{ width: `${summary.score}%` }} /></div></div>
        <div className="reliability-facts"><span><b>{summary.completed_projects_count}</b><small>completed projects</small></span><span><b>{summary.positive_reviews_count}</b><small>positive reviews</small></span><span><b>{summary.average_rating ? `${Number(summary.average_rating).toFixed(1)} / 5` : '—'}</b><small>project rating</small></span></div>
        <p className="reliability-next"><b>Next step</b>{summary.next_step}</p>
      </article>)}
    </div>

    <section className="settings-card reliability-history">
      <header><div><p className="eyebrow">Account history</p><h2>Reliability activity</h2></div><p>Confirmed items can expire over time. Pending concerns never reduce reach.</p></header>
      {workspaces.some((summary) => summary.recent_events?.length) ? <div>{workspaces.flatMap((summary) => summary.recent_events.map((event) => ({ ...event, role: summary.role }))).sort((first, second) => new Date(second.created_at) - new Date(first.created_at)).map((event) => <article key={`${event.role}-${event.id}`}><span className={`reliability-event-icon ${event.points < 0 ? 'negative' : 'positive'}`}>{event.status === 'pending' ? '?' : event.points < 0 ? '!' : '+'}</span><div><b>{eventDescription(event)}</b><small>{roleLabel(event.role)} · {new Date(event.created_at).toLocaleDateString()}{event.expires_at ? ` · Expires ${new Date(event.expires_at).toLocaleDateString()}` : ''}</small>{event.resolution_note && <p>Operations note: {event.resolution_note}</p>}</div><em className={event.status}>{event.status === 'pending' ? 'Under review' : event.points > 0 ? `+${event.points}` : String(event.points)}</em></article>)}</div> : <p className="reliability-empty">Complete projects, reviews, verification, and any future operations reviews will appear here.</p>}
    </section>
  </div>
}
