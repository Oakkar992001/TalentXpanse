import { useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'

const options = [
  ['messages', 'notifications_settings.messages', 'notifications_settings.messages_detail', 'Messages', 'New project and proposal conversation messages.'],
  ['proposals', 'notifications_settings.proposals', 'notifications_settings.proposals_detail', 'Proposals', 'New applications and decisions on your proposals.'],
  ['projects', 'notifications_settings.projects', 'notifications_settings.projects_detail', 'Projects', 'Milestones, reviews, support requests, and project completion.'],
  ['job_alerts', 'notifications_settings.alerts', 'notifications_settings.alerts_detail', 'Saved-search alerts', 'A daily in-app digest when new jobs match a saved job search.'],
  ['email_updates', 'notifications_settings.email', 'notifications_settings.email_detail', 'Email updates', 'Optional email copies of marketplace updates after TalentXpanse email delivery is enabled.'],
]

export default function NotificationSettings() {
  const { errorMessage } = useAuth()
  const { t } = usePreferences()
  const [preferences, setPreferences] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => { api.get('/notification-preferences').then(({ data }) => setPreferences(data.data)).catch((requestError) => setError(errorMessage(requestError))) }, [errorMessage])

  const save = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.put('/notification-preferences', preferences); setPreferences(data.data); setNotice(t('notifications_settings.saved', 'Notification preferences saved.')) } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  if (!preferences) return <section className="settings-card"><p className="settings-loading">{t('notifications_settings.loading', 'Loading notification settings...')}</p>{error && <p className="form-notice">{error}</p>}</section>

  return <><section className="settings-card"><p className="eyebrow">{t('settings.notifications', 'Notification settings')}</p><h2>{t('notifications_settings.heading', 'Choose your marketplace alerts')}</h2><p className="settings-copy">{t('notifications_settings.intro', 'These preferences control the TalentXpanse notification center. Email updates take effect only after the production email service is configured. Security emails and account-recovery emails are always sent when needed.')}</p><form className="notification-preferences" onSubmit={save}>{options.map(([key, titleKey, detailKey, title, detail]) => <label key={key}><input type="checkbox" checked={preferences[key]} onChange={(event) => setPreferences({ ...preferences, [key]: event.target.checked })} /><span><b>{t(titleKey, title)}</b><small>{t(detailKey, detail)}</small></span></label>)}{error && <p className="form-notice">{error}</p>}{notice && <p className="form-notice">{notice}</p>}<button disabled={busy} className="button button-primary">{busy ? t('common.saving', 'Saving...') : t('notifications_settings.save', 'Save preferences')}</button></form></section><section className="settings-card settings-note"><h2>{t('notifications_settings.stays_on', 'What stays on')}</h2><p>{t('notifications_settings.stays_on_detail', 'Password recovery, email verification, and important account-security emails are not controlled here.')}</p></section></>
}
