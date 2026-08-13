import { useCallback, useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'
import { useConfirmation } from '../contexts/ConfirmContext'

export default function PasswordSecuritySettings({ passwordLoginEnabled }) {
  const { errorMessage } = useAuth()
  const confirmDialog = useConfirmation()
  const { t } = usePreferences()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [security, setSecurity] = useState(null)
  const [setup, setSetup] = useState(null)
  const [code, setCode] = useState('')
  const [recoveryCodes, setRecoveryCodes] = useState([])

  const loadSecurity = useCallback(async () => {
    try { const { data } = await api.get('/security'); setSecurity(data.data) } catch (requestError) { setError(errorMessage(requestError)) }
  }, [errorMessage])
  useEffect(() => { loadSecurity() }, [loadSecurity])

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.put('/account/password', form); setForm({ current_password: '', password: '', password_confirmation: '' }); setNotice(data.message) } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const beginTwoFactor = async () => {
    setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.post('/security/two-factor/setup'); setSetup(data.data) } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const confirmTwoFactor = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.post('/security/two-factor/confirm', { code }); setRecoveryCodes(data.data.recovery_codes || []); setSetup(null); setCode(''); setNotice(data.message); await loadSecurity() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const disableTwoFactor = async () => {
    if (!await confirmDialog({ title: 'Disable two-factor authentication?', message: 'Your account will rely only on its password or sign-in provider until you enable it again.', confirmLabel: 'Disable two-factor', tone: 'danger' })) return
    setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.delete('/security/two-factor', { data: { code } }); setCode(''); setNotice(data.message); await loadSecurity() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const revokeOtherSessions = async () => {
    if (!await confirmDialog({ title: 'Sign out of other devices?', message: 'Your current session stays active. Every other browser or device will need to sign in again.', confirmLabel: 'Sign out other sessions' })) return
    setBusy(true); setError(''); setNotice('')
    try { const { data } = await api.post('/security/sessions/revoke-others'); setNotice(data.message); await loadSecurity() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const revokeSession = async (session) => {
    if (!await confirmDialog({ title: 'Sign out this device?', message: `The session “${session.label}” will be ended immediately.`, confirmLabel: 'Sign out device' })) return
    setBusy(true); setError(''); setNotice('')
    try { await api.delete(`/security/sessions/${session.id}`); setNotice('Device session signed out.'); await loadSecurity() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
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
    <section className="settings-card security-mfa"><p className="eyebrow">Two-factor authentication</p><h2>Protect this account with an authenticator app</h2><p className="settings-copy">Use Google Authenticator, Microsoft Authenticator, 1Password, or another TOTP app. Administrators should enable this before production access is required.</p>{error && <p className="form-notice" role="alert">{error}</p>}{notice && <p className="form-notice" role="status">{notice}</p>}
      {!security ? <p className="settings-loading">Loading security settings…</p> : security.two_factor_enabled ? <div className="security-action-row"><div><b>Two-factor authentication is on</b><small>Use an authenticator or a stored recovery code if you lose your device.</small></div><label>Current code<input inputMode="numeric" maxLength="32" value={code} onChange={(event) => setCode(event.target.value)} /></label><button disabled={busy || !code.trim()} onClick={disableTwoFactor}>Disable</button></div> : !setup ? <button disabled={busy} className="button button-outline" onClick={beginTwoFactor}>{busy ? 'Preparing…' : 'Set up two-factor authentication'}</button> : <form className="two-factor-setup" onSubmit={confirmTwoFactor}><p><b>1. Add this secret to your authenticator:</b> <code>{setup.secret}</code></p><p><small>If your app supports setup links, use the generated code URI: <code>{setup.otpauth_uri}</code></small></p><label><b>2. Enter the six-digit code</b><input required inputMode="numeric" autoComplete="one-time-code" maxLength="6" value={code} onChange={(event) => setCode(event.target.value)} /></label><div><button disabled={busy} className="button button-primary">{busy ? 'Confirming…' : 'Enable two-factor'}</button><button type="button" disabled={busy} onClick={() => { setSetup(null); setCode('') }}>Cancel</button></div></form>}
      {recoveryCodes.length > 0 && <div className="recovery-codes"><b>Store these recovery codes now</b><p>They are shown once. Keep them offline and never send them in a message.</p><code>{recoveryCodes.join('  ')}</code></div>}
    </section>
    <section className="settings-card security-sessions"><div className="settings-card-heading"><div><p className="eyebrow">Signed-in devices</p><h2>Login and device activity</h2><p className="settings-copy">Review browser sessions and end access you do not recognize.</p></div><button disabled={busy || !security?.sessions?.some((session) => !session.current)} onClick={revokeOtherSessions}>Sign out other sessions</button></div>{security?.sessions?.length ? <div className="security-session-list">{security.sessions.map((session) => <article key={session.id}><div><b>{session.label}</b><small>{session.current ? 'This device' : 'Signed-in device'} · Last active {session.last_used_at ? new Date(session.last_used_at).toLocaleString() : new Date(session.created_at).toLocaleString()}</small></div>{session.current ? <em>Current</em> : <button disabled={busy} onClick={() => revokeSession(session)}>Sign out</button>}</article>)}</div> : <p className="settings-copy">No active API sessions were found.</p>}</section>
    <section className="settings-card settings-note"><h2>{t('security.reminder', 'Security reminder')}</h2><p>{t('security.reminder_detail', 'TalentXpanse will never ask for your password in project messages. Use a unique password and report suspicious messages to the marketplace team.')}</p></section>
  </>
}
