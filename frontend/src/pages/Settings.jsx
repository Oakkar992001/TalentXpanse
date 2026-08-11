import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'
import SettingsSidebar from '../components/SettingsSidebar'
import PasswordSecuritySettings from '../components/PasswordSecuritySettings'
import NotificationSettings from '../components/NotificationSettings'
import ReliabilitySettings from '../components/ReliabilitySettings'
import VerificationSettings from '../components/VerificationSettings'
import ProposalCreditsSettings from '../components/ProposalCreditsSettings'
import '../settings.css'

const roleName = (role, t) => role === 'client' ? t('common.client', 'Client') : t('common.freelancer', 'Freelancer')

const pageTitle = {
  information: 'settings.information',
  account: 'settings.account',
  security: 'settings.security',
  notifications: 'settings.notifications',
  verification: 'settings.verification',
  credits: 'settings.credits',
  reliability: 'settings.reliability',
}

export default function SettingsScreen({ section = 'information' }) {
  const { user, addRole, switchRole, refreshUser, errorMessage } = useAuth()
  const { t } = usePreferences()
  const navigate = useNavigate()
  const location = useLocation()
  const [account, setAccount] = useState(null)
  const [name, setName] = useState('')
  const [credits, setCredits] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [dirty, setDirty] = useState(false)

  const load = useCallback(async () => {
    try {
      const { data } = await api.get('/account-settings')
      setAccount(data.data)
      setName(data.data.name)
      if (section === 'credits') {
        const response = await api.get('/proposal-credits')
        setCredits(response.data.data)
      }
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }, [errorMessage, section])

  useEffect(() => { if (user) load() }, [user, load])
  useEffect(() => {
    if (new URLSearchParams(location.search).get('verified') === '1') {
      setNotice(t('settings.email_verified_notice', 'Your email address has been verified.'))
      navigate('/settings', { replace: true })
    }
  }, [location.search, navigate, t])
  useEffect(() => {
    const warn = (event) => {
      if (!dirty) return
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])

  if (!user) return <section className="simple-page"><h1>{t('settings.account', 'Account settings')}</h1><Link className="button button-primary" to="/login">{t('nav.login', 'Log in')}</Link></section>

  const saveInformation = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const { data } = await api.put('/account-settings', { name })
      setAccount(data.data)
      await refreshUser()
      setDirty(false)
      setNotice(t('settings.saved_notice', 'Your information was saved.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const resendVerification = async () => {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      const { data } = await api.post('/email/verification-notification')
      setNotice(data.message)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const activate = async (role) => {
    setBusy(true)
    setError('')
    try {
      await switchRole(role)
      navigate(`/dashboard?role=${role}`)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const addWorkspace = async (role) => {
    setBusy(true)
    setError('')
    try {
      await addRole(role)
      navigate(`/workspace-setup?role=${role}`)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const missingRole = account?.roles?.includes('client') ? (account.roles.includes('freelancer') ? null : 'freelancer') : 'client'
  const profileCompletion = account?.active_role === 'freelancer' ? account?.freelancer_profile?.profile_completeness || 0 : null

  if (section === 'verification') return <section className="settings-page"><header><p className="eyebrow">{t('nav.settings', 'Settings')}</p><h1>{t(pageTitle.verification, 'Identity and verification')}</h1><p>{t('settings.verification_intro', 'Manage the manual verification steps that support a safer marketplace.')}</p></header><div className="settings-layout"><SettingsSidebar /><main><VerificationSettings account={account} onRefresh={load} /></main></div></section>

  return <section className="settings-page">
    <header><p className="eyebrow">{t('nav.settings', 'Settings')}</p><h1>{t(pageTitle[section] || pageTitle.information, 'My information')}</h1><p>{t('settings.intro', 'Keep your account details and marketplace workspace accurate.')}</p></header>
    <div className="settings-layout"><SettingsSidebar /><main>
      {error && <p className="form-notice">{error}</p>}
      {notice && <p className="form-notice">{notice}</p>}
      {!account ? <p className="settings-loading">{t('settings.loading', 'Loading account settings...')}</p> : section === 'information' ? <>
        <form className="settings-card" onSubmit={saveInformation}>
          <div className="settings-card-heading"><div><p className="eyebrow">{t('settings.contact', 'Contact information')}</p><h2>{t('settings.your_account', 'Your account')}</h2></div><span className={`status-pill ${account.account_status}`}>{t(`settings.status_${account.account_status}`, account.account_status)}</span></div>
          <label>{t('auth.full_name', 'Full name')}<input required minLength="2" maxLength="255" value={name} onChange={(event) => { setName(event.target.value); setDirty(event.target.value !== account.name) }} /></label>
          <label>{t('settings.email_address', 'Email address')}<input value={account.email} readOnly aria-describedby="email-status" /><small id="email-status">{account.email_verified ? t('settings.email_verified_detail', 'Email verified. This address is confirmed for account security.') : t('settings.email_unverified_detail', 'Your email is not verified yet. Check your inbox for the secure verification link.')}</small></label>
          {!account.email_verified && <button type="button" disabled={busy} className="button button-outline verification-button" onClick={resendVerification}>{busy ? t('settings.sending', 'Sending...') : t('settings.resend_verification', 'Resend verification email')}</button>}
          <div className="account-facts"><div><b>{account.id ? `TX-${String(account.id).padStart(7, '0')}` : '—'}</b><small>{t('settings.user_id', 'User ID')}</small></div><div><b>{account.email_verified ? t('settings.verified', 'Verified') : t('settings.unverified', 'Unverified')}</b><small>{t('settings.email_status', 'Email status')}</small></div>{profileCompletion !== null && <div><b>{profileCompletion}%</b><small>{t('settings.profile_complete', 'Profile complete')}</small></div>}</div>
          <button disabled={busy || !dirty} className="button button-primary">{busy ? t('common.saving', 'Saving...') : t('settings.save_changes', 'Save changes')}</button>
        </form>
        <section className="settings-card settings-note"><h2>{t('settings.account_security', 'Account security')}</h2><p>{t('settings.security_detail', 'Email verification and password changes are available. Identity verification will be introduced before live payments.')}</p></section>
      </> : section === 'security' ? <PasswordSecuritySettings passwordLoginEnabled={account.password_login_enabled} /> : section === 'notifications' ? <NotificationSettings /> : section === 'reliability' ? <ReliabilitySettings /> : section === 'account' ? <>
        <section className="settings-card"><p className="eyebrow">{t('settings.active_workspace', 'Active workspace')}</p><h2>{t('settings.choose_workspace', 'Choose how you use TalentXpanse')}</h2><p className="settings-copy">{t('settings.switch_detail', 'Switching changes your marketplace experience without creating another user or ending your session.')}</p><div className="workspace-cards">{account.roles.map((role) => <article key={role}><span>{role === 'client' ? 'C' : 'F'}</span><div><b>{t('settings.workspace_name', `${roleName(role, t)} workspace`, { role: roleName(role, t) })}</b><p>{role === account.active_role ? t('settings.currently_active', 'Currently active') : t('settings.available_account', 'Available on this account')}</p></div>{role === account.active_role ? <em>{t('settings.active', 'Active')}</em> : <button disabled={busy} onClick={() => activate(role)}>{t('settings.switch_to', `Switch to ${roleName(role, t)}`, { role: roleName(role, t) })}</button>}</article>)}</div>{missingRole && <div className="additional-workspace"><div><h3>{t('settings.add_workspace', `Add ${roleName(missingRole, t)} workspace`, { role: roleName(missingRole, t) })}</h3><p>{t('settings.add_workspace_detail', `Use the same sign-in for a separate ${roleName(missingRole, t)} profile. You will be guided through setup before it becomes active.`, { role: roleName(missingRole, t) })}</p></div><button disabled={busy} className="button button-outline" onClick={() => addWorkspace(missingRole)}>{t('settings.add_role', `Add ${roleName(missingRole, t)}`, { role: roleName(missingRole, t) })}</button></div>}</section>
        <section className="settings-card settings-note"><h2>{t('settings.privacy_closure', 'Privacy and account closure')}</h2><p>{t('settings.privacy_intro', 'Read our ')}<Link to="/privacy">{t('auth.privacy', 'Privacy Policy')}</Link>{t('settings.privacy_and', ' and ')}<Link to="/terms">{t('auth.terms', 'Terms of Use')}</Link>{t('settings.privacy_detail', '. Account closure and data requests must preserve active-project, dispute, and required payment records; contact support once that workflow is available.')}</p></section>
      </> : <ProposalCreditsSettings credits={credits} />}
    </main></div>
  </section>
}
