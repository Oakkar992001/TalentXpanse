import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'

export default function PasswordRecoveryScreen({ reset = false }) {
  const { errorMessage } = useAuth()
  const { t } = usePreferences()
  const [params] = useSearchParams()
  const [form, setForm] = useState({ email: params.get('email') || '', token: params.get('token') || '', password: '', password_confirmation: '' })
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const { data } = await api.post(reset ? '/auth/reset-password' : '/auth/forgot-password', reset ? form : { email: form.email })
      setNotice(data.message)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  return <section className="auth-page"><div className="auth-panel"><div className="auth-card"><p className="eyebrow">{t('account.recovery', 'Account recovery')}</p><h1>{reset ? t('account.choose_password', 'Choose a new password.') : t('account.reset_password', 'Reset your password.')}</h1><p className="auth-intro">{reset ? t('account.new_password_intro', 'Create a strong new password for your TalentXpanse account.') : t('account.recovery_intro', 'Enter your email and we will send a secure reset link if an account matches it.')}</p>
    <form onSubmit={submit}><div className="auth-fields"><label>{t('auth.email', 'Email')}<input required type="email" autoComplete="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder={t('auth.email_example', 'Enter your email address')} /></label>{reset && <><label>{t('account.new_password', 'New password')}<input required minLength="8" type="password" autoComplete="new-password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder={t('auth.at_least_8', 'At least 8 characters')} /></label><label>{t('account.confirm_new_password', 'Confirm new password')}<input required type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} placeholder={t('auth.repeat_password', 'Repeat your password')} /></label></>}</div>{notice && <p className="form-notice">{notice}</p>}{error && <p className="form-notice">{error}</p>}<button disabled={busy || (reset && !form.token)} className="button button-primary auth-continue">{busy ? t('auth.please_wait', 'Please wait...') : reset ? t('account.reset', 'Reset password') : t('account.send_reset', 'Send reset link')}</button></form>
    {reset && !form.token && <p className="auth-switch">{t('account.reset_invalid', 'This reset link is incomplete or expired.')} <Link to="/forgot-password">{t('account.request_new_link', 'Request a new link')}</Link></p>}<p className="auth-switch"><Link to="/login">{t('account.back_login', 'Back to log in')}</Link></p>
  </div></div><aside className="auth-art"><div className="art-card large"><span>🔐</span><b>{t('account.recovery', 'Secure account recovery')}</b><small>{t('account.recovery_art', 'Use the link sent to your email to safely create a new password.')}</small></div><div className="art-card small"><b>{t('account.profile_safe', 'Your marketplace profile stays protected.')}</b></div><div className="art-figure">✦</div></aside></section>
}
