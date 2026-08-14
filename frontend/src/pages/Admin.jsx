import { useCallback, useEffect, useRef, useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import AdminPaymentSafetyPanel from '../components/AdminPaymentSafetyPanel'
import AdminAuditTrail from '../components/AdminAuditTrail'
import AdminVerificationPanel from '../components/AdminVerificationPanel'
import AdminReliabilityPanel from '../components/AdminReliabilityPanel'
import AdminReportActions from '../components/AdminReportActions'
import '../admin.css'

const label = (value) => String(value || '').replaceAll('_', ' ')

function confirmationForAction(path, payload) {
  const status = payload?.status
  if (path.startsWith('/admin/reliability-events/') && ['confirmed', 'dismissed'].includes(status)) return { title: `${status === 'confirmed' ? 'Confirm' : 'Dismiss'} this reliability concern?`, message: status === 'confirmed' ? 'This applies a time-limited, documented reach impact. Make sure the decision note records the evidence.' : 'This removes the pending concern without changing the member’s reach. The review note is retained in the audit trail.', confirmLabel: status === 'confirmed' ? 'Confirm concern' : 'Dismiss concern', tone: status === 'confirmed' ? 'danger' : undefined }
  if (path.startsWith('/admin/reports/') && payload?.reliability_action && payload.reliability_action !== 'none') return { title: 'Resolve report with a reliability action?', message: 'This creates a documented, time-limited account-health action for the reported user. Confirm the evidence and note first.', confirmLabel: 'Resolve with action', tone: 'danger' }
  if (path.startsWith('/admin/users/') && status === 'suspended') return { title: 'Suspend this account?', message: 'The user will be signed out and unable to access TalentXpanse until an administrator restores the account.', confirmLabel: 'Suspend account', tone: 'danger' }
  if (path.startsWith('/admin/jobs/') && status === 'closed') return { title: 'Close this job post?', message: 'It will no longer be available for new proposals. This action is recorded in the audit trail.', confirmLabel: 'Close job', tone: 'danger' }
  if (path.startsWith('/admin/jobs/') && status === 'paused') return { title: 'Pause this job post?', message: 'New proposals will be blocked until the job is reopened or closed.', confirmLabel: 'Pause job' }
  if (path.startsWith('/admin/reports/') && ['resolved', 'dismissed'].includes(status)) return { title: `${status === 'resolved' ? 'Resolve' : 'Dismiss'} this report?`, message: 'Make sure you reviewed the reported content. The decision is recorded in the audit trail.', confirmLabel: status === 'resolved' ? 'Resolve report' : 'Dismiss report' }
  if (path.startsWith('/admin/support-requests/') && ['resolved', 'dismissed'].includes(status)) return { title: `${status === 'resolved' ? 'Resolve' : 'Dismiss'} this support request?`, message: 'The submitted closing note will be shown to the project partners and the decision will be audited.', confirmLabel: status === 'resolved' ? 'Resolve request' : 'Dismiss request' }
  if (path.includes('identity-verification') || path.includes('company-verification')) return { title: `${status === 'verified' ? 'Approve' : 'Reject'} this verification?`, message: 'Verification is an account-trust signal only. Confirm the evidence before recording this decision.', confirmLabel: status === 'verified' ? 'Approve verification' : 'Reject verification', tone: status === 'rejected' ? 'danger' : undefined }
  if (path.endsWith('/payment-hold') && status === 'clear') return { title: 'Clear this payment hold?', message: 'Confirm the review outcome and note are accurate before allowing this project to continue.', confirmLabel: 'Clear hold', tone: 'danger' }

  return null
}

export function AdminLoginScreen() {
  const { adminLogin, errorMessage, sessionExpired, user } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '', two_factor_code: '' })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [requiresTwoFactor, setRequiresTwoFactor] = useState(false)

  if (user?.roles?.includes('admin')) return <Navigate to="/admin" replace />

  const submit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      await adminLogin(form)
      navigate('/admin')
    } catch (requestError) {
      const isTwoFactorChallenge = Boolean(requestError.response?.data?.errors?.two_factor_code)
      if (isTwoFactorChallenge) {
        const alreadyShowingChallenge = requiresTwoFactor
        setRequiresTwoFactor(true)
        setError(alreadyShowingChallenge ? errorMessage(requestError) : '')
      } else {
        setError(errorMessage(requestError))
      }
    } finally {
      setBusy(false)
    }
  }

  return <main className="admin-login"><section>
    <p className="eyebrow">TalentXpanse operations</p>
    <h1>Administrator sign in</h1>
    <p>Access is limited to company-provisioned administrator accounts.</p>
    {sessionExpired && <p className="form-notice">Your session timed out for security. Please sign in again.</p>}
    {error && <p className="form-notice">{error}</p>}
    <form onSubmit={submit}>
      {requiresTwoFactor ? <><div className="admin-two-factor"><b>One more security step</b><p>Enter the code from your authenticator app or a recovery code for {form.email}.</p></div><label>Authenticator or recovery code<input required autoFocus inputMode="numeric" autoComplete="one-time-code" maxLength="32" value={form.two_factor_code} onChange={(event) => setForm({ ...form, two_factor_code: event.target.value })} /></label><button type="button" className="admin-two-factor-back" onClick={() => { setRequiresTwoFactor(false); setError(''); setForm((current) => ({ ...current, two_factor_code: '' })) }}>Back to credentials</button></> : <><label>Work email<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label><label>Password<input required type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></label></>}
      <button disabled={busy || (requiresTwoFactor && !form.two_factor_code.trim())} className="button button-primary">{busy ? 'Signing in…' : requiresTwoFactor ? 'Verify and sign in' : 'Sign in to admin'}</button>
    </form>
    <small>There is no public administrator registration.</small>
  </section></main>
}

