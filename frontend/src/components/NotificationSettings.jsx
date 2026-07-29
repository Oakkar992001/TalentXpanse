import { useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

const options = [
  ['messages', 'Messages', 'New project and proposal conversation messages.'],
  ['proposals', 'Proposals', 'New applications and decisions on your proposals.'],
  ['projects', 'Projects', 'Milestones, reviews, support requests, and project completion.'],
  ['job_alerts', 'Saved-search alerts', 'A daily in-app digest when new jobs match a saved job search.'],
  ['email_updates', 'Email updates', 'Optional email copies of marketplace updates after TalentXpanse email delivery is enabled.'],
]

export default function NotificationSettings() {
  const { errorMessage } = useAuth()
  const [preferences, setPreferences] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => { api.get('/notification-preferences').then(({ data }) => setPreferences(data.data)).catch((requestError) => setError(errorMessage(requestError))) }, [errorMessage])

  const save = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.put('/notification-preferences', preferences); setPreferences(data.data); setNotice('Notification preferences saved.') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  if (!preferences) return <section className="settings-card"><p className="settings-loading">Loading notification settings…</p>{error && <p className="form-notice">{error}</p>}</section>

  return <><section className="settings-card"><p className="eyebrow">Notification settings</p><h2>Choose your marketplace alerts</h2><p className="settings-copy">These preferences control the TalentXpanse notification center. Email updates take effect only after the production email service is configured. Security emails and account-recovery emails are always sent when needed.</p><form className="notification-preferences" onSubmit={save}>{options.map(([key, title, detail]) => <label key={key}><input type="checkbox" checked={preferences[key]} onChange={(event) => setPreferences({ ...preferences, [key]: event.target.checked })} /><span><b>{title}</b><small>{detail}</small></span></label>)}{error && <p className="form-notice">{error}</p>}{notice && <p className="form-notice">{notice}</p>}<button disabled={busy} className="button button-primary">{busy ? 'Saving…' : 'Save preferences'}</button></form></section><section className="settings-card settings-note"><h2>What stays on</h2><p>Password recovery, email verification, and important account-security emails are not controlled here.</p></section></>
}
