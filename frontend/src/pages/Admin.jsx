import { useEffect, useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import AdminPaymentSafetyPanel from '../components/AdminPaymentSafetyPanel'
import AdminAuditTrail from '../components/AdminAuditTrail'
import AdminVerificationPanel from '../components/AdminVerificationPanel'
import '../admin.css'

const label = (value) => String(value || '').replaceAll('_', ' ')

export function AdminLoginScreen() {
  const { adminLogin, errorMessage, user } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  if (user?.roles?.includes('admin')) return <Navigate to="/admin" replace />

  const submit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      await adminLogin(form)
      navigate('/admin')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  return <main className="admin-login"><section>
    <p className="eyebrow">TalentXpanse operations</p>
    <h1>Administrator sign in</h1>
    <p>Access is limited to company-provisioned administrator accounts.</p>
    {error && <p className="form-notice">{error}</p>}
    <form onSubmit={submit}>
      <label>Work email<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
      <label>Password<input required type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></label>
      <button disabled={busy} className="button button-primary">{busy ? 'Signing in…' : 'Sign in to admin'}</button>
    </form>
    <small>There is no public administrator registration.</small>
  </section></main>
}

export function AdminDashboardScreen() {
  const { user, loading, logout, errorMessage } = useAuth()
  const navigate = useNavigate()
  const [tab, setTab] = useState('overview')
  const [dashboard, setDashboard] = useState(null)
  const [items, setItems] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(null)
  const [resolutionNotes, setResolutionNotes] = useState({})
  const [paymentData, setPaymentData] = useState(null)
  const [verificationData, setVerificationData] = useState(null)
  const isAdmin = user?.roles?.includes('admin')
  const endpoint = tab === 'users' ? '/admin/users' : tab === 'jobs' ? '/admin/jobs' : tab === 'support' ? '/admin/support-requests' : tab === 'payments' ? '/admin/payment-records' : tab === 'audit' ? '/admin/audit-logs' : tab === 'verifications' ? '/admin/verifications' : '/admin/reports'

  const load = async () => {
    setError('')
    try {
      const requests = tab === 'overview' ? [api.get('/admin/dashboard')] : [api.get('/admin/dashboard'), api.get(endpoint)]
      const responses = await Promise.all(requests)
      setDashboard(responses[0].data.data)
      if (responses[1]) {
        const payload = responses[1].data.data
        if (tab === 'payments') setPaymentData(payload)
        else if (tab === 'verifications') setVerificationData(payload)
        else setItems(payload.data || [])
      }
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  useEffect(() => { if (isAdmin) load() }, [tab, isAdmin])

  if (loading) return <main className="admin-login"><p>Loading administrator access…</p></main>
  if (!isAdmin) return <Navigate to="/admin/login" replace />

  const action = async (path, payload) => {
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
    <nav>{[['overview', 'Overview'], ['reports', 'Reports'], ['support', 'Project support'], ['verifications', 'Verifications'], ['payments', 'Payment safety'], ['audit', 'Audit trail'], ['jobs', 'Jobs'], ['users', 'Users']].map(([value, title]) => <button key={value} className={tab === value ? 'active' : ''} onClick={() => setTab(value)}>{title}</button>)}</nav>
    <div className="admin-account"><b>{user.name}</b><small>{user.email}</small><button onClick={signOut}>Log out</button></div>
  </aside><main>
    <header><div><p className="eyebrow">Administrator console</p><h1>{tab === 'overview' ? 'Marketplace overview' : label(tab)}</h1></div><span className="admin-status">Platform monitoring</span></header>
    {error && <p className="form-notice">{error}</p>}
    {!dashboard ? <p className="admin-loading">Loading operational data…</p> : <>
      {tab === 'overview' && <section className="admin-metrics">{[['Users', dashboard.users], ['Open jobs', dashboard.open_jobs], ['Proposals', dashboard.proposals], ['Active contracts', dashboard.active_contracts], ['Content reports', dashboard.open_reports], ['Project support', dashboard.open_support_requests], ['Payment holds', dashboard.payment_holds], ['Audit entries', dashboard.audit_entries], ['Suspended users', dashboard.suspended_users]].map(([name, value]) => <article key={name}><small>{name}</small><b>{value}</b></article>)}</section>}

      {tab === 'reports' && <section className="admin-table"><p>Review the reported item before resolving the report. Account and content actions remain deliberate, separate decisions.</p>
        {items.length ? <table><thead><tr><th>Reported item</th><th>Reason</th><th>Reporter</th><th>Status</th><th>Action</th></tr></thead><tbody>{items.map((report) => <tr key={report.id}>
          <td><b>{report.target_preview?.title || `${label(report.target_type)} #${report.target_id}`}</b><small>{report.target_preview?.subtitle}</small><p className="report-preview">{report.target_preview?.excerpt || 'This item is no longer available.'}</p></td>
          <td>{label(report.reason)}{report.details && <small>{report.details}</small>}</td>
          <td>{report.reporter?.name}<small>{report.reviewed_at ? `Reviewed by ${report.reviewer?.name || 'administrator'}` : 'Not reviewed yet'}</small></td>
          <td><span className={`admin-pill ${report.status}`}>{report.status}</span></td>
          <td><div>{report.status === 'open' && <button disabled={busy === `/admin/reports/${report.id}`} onClick={() => action(`/admin/reports/${report.id}`, { status: 'reviewed' })}>Review</button>}{report.status === 'reviewed' && <button disabled={busy === `/admin/reports/${report.id}`} onClick={() => action(`/admin/reports/${report.id}`, { status: 'resolved' })}>Resolve</button>}{['open', 'reviewed'].includes(report.status) && <button disabled={busy === `/admin/reports/${report.id}`} onClick={() => action(`/admin/reports/${report.id}`, { status: 'dismissed' })}>Dismiss</button>}</div></td>
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