export function AdminDashboardScreen() {
  const { user, loading, logout, errorMessage } = useAuth()
  const confirm = useConfirmation()
  const navigate = useNavigate()
  const [tab, setTab] = useState('overview')
  const [dashboard, setDashboard] = useState(null)
  const [items, setItems] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(null)
  const [resolutionNotes, setResolutionNotes] = useState({})
  const [paymentData, setPaymentData] = useState(null)
  const [verificationData, setVerificationData] = useState(null)
  const [reliabilityData, setReliabilityData] = useState(null)
  const [lastRefreshed, setLastRefreshed] = useState(null)
  const refreshingRef = useRef(false)
  const isAdmin = user?.roles?.includes('admin')
  const endpoint = tab === 'users' ? '/admin/users' : tab === 'jobs' ? '/admin/jobs' : tab === 'support' ? '/admin/support-requests' : tab === 'payments' ? '/admin/payment-records' : tab === 'audit' ? '/admin/audit-logs' : tab === 'verifications' ? '/admin/verifications' : tab === 'reliability' ? '/admin/reliability' : tab === 'feedback' ? '/admin/feedback' : tab === 'appeals' ? '/admin/appeals' : '/admin/reports'

  const load = useCallback(async ({ silent = false } = {}) => {
    if (refreshingRef.current) return

    refreshingRef.current = true
    if (!silent) setError('')
    try {
      const requests = tab === 'overview' ? [api.get('/admin/dashboard')] : [api.get('/admin/dashboard'), api.get(endpoint)]
      const responses = await Promise.all(requests)
      setDashboard(responses[0].data.data)
      if (responses[1]) {
        const payload = responses[1].data.data
        if (tab === 'payments') setPaymentData(payload)
        else if (tab === 'verifications') setVerificationData(payload)
        else if (tab === 'reliability') setReliabilityData(payload)
        else setItems(payload.data || [])
      }
      setLastRefreshed(new Date())
    } catch (requestError) {
      if (!silent) setError(errorMessage(requestError))
    } finally {
      refreshingRef.current = false
    }
  }, [endpoint, errorMessage, tab])

  useEffect(() => { if (isAdmin) load() }, [isAdmin, load])

  useEffect(() => {
    if (!isAdmin) return undefined

    const refreshWhenVisible = () => {
      if (document.visibilityState === 'visible' && !busy) load({ silent: true })
    }
    const interval = window.setInterval(refreshWhenVisible, 30000)
    document.addEventListener('visibilitychange', refreshWhenVisible)

    return () => {
      window.clearInterval(interval)
      document.removeEventListener('visibilitychange', refreshWhenVisible)
    }
  }, [busy, isAdmin, load])

  if (loading) return <main className="admin-login"><p>Loading administrator access…</p></main>
  if (!isAdmin) return <Navigate to="/admin/login" replace />

  const action = async (path, payload) => {
    const confirmation = confirmationForAction(path, payload)
    if (confirmation && !await confirm(confirmation)) return
    setBusy(path)
    try {
      await api.patch(path, payload)
      await load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  const signOut = async () => {
    await logout()
    navigate('/admin/login')
  }

  return <div className="admin-shell"><aside>
    <div className="admin-brand">Talent<span>Xpanse</span><small>Operations</small></div>
    <nav>{[['overview', 'Overview'], ['feedback', 'Beta feedback'], ['appeals', 'Appeals'], ['reports', 'Reports'], ['support', 'Project support'], ['reliability', 'Reliability'], ['verifications', 'Verifications'], ['payments', 'Payment safety'], ['audit', 'Audit trail'], ['jobs', 'Jobs'], ['users', 'Users']].map(([value, title]) => <button key={value} className={tab === value ? 'active' : ''} onClick={() => setTab(value)}>{title}</button>)}</nav>
    <div className="admin-account"><b>{user.name}</b><small>{user.email}</small><button onClick={() => navigate('/settings/security')}>Security & MFA</button><button onClick={signOut}>Log out</button></div>
  </aside><main>
    <header><div><p className="eyebrow">Administrator console</p><h1>{tab === 'overview' ? 'Marketplace overview' : label(tab)}</h1></div><div className="admin-header-actions"><small>{lastRefreshed ? `Auto-updated ${lastRefreshed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'Loading current data'}</small><span className="admin-status">Live monitoring</span></div></header>
    {error && <p className="form-notice">{error}</p>}
    {!dashboard ? <p className="admin-loading">Loading operational data…</p> : <>
      {tab === 'overview' && <><section className="admin-metrics">{[['Users', dashboard.users], ['Open jobs', dashboard.open_jobs], ['Proposals', dashboard.proposals], ['Active contracts', dashboard.active_contracts], ['New feedback', dashboard.new_feedback], ['Open appeals', dashboard.open_appeals], ['Content reports', dashboard.open_reports], ['Project support', dashboard.open_support_requests], ['Reliability backlog', dashboard.pending_reliability_cases], ['Payment holds', dashboard.payment_holds], ['Audit entries', dashboard.audit_entries], ['Suspended users', dashboard.suspended_users]].map(([name, value]) => <article key={name}><small>{name}</small><b>{value}</b></article>)}</section><section className="admin-funnel"><header><div><p className="eyebrow">Product funnel</p><h2>{dashboard.funnel?.period_label || 'Last 30 days'}</h2><p>Operational product events only—no message, CV, verification, or payment contents are stored here.</p></div></header><div>{[['Registered', dashboard.funnel?.registered], ['Profile updates', dashboard.funnel?.profiles_updated], ['Jobs posted', dashboard.funnel?.jobs_posted], ['Proposals sent', dashboard.funnel?.proposals_submitted], ['Contracts started', dashboard.funnel?.contracts_started]].map(([label, value]) => <article key={label}><small>{label}</small><b>{value || 0}</b></article>)}</div></section></>}

      {tab === 'feedback' && <section className="admin-table"><p>Beta feedback is private to the operations team. Mark every item with an honest next step so contributors know it was handled.</p>{items.length ? <table><thead><tr><th>Feedback</th><th>Area</th><th>Member</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((feedback) => <tr key={feedback.id}><td><p className="report-preview">{feedback.message}</p>{feedback.rating && <small>{'★'.repeat(feedback.rating)} · {new Date(feedback.created_at).toLocaleDateString()}</small>}</td><td>{label(feedback.area)}</td><td>{feedback.user?.name || 'Deleted user'}</td><td><span className={`admin-pill ${feedback.status}`}>{label(feedback.status)}</span></td><td><select disabled={busy === `/admin/feedback/${feedback.id}`} value={feedback.status} onChange={(event) => action(`/admin/feedback/${feedback.id}`, { status: event.target.value })}><option value="new">New</option><option value="reviewed">Reviewed</option><option value="planned">Planned</option><option value="resolved">Resolved</option></select></td></tr>)}</tbody></table> : <p className="admin-empty">No beta feedback yet.</p>}</section>}

      {tab === 'appeals' && <section className="admin-table"><p>Appeals are a second review of a confirmed reliability decision. Record a clear resolution note; changing the underlying reliability score remains a deliberate operations action.</p>{items.length ? <table><thead><tr><th>Member</th><th>Appeal</th><th>Decision</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((appeal) => <tr key={appeal.id}><td><b>{appeal.user?.name || 'Removed user'}</b><small>{appeal.user?.email}</small></td><td><p className="report-preview">{appeal.reason}</p></td><td>{appeal.reliability_event?.event_type ? label(appeal.reliability_event.event_type) : 'Removed decision'}</td><td><span className={`admin-pill ${appeal.status}`}>{label(appeal.status)}</span></td><td><div><select disabled={busy === `/admin/appeals/${appeal.id}`} value={appeal.status} onChange={(event) => action(`/admin/appeals/${appeal.id}`, { status: event.target.value, resolution_note: event.target.value === 'under_review' ? null : 'Appeal reviewed by TalentXpanse operations.' })}><option value="open">Open</option><option value="under_review">Under review</option><option value="upheld">Upheld</option><option value="adjusted">Adjusted</option><option value="dismissed">Dismissed</option></select></div></td></tr>)}</tbody></table> : <p className="admin-empty">No reliability appeals are waiting.</p>}</section>}

      {tab === 'reports' && <section className="admin-table"><p>Review the reported item before resolving the report. Account and content actions remain deliberate, separate decisions.</p>
        {items.length ? <table><thead><tr><th>Reported item</th><th>Reason</th><th>Reporter</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((report) => <tr key={report.id}>
          <td><b>{report.target_preview?.title || `${label(report.target_type)} #${report.target_id}`}</b><small>{report.target_preview?.subtitle}</small><p className="report-preview">{report.target_preview?.excerpt || 'This item is no longer available.'}</p></td>
          <td>{label(report.reason)}{report.details && <small>{report.details}</small>}</td>
          <td>{report.reporter?.name}<small>{report.reviewed_at ? `Reviewed by ${report.reviewer?.name || 'administrator'}` : 'Not reviewed yet'}</small></td>
          <td><span className={`admin-pill ${report.status}`}>{report.status}</span></td>
          <td><AdminReportActions report={report} busy={busy} onAction={action} /></td>
        </tr>)}</tbody></table> : <p className="admin-empty">No reports need review.</p>}
      </section>}

      {tab === 'support' && <section className="admin-table"><p>Project support is not a payment dispute yet. Review the context, communicate a fair next step, and leave a clear closing note for both project partners.</p>
        {items.length ? <table><thead><tr><th>Project</th><th>Request</th><th>Opened by</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((request) => <tr key={request.id}>
          <td><b>{request.contract?.title || 'Removed project'}</b><small>{request.contract?.client?.client_profile?.company_name || request.contract?.client?.name} · {request.contract?.freelancer?.name}</small></td>
          <td><b>{label(request.reason)}</b><p className="report-preview">{request.details}</p></td>
          <td>{request.opener?.name}<small>{request.handled_at ? `Handled by ${request.handler?.name || 'administrator'}` : 'Not assigned yet'}</small></td>
          <td><span className={`admin-pill ${request.status}`}>{label(request.status)}</span></td>
          <td><div>{request.status === 'open' && <button disabled={busy === `/admin/support-requests/${request.id}`} onClick={() => action(`/admin/support-requests/${request.id}`, { status: 'under_review' })}>Start review</button>}{request.status === 'under_review' && <><textarea aria-label={`Resolution note for support request ${request.id}`} value={resolutionNotes[request.id] || ''} onChange={(event) => setResolutionNotes({ ...resolutionNotes, [request.id]: event.target.value })} placeholder="Closing note for both partners" maxLength="2000" /><button disabled={busy === `/admin/support-requests/${request.id}` || !(resolutionNotes[request.id] || '').trim()} onClick={() => action(`/admin/support-requests/${request.id}`, { status: 'resolved', resolution_note: resolutionNotes[request.id] })}>Resolve</button><button disabled={busy === `/admin/support-requests/${request.id}` || !(resolutionNotes[request.id] || '').trim()} onClick={() => action(`/admin/support-requests/${request.id}`, { status: 'dismissed', resolution_note: resolutionNotes[request.id] })}>Dismiss</button></>}</div></td>
        </tr>)}</tbody></table> : <p className="admin-empty">No project support requests need review.</p>}
      </section>}

      {tab === 'payments' && <AdminPaymentSafetyPanel data={paymentData} busy={busy} onAction={action} />}

      {tab === 'verifications' && <AdminVerificationPanel data={verificationData} busy={busy} onAction={action} />}

      {tab === 'reliability' && <AdminReliabilityPanel data={reliabilityData} busy={busy} onAction={action} />}

      {tab === 'audit' && <AdminAuditTrail entries={items} />}

      {tab === 'jobs' && <section className="admin-table"><p>Pause or close problematic job posts. Contract jobs cannot be changed here.</p>
        {items.length ? <table><thead><tr><th>Job</th><th>Client</th><th>Proposals</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((job) => <tr key={job.id}><td>{job.title}</td><td>{job.client?.client_profile?.company_name || job.client?.name}</td><td>{job.proposals_count}</td><td><span className={`admin-pill ${job.status}`}>{label(job.status)}</span></td><td><div>{job.status === 'open' && <button disabled={busy === `/admin/jobs/${job.id}`} onClick={() => action(`/admin/jobs/${job.id}`, { status: 'paused' })}>Pause</button>}{['open', 'paused'].includes(job.status) && <button disabled={busy === `/admin/jobs/${job.id}`} onClick={() => action(`/admin/jobs/${job.id}`, { status: 'closed' })}>Close</button>}</div></td></tr>)}</tbody></table> : <p className="admin-empty">No jobs match this view.</p>}
      </section>}

      {tab === 'users' && <section className="admin-table"><p>Suspending an account prevents sign-in. Never suspend your own administrator account.</p>
        {items.length ? <table><thead><tr><th>User</th><th>Roles</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((account) => <tr key={account.id}><td><b>{account.name}</b><small>{account.email}</small></td><td>{account.roles?.map((role) => role.name).join(', ')}</td><td>{new Date(account.created_at).toLocaleDateString()}</td><td><span className={`admin-pill ${account.status}`}>{account.status}</span></td><td>{account.id !== user.id && <button disabled={busy === `/admin/users/${account.id}`} onClick={() => action(`/admin/users/${account.id}`, { status: account.status === 'active' ? 'suspended' : 'active' })}>{account.status === 'active' ? 'Suspend' : 'Restore'}</button>}</td></tr>)}</tbody></table> : <p className="admin-empty">No user accounts found.</p>}
      </section>}
    </>}
  </main></div>
}
