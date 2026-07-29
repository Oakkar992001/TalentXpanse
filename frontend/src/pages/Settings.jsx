import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import SettingsSidebar from '../components/SettingsSidebar'
import PasswordSecuritySettings from '../components/PasswordSecuritySettings'
import NotificationSettings from '../components/NotificationSettings'
import VerificationSettings from '../components/VerificationSettings'
import '../settings.css'

const roleName = (role) => role === 'client' ? 'Client' : 'Freelancer'

const pageTitle = {
  information: 'My information',
  account: 'Account settings',
  security: 'Password and security',
  notifications: 'Notification settings',
  verification: 'Identity and verification',
  credits: 'Membership and credits',
}

export default function SettingsScreen({ section = 'information' }) {
  const { user, addRole, switchRole, refreshUser, errorMessage } = useAuth()
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
      setNotice('Your email address has been verified.')
      navigate('/settings', { replace: true })
    }
  }, [location.search, navigate])
  useEffect(() => {
    const warn = (event) => {
      if (!dirty) return
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])

  if (!user) return <section className="simple-page"><h1>Account settings</h1><Link className="button button-primary" to="/login">Log in</Link></section>

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
      setNotice('Your information was saved.')
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

  if (section === 'verification') return <section className="settings-page"><header><p className="eyebrow">Settings</p><h1>{pageTitle.verification}</h1><p>Manage the manual verification steps that support a safer marketplace.</p></header><div className="settings-layout"><SettingsSidebar /><main><VerificationSettings account={account} onRefresh={load} /></main></div></section>

  return <section className="settings-page">
    <header><p className="eyebrow">Settings</p><h1>{pageTitle[section] || pageTitle.information}</h1><p>Keep your account details and marketplace workspace accurate.</p></header>
    <div className="settings-layout"><SettingsSidebar /><main>
      {error && <p className="form-notice">{error}</p>}
      {notice && <p className="form-notice">{notice}</p>}
      {!account ? <p className="settings-loading">Loading account settings…</p> : section === 'information' ? <>
        <form className="settings-card" onSubmit={saveInformation}>
          <div className="settings-card-heading"><div><p className="eyebrow">Contact information</p><h2>Your account</h2></div><span className={`status-pill ${account.account_status}`}>{account.account_status}</span></div>
          <label>Full name<input required minLength="2" maxLength="255" value={name} onChange={(event) => { setName(event.target.value); setDirty(event.target.value !== account.name) }} /></label>
          <label>Email address<input value={account.email} readOnly aria-describedby="email-status" /><small id="email-status">{account.email_verified ? 'Email verified. This address is confirmed for account security.' : 'Your email is not verified yet. Check your inbox for the secure verification link.'}</small></label>
          {!account.email_verified && <button type="button" disabled={busy} className="button button-outline verification-button" onClick={resendVerification}>{busy ? 'Sending…' : 'Resend verification email'}</button>}
          <div className="account-facts"><div><b>{account.id ? `TX-${String(account.id).padStart(7, '0')}` : '—'}</b><small>User ID</small></div><div><b>{account.email_verified ? 'Verified' : 'Unverified'}</b><small>Email status</small></div>{profileCompletion !== null && <div><b>{profileCompletion}%</b><small>Profile complete</small></div>}</div>
          <button disabled={busy || !dirty} className="button button-primary">{busy ? 'Saving…' : 'Save changes'}</button>
        </form>
        <section className="settings-card settings-note"><h2>Account security</h2><p>Email verification and password changes are available. Identity verification will be introduced before live payments.</p></section>
      </> : section === 'security' ? <PasswordSecuritySettings passwordLoginEnabled={account.password_login_enabled} /> : section === 'notifications' ? <NotificationSettings /> : section === 'account' ? <>
        <section className="settings-card"><p className="eyebrow">Active workspace</p><h2>Choose how you use TalentXpanse</h2><p className="settings-copy">Switching changes your marketplace experience without creating another user or ending your session.</p><div className="workspace-cards">{account.roles.map((role) => <article key={role}><span>{role === 'client' ? 'C' : 'F'}</span><div><b>{roleName(role)} workspace</b><p>{role === account.active_role ? 'Currently active' : 'Available on this account'}</p></div>{role === account.active_role ? <em>Active</em> : <button disabled={busy} onClick={() => activate(role)}>Switch to {roleName(role)}</button>}</article>)}</div>{missingRole && <div className="additional-workspace"><div><h3>Add {roleName(missingRole)} workspace</h3><p>Use the same sign-in for a separate {missingRole} profile. You will be guided through setup before it becomes active.</p></div><button disabled={busy} className="button button-outline" onClick={() => addWorkspace(missingRole)}>Add {roleName(missingRole)}</button></div>}</section>
        <section className="settings-card settings-note"><h2>Privacy and account closure</h2><p>Read our <Link to="/privacy">Privacy Policy</Link> and <Link to="/terms">Terms of Use</Link>. Account closure and data requests must preserve active-project, dispute, and required payment records; contact support once that workflow is available.</p></section>
      </> : <section className="settings-card credits-settings"><p className="eyebrow">Freelancer workspace</p><h2>Proposal Credits</h2>{credits ? <><strong>{credits.balance}</strong><span>available credits</span><p>{credits.monthly_allowance || 20} credits are granted each month. Credit costs are shown before you submit a proposal.</p></> : <p className="settings-loading">Loading credit balance…</p>}</section>}
    </main></div>
  </section>
}
