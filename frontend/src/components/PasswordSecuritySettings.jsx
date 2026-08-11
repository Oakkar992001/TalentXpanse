import { useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'

export default function PasswordSecuritySettings({ passwordLoginEnabled }) {
  const { errorMessage } = useAuth()
  const { t } = usePreferences()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.put('/account/password', form); setForm({ current_password: '', password: '', password_confirmation: '' }); setNotice(data.message) } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  return <>
    <section className="settings-card"><p className="eyebrow">{t('settings.security', 'Password and security')}</p><h2>{t('security.keep_secure', 'Keep your sign-in secure')}</h2>
      {!passwordLoginEnabled ? <p className="settings-copy">{t('security.google_only', 'This account uses Google sign-in. Password setup is not available yet, so manage the password through your Google account.')}</p> : <form className="password-security-form" onSubmit={submit}>
        <p className="settings-copy">{t('security.password_signout', 'Changing your password signs out other active TalentXpanse sessions.')}</p>
        {error && <p className="form-notice">{error}</p>}{notice && <p className="form-notice">{notice}</p>}
        <label>{t('security.current_password', 'Current password')}<input required autoComplete="current-password" type="password" value={form.current_password} onChange={(event) => setForm({ ...form, current_password: event.target.value })} /></label>
        <label>{t('security.new_password', 'New password')}<input required minLength="8" autoComplete="new-password" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /><small>{t('security.minimum', 'Use at least 8 characters.')}</small></label>
        <label>{t('security.confirm_new_password', 'Confirm new password')}<input required autoComplete="new-password" type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></label>
        <button disabled={busy} className="button button-primary">{busy ? t('security.updating', 'Updating...') : t('security.update_password', 'Update password')}</button>
      </form>}
    </section>
    <section className="settings-card settings-note"><h2>{t('security.reminder', 'Security reminder')}</h2><p>{t('security.reminder_detail', 'TalentXpanse will never ask for your password in project messages. Use a unique password and report suspicious messages to the marketplace team.')}</p></section>
  </>
}
