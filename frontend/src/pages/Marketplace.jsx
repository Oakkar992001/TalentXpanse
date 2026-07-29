import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate, useOutletContext, useParams, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import MarketplaceSaveButton from '../components/MarketplaceSaveButton'
import MarketplaceReportButton from '../components/MarketplaceReportButton'

const formatMMK = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const trustText = (summary) => summary?.review_count ? `★ ${summary.average_rating} (${summary.review_count})` : summary?.completed_projects_count ? `${summary.completed_projects_count} completed` : 'New on TalentXpanse'
const roleLabel = (role) => role === 'client' ? 'Hire talent' : 'Find work'
const proposalCreditCost = (job) => {
  const budget = Number(job?.budget_max || job?.budget_min || 0)
  if (budget > 500000) return 4
  if (budget >= 100000) return 2
  return 1
}

function Notice({ children }) { return <p className="form-notice">{children}</p> }
function Avatar({ name, photoUrl }) { return <span className="avatar">{photoUrl ? <img src={photoUrl} alt="" /> : name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'}</span> }

function GoogleButton({ disabled, onCredential, onError }) {
  const container = useRef(null)
  const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID

  useEffect(() => {
    if (!clientId || !container.current) return undefined
    const initialize = () => {
      window.google.accounts.id.initialize({ client_id: clientId, callback: ({ credential }) => onCredential(credential), use_fedcm_for_button: true })
      container.current.replaceChildren()
      window.google.accounts.id.renderButton(container.current, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular',
        logo_alignment: 'left',
        locale: 'en-US',
        width: 320,
      })
    }
    const script = document.querySelector('script[data-google-identity]')
    if (script) {
      script.addEventListener('load', initialize)
      if (window.google) initialize()
      return () => script.removeEventListener('load', initialize)
    }
    const googleScript = document.createElement('script')
    googleScript.src = 'https://accounts.google.com/gsi/client?hl=en'
    googleScript.async = true
    googleScript.defer = true
    googleScript.dataset.googleIdentity = 'true'
    googleScript.onload = initialize
    googleScript.onerror = () => onError('Google Sign-In could not load. Check your internet connection and try again.')
    document.head.appendChild(googleScript)
    return () => { googleScript.onload = null; googleScript.onerror = null }
  }, [clientId, onCredential, onError])

  if (!clientId) return <button type="button" disabled><b className="google-mark">G</b>Google unavailable</button>
  return <div className={disabled ? 'google-button-disabled' : ''} ref={container} />
}

export function AuthScreen({ mode }) {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const { login, register, googleLogin, errorMessage } = useAuth()
  const [role, setRole] = useState(() => params.get('role') === 'client' ? 'client' : 'freelancer')
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const isLogin = mode === 'login'
  const nextPath = params.get('next')?.startsWith('/') ? params.get('next') : null

  const submit = async (event) => {
    event.preventDefault()
    setError(''); setBusy(true)
    try {
      const user = isLogin ? await login({ email: form.email, password: form.password }) : await register({ ...form, role })
      navigate(isLogin && nextPath ? nextPath : isLogin ? `/dashboard?role=${user.roles.includes(role) ? role : user.roles[0]}` : `/workspace-setup?role=${role}`)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const signInWithGoogle = useCallback(async (credential) => {
    setError(''); setBusy(true)
    try {
      const user = await googleLogin({ credential, role })
      navigate(nextPath || `/dashboard?role=${user.roles.includes(role) ? role : user.roles[0]}`)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }, [errorMessage, googleLogin, navigate, nextPath, role])

  return <section className="auth-page"><div className="auth-panel"><div className="auth-card"><p className="eyebrow">{isLogin ? 'Welcome back' : 'Create your account'}</p><h1>{isLogin ? 'Continue your journey.' : 'How would you like to use TalentXpanse?'}</h1><p className="auth-intro">{isLogin ? 'Sign in to manage your work and opportunities.' : 'Start with one path today. You can add the other profile later.'}</p>
    <form onSubmit={submit}>
      {!isLogin && <div className="role-cards"><button type="button" className={role === 'freelancer' ? 'selected' : ''} onClick={() => setRole('freelancer')}><span>✦</span><div><b>Find work</b><small>Build your profile, discover projects, and send proposals.</small></div><i /></button><button type="button" className={role === 'client' ? 'selected' : ''} onClick={() => setRole('client')}><span>▣</span><div><b>Hire talent</b><small>Post a job, discover skilled people, and hire with confidence.</small></div><i /></button></div>}
      <div className="auth-fields">
        {!isLogin && <label>Full name<input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Your name" /></label>}
        <label>Email<input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="you@example.com" /></label>
        <label>Password<input required minLength="8" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="At least 8 characters" /></label>
        {!isLogin && <label>Confirm password<input required type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} placeholder="Repeat your password" /></label>}
      </div>
      {error && <Notice>{error}</Notice>}
      <button disabled={busy} className="button button-primary auth-continue">{busy ? 'Please wait…' : isLogin ? 'Log in' : `Continue as ${roleLabel(role)}`}</button>
    </form>{isLogin && <p className="auth-forgot"><Link to="/forgot-password">Forgot password?</Link></p>}
    <div className="auth-divider"><span>or continue with</span></div><div className="google-sign-in"><div className="google-sign-in-copy"><span className="google-symbol" aria-hidden="true">G</span><div><b>Continue with Google</b><small>Fast, secure sign-in — no extra password needed.</small></div></div><GoogleButton disabled={busy} onCredential={signInWithGoogle} onError={setError} /></div><p className="auth-switch">{isLogin ? 'New to TalentXpanse?' : 'Already have an account?'} <Link to={isLogin ? '/register' : '/login'}>{isLogin ? 'Sign up' : 'Log in'}</Link></p>
  </div></div><aside className="auth-art"><div className="art-card large"><span>▣</span><b>Projects that matter</b><small>Find the right people and the right work.</small></div><div className="art-card small"><Avatar name="Nandar Win" /><b>Build lasting relationships.</b></div><div className="art-figure">✦</div></aside></section>
}

export function JobsScreen() {
  const [jobs, setJobs] = useState([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const load = async (term = '') => {
    setLoading(true)
    try { const { data } = await api.get('/jobs', { params: term ? { search: term } : {} }); setJobs(data.data.data) } catch { setError('Jobs could not be loaded. Please start Laravel and try again.') } finally { setLoading(false) }
  }
  useEffect(() => { load() }, [])
  return <section className="marketplace-page"><header><p className="eyebrow">Find work</p><h1>Work that fits your expertise.</h1><p>Explore verified opportunities from businesses building in Myanmar and beyond.</p><form className="job-search" onSubmit={(e) => { e.preventDefault(); load(search) }}><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search jobs, skills, or keywords" /><button className="button button-primary">Search</button></form></header>{error && <Notice>{error}</Notice>}{loading ? <p>Loading opportunities…</p> : <div className="live-job-grid">{jobs.map((job) => <article key={job.id} className="live-job-card"><p>{job.category}</p><h2>{job.title}</h2><small>{job.client?.client_profile?.company_name || job.client?.name} · {trustText(job.client?.trust_summary)} · {job.duration || 'Flexible'}</small><div>{job.skills?.map((skill) => <span className="tag" key={skill}>{skill}</span>)}</div><strong>{job.budget_type === 'hourly' ? `${formatMMK(job.budget_min)}/hr` : `${formatMMK(job.budget_min)} – ${formatMMK(job.budget_max)}`}</strong><footer><span>{job.proposals_count} proposals</span><Link to={`/jobs/${job.id}`}>View job →</Link></footer></article>)}</div>}{!loading && !jobs.length && <p>No open jobs match that search yet.</p>}</section>
}

function LegacyJobDetailScreen() {
  const { id } = useParams()
  const { user, errorMessage } = useAuth()
  const navigate = useNavigate()
  const [job, setJob] = useState(null)
  const [form, setForm] = useState({ cover_letter: '', bid_amount: '', delivery_days: '' })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { api.get(`/jobs/${id}`).then(({ data }) => setJob(data.data)).catch(() => setError('This job is no longer available.')) }, [id])
  const submit = async (event) => {
    event.preventDefault()
    if (!user) { navigate('/login'); return }
    setBusy(true); setError(''); setMessage('')
    try { await api.post(`/jobs/${id}/proposals`, { ...form, bid_amount: Number(form.bid_amount), delivery_days: form.delivery_days ? Number(form.delivery_days) : null }); setMessage('Your proposal has been submitted.') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  if (error && !job) return <section className="simple-page"><h1>Job unavailable</h1><Notice>{error}</Notice><Link className="button button-primary" to="/jobs">Browse jobs</Link></section>
  if (!job) return <section className="simple-page"><p>Loading job…</p></section>
  return <section className="marketplace-page job-detail"><Link to="/jobs">← All jobs</Link><article className="job-detail-card"><p className="eyebrow">{job.category}</p><h1>{job.title}</h1><p>{job.description}</p><div>{job.skills?.map((skill) => <span className="tag" key={skill}>{skill}</span>)}</div><dl><div><dt>Budget</dt><dd>{formatMMK(job.budget_min)} – {formatMMK(job.budget_max)}</dd></div><div><dt>Experience</dt><dd>{job.experience_level}</dd></div><div><dt>Proposals</dt><dd>{job.proposals_count}</dd></div></dl></article>{user?.roles?.includes('freelancer') ? <form className="proposal-form" onSubmit={submit}><h2>Submit a proposal</h2><label>Your proposal<textarea required minLength="40" value={form.cover_letter} onChange={(e) => setForm({ ...form, cover_letter: e.target.value })} placeholder="Explain why you are a great fit for this project." /></label><div><label>Your bid (MMK)<input required min="1000" type="number" value={form.bid_amount} onChange={(e) => setForm({ ...form, bid_amount: e.target.value })} /></label><label>Delivery days<input min="1" type="number" value={form.delivery_days} onChange={(e) => setForm({ ...form, delivery_days: e.target.value })} /></label></div>{message && <Notice>{message}</Notice>}{error && <Notice>{error}</Notice>}<button disabled={busy} className="button button-primary">{busy ? 'Submitting…' : 'Submit proposal'}</button></form> : <aside className="job-cta"><h2>Want to apply?</h2><p>Sign in as a freelancer to submit a proposal.</p><Link className="button button-primary" to={user ? '/dashboard?role=freelancer' : '/register'}>{user ? 'Add freelancer role' : 'Create an account'}</Link></aside>}</section>
}

function ProposalCreditJobDetailScreen() {
  const { id } = useParams()
  const { user, errorMessage } = useAuth()
  const navigate = useNavigate()
  const [job, setJob] = useState(null)
  const [proposals, setProposals] = useState([])
  const [creditInfo, setCreditInfo] = useState(null)
  const [_portfolio, setPortfolio] = useState([])
  const [_resume, setResume] = useState(null)
  const [form, setForm] = useState({ cover_letter: '', bid_amount: '', delivery_days: '', portfolio_item_ids: [], attach_resume: false })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [actionId, setActionId] = useState(null)
  const ownsJob = Boolean(job && user?.id === job.client_id)
  const isFreelancer = user?.roles?.includes('freelancer')

  const loadJob = async () => {
    try {
      const { data } = await api.get(`/jobs/${id}`)
      setJob(data.data)
    } catch {
      setError('This job is no longer available.')
    }
  }

  const loadProposals = async () => {
    try {
      const { data } = await api.get(`/jobs/${id}/proposals`)
      setProposals(data.data)
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  useEffect(() => { loadJob() }, [id])

  useEffect(() => {
    if (!isFreelancer) return
    api.get('/proposal-credits').then(({ data }) => setCreditInfo(data.data)).catch(() => setCreditInfo(null))
    api.get('/freelancer-profile').then(({ data }) => { setPortfolio(data.data.portfolio_items || []); setResume(data.data.freelancer_resume) }).catch(() => { setPortfolio([]); setResume(null) })
  }, [user?.id, isFreelancer])

  useEffect(() => {
    if (ownsJob) loadProposals()
    else setProposals([])
  }, [ownsJob, id])

  const submit = async (event) => {
    event.preventDefault()
    if (!user) { navigate('/login'); return }
    setBusy(true); setError(''); setMessage('')
    try {
      const { data } = await api.post(`/jobs/${id}/proposals`, { ...form, bid_amount: Number(form.bid_amount), delivery_days: form.delivery_days ? Number(form.delivery_days) : null })
      setCreditInfo(data.proposal_credits)
      setMessage(`Proposal submitted. ${data.data.credit_cost} Proposal Credits were used.`)
      setForm({ cover_letter: '', bid_amount: '', delivery_days: '', portfolio_item_ids: [], attach_resume: false })
      loadJob()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const decideProposal = async (proposalId, status) => {
    setActionId(proposalId); setError(''); setMessage('')
    try {
      await api.patch(`/proposals/${proposalId}`, { status })
      setMessage(status === 'hired' ? 'Freelancer hired. This job is now in progress.' : `Proposal ${status}.`)
      await loadJob()
      await loadProposals()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setActionId(null)
    }
  }

  const _togglePortfolioItem = (itemId) => {
    const selected = form.portfolio_item_ids.includes(itemId)
    if (!selected && form.portfolio_item_ids.length === 3) return
    setForm({ ...form, portfolio_item_ids: selected ? form.portfolio_item_ids.filter((id) => id !== itemId) : [...form.portfolio_item_ids, itemId] })
  }

  const _downloadResume = async (proposal) => {
    try {
      const response = await api.get(`/proposals/${proposal.id}/resume`, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a'); link.href = url; link.download = proposal.resume_name || 'cv.pdf'; link.click(); URL.revokeObjectURL(url)
    } catch (requestError) { setError(errorMessage(requestError)) }
  }

  if (error && !job) return <section className="simple-page"><h1>Job unavailable</h1><Notice>{error}</Notice><Link className="button button-primary" to="/jobs">Browse jobs</Link></section>
  if (!job) return <section className="simple-page"><p>Loading job...</p></section>

  const cost = proposalCreditCost(job)
  const canApply = isFreelancer && job.status === 'open' && !ownsJob

  return <section className="marketplace-page job-detail">
    <Link to="/jobs">← All jobs</Link>
    <article className="job-detail-card">
      <p className="eyebrow">{job.category} · {job.status.replace('_', ' ')}</p>
      <h1>{job.title}</h1><p>{job.description}</p>
      <div>{job.skills?.map((skill) => <span className="tag" key={skill}>{skill}</span>)}</div>
      <dl><div><dt>Budget</dt><dd>{formatMMK(job.budget_min)} – {formatMMK(job.budget_max)}</dd></div><div><dt>Experience</dt><dd>{job.experience_level}</dd></div><div><dt>Proposals</dt><dd>{job.proposals_count}</dd></div></dl>
    </article>
    {message && <Notice>{message}</Notice>}{error && <Notice>{error}</Notice>}
    {ownsJob && <section className="client-proposals"><div className="section-heading"><div><p className="eyebrow">Client workspace</p><h2>Review proposals</h2></div><span>{proposals.length} received</span></div>{proposals.length ? proposals.map((proposal) => <article className="client-proposal" key={proposal.id}><Avatar name={proposal.freelancer?.name} /><div className="client-proposal-main"><div><b>{proposal.freelancer?.name}</b><span className={`proposal-status ${proposal.status}`}>{proposal.status}</span></div><small>{proposal.freelancer?.freelancer_profile?.title || 'Freelancer'}</small><p>{proposal.cover_letter}</p></div><div className="client-proposal-offer"><b>{formatMMK(proposal.bid_amount)}</b><small>{proposal.delivery_days || 'Flexible'} days</small>{job.status === 'open' && proposal.status !== 'declined' && <div className="proposal-actions"><button type="button" onClick={() => decideProposal(proposal.id, 'shortlisted')} disabled={actionId === proposal.id}>Shortlist</button><button type="button" className="decline" onClick={() => decideProposal(proposal.id, 'declined')} disabled={actionId === proposal.id}>Decline</button><button type="button" className="hire" onClick={() => decideProposal(proposal.id, 'hired')} disabled={actionId === proposal.id}>{actionId === proposal.id ? 'Working...' : 'Hire'}</button></div>}</div></article>) : <p className="empty-panel">No proposals yet. New proposals will appear here.</p>}</section>}
    {canApply && <form className="proposal-form" onSubmit={submit}><div className="proposal-form-heading"><div><h2>Submit a proposal</h2><p>Show the client why you are the right person for this work.</p></div><div className="credit-balance"><b>{creditInfo ? creditInfo.balance : '–'}</b><span>Proposal Credits</span></div></div><div className="credit-cost"><span>This proposal costs <b>{cost} credits</b></span><small>20 credits are granted monthly. Unused credits roll over up to 40.</small></div><label>Your proposal<textarea required minLength="40" value={form.cover_letter} onChange={(e) => setForm({ ...form, cover_letter: e.target.value })} placeholder="Explain why you are a great fit for this project." /></label><div><label>Your bid (MMK)<input required min="1000" type="number" value={form.bid_amount} onChange={(e) => setForm({ ...form, bid_amount: e.target.value })} /></label><label>Delivery days<input min="1" type="number" value={form.delivery_days} onChange={(e) => setForm({ ...form, delivery_days: e.target.value })} /></label></div><button disabled={busy} className="button button-primary">{busy ? 'Submitting...' : `Submit proposal · ${cost} credits`}</button></form>}
    {!ownsJob && !canApply && <aside className="job-cta"><h2>{job.status === 'open' ? 'Want to apply?' : 'This job is in progress'}</h2><p>{user ? 'Add the Freelancer role to submit a proposal.' : 'Sign in as a freelancer to submit a proposal.'}</p><Link className="button button-primary" to={user ? '/dashboard?role=freelancer' : '/register'}>{user ? 'Add freelancer role' : 'Create an account'}</Link></aside>}
  </section>
}

export function JobDetailScreen() {
  const { id } = useParams()
  const { pathname } = useLocation()
  const { user, errorMessage } = useAuth()
  const navigate = useNavigate()
  const [job, setJob] = useState(null)
  const [proposals, setProposals] = useState([])
  const [credits, setCredits] = useState(null)
  const [portfolio, setPortfolio] = useState([])
  const [resume, setResume] = useState(null)
  const [form, setForm] = useState({ cover_letter: '', bid_amount: '', delivery_days: '', portfolio_item_ids: [], attach_resume: false })
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [actionId, setActionId] = useState(null)
  const isFreelancer = user?.roles?.includes('freelancer')
  const ownsJob = Boolean(job && user?.id === job.client_id)
  const browseJobsPath = pathname.startsWith('/search/') ? '/search' : '/jobs'

  const refreshJob = () => api.get(`/jobs/${id}`).then(({ data }) => setJob(data.data)).catch(() => setError('This job is no longer available.'))
  const refreshProposals = () => api.get(`/jobs/${id}/proposals`).then(({ data }) => setProposals(data.data)).catch((requestError) => setError(errorMessage(requestError)))

  useEffect(() => { refreshJob() }, [id])
  useEffect(() => {
    if (!isFreelancer) return
    api.get('/proposal-credits').then(({ data }) => setCredits(data.data)).catch(() => setCredits(null))
    api.get('/freelancer-profile').then(({ data }) => { setPortfolio(data.data.portfolio_items || []); setResume(data.data.freelancer_resume) })
  }, [user?.id, isFreelancer])
  useEffect(() => { if (ownsJob) refreshProposals() }, [ownsJob, id])

  const submit = async (event) => {
    event.preventDefault()
    if (!user) { navigate('/login'); return }
    setBusy(true); setError(''); setNotice('')
    try {
      const { data } = await api.post(`/jobs/${id}/proposals`, { ...form, bid_amount: Number(form.bid_amount), delivery_days: form.delivery_days ? Number(form.delivery_days) : null })
      setCredits(data.proposal_credits)
      setNotice(`Proposal sent with ${data.data.credit_cost} Proposal Credits used.`)
      setForm({ cover_letter: '', bid_amount: '', delivery_days: '', portfolio_item_ids: [], attach_resume: false })
      refreshJob()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const selectWork = (itemId) => {
    const selected = form.portfolio_item_ids.includes(itemId)
    if (!selected && form.portfolio_item_ids.length === 3) return
    setForm({ ...form, portfolio_item_ids: selected ? form.portfolio_item_ids.filter((id) => id !== itemId) : [...form.portfolio_item_ids, itemId] })
  }

  const decide = async (proposalId, status) => {
    const payload = { status }
    if (status === 'declined') {
      const reason = window.prompt('Tell the freelancer why this proposal was not selected. Keep it brief and constructive.')
      if (!reason?.trim()) return
      payload.decline_reason = reason.trim()
    }
    setActionId(proposalId); setError(''); setNotice('')
    try {
      await api.patch(`/proposals/${proposalId}`, payload)
      setNotice(status === 'hired' ? 'Freelancer hired. This job is now in progress.' : `Proposal ${status}.`)
      await refreshJob(); await refreshProposals()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setActionId(null) }
  }

  const downloadCv = async (proposal) => {
    try {
      const response = await api.get(`/proposals/${proposal.id}/resume`, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a'); link.href = url; link.download = proposal.resume_name || 'cv.pdf'; link.click(); URL.revokeObjectURL(url)
    } catch (requestError) { setError(errorMessage(requestError)) }
  }

  if (error && !job) return <section className="simple-page"><h1>Job unavailable</h1><Notice>{error}</Notice><Link className="button button-primary" to={browseJobsPath}>Browse jobs</Link></section>
  if (!job) return <section className="simple-page"><p>Loading job...</p></section>
  const cost = proposalCreditCost(job)
  const canApply = isFreelancer && !ownsJob && job.status === 'open'

  return <section className="marketplace-page job-detail"><div className="marketplace-detail-actions"><Link to={browseJobsPath}>← All jobs</Link><div><MarketplaceSaveButton kind="job" targetId={job.id} /><MarketplaceReportButton targetType="job" targetId={job.id} /></div></div><article className="job-detail-card"><p className="eyebrow">{job.category} · {job.status.replace('_', ' ')}</p><h1>{job.title}</h1><p>{job.description}</p><div>{job.skills?.map((skill) => <span className="tag" key={skill}>{skill}</span>)}</div><dl><div><dt>Budget</dt><dd>{formatMMK(job.budget_min)} – {formatMMK(job.budget_max)}</dd></div><div><dt>Experience</dt><dd>{job.experience_level}</dd></div><div><dt>Proposals</dt><dd>{job.proposals_count}</dd></div></dl></article>{notice && <Notice>{notice}</Notice>}{error && <Notice>{error}</Notice>}
    {ownsJob && <section className="client-proposals"><div className="section-heading"><div><p className="eyebrow">Client workspace</p><h2>Review proposals</h2></div><span>{proposals.length} received</span></div>{proposals.length ? proposals.map((proposal) => <article className="client-proposal" key={proposal.id}><Avatar name={proposal.freelancer?.name} photoUrl={proposal.freelancer?.profile_photo_url} /><div className="client-proposal-main"><div><b>{proposal.freelancer?.name}</b><span className={`proposal-status ${proposal.status}`}>{proposal.status}</span></div><small>{proposal.freelancer?.freelancer_profile?.title || 'Freelancer'}</small><p>{proposal.cover_letter}</p>{proposal.work_samples?.length > 0 && <div className="proposal-samples"><b>Selected work</b>{proposal.work_samples.map((sample) => sample.project_url ? <a key={sample.id} href={sample.project_url} target="_blank" rel="noreferrer">{sample.title} ↗</a> : <span key={sample.id}>{sample.title}</span>)}</div>}{proposal.resume_name && <button type="button" className="resume-link" onClick={() => downloadCv(proposal)}>Download CV (PDF)</button>}</div><div className="client-proposal-offer"><b>{formatMMK(proposal.bid_amount)}</b><small>{proposal.delivery_days || 'Flexible'} days</small>{job.status === 'open' && proposal.status !== 'declined' && <div className="proposal-actions"><button type="button" disabled={actionId === proposal.id} onClick={() => decide(proposal.id, 'shortlisted')}>Shortlist</button><button type="button" className="decline" disabled={actionId === proposal.id} onClick={() => decide(proposal.id, 'declined')}>Decline</button><button type="button" className="hire" disabled={actionId === proposal.id} onClick={() => decide(proposal.id, 'hired')}>{actionId === proposal.id ? 'Working...' : 'Hire'}</button></div>}</div></article>) : <p className="empty-panel">No proposals yet. New proposals will appear here.</p>}</section>}
    {canApply && <form className="proposal-form" onSubmit={submit}><div className="proposal-form-heading"><div><h2>Submit a proposal</h2><p>Choose the work that best supports this application.</p></div><div className="credit-balance"><b>{credits?.balance ?? '–'}</b><span>Proposal Credits</span></div></div><div className="credit-cost"><span>This proposal costs <b>{cost} credits</b></span><small>20 credits are granted monthly. Unused credits roll over up to 40.</small></div>{portfolio.length > 0 && <fieldset className="proposal-attachments"><legend>Attach work samples <small>Select up to 3</small></legend>{portfolio.map((item) => <label key={item.id} className={form.portfolio_item_ids.includes(item.id) ? 'selected' : ''}><input type="checkbox" checked={form.portfolio_item_ids.includes(item.id)} onChange={() => selectWork(item.id)} /><span><b>{item.title}</b><small>{item.description || 'Portfolio work sample'}</small></span></label>)}</fieldset>}{resume && <label className="attach-resume"><input type="checkbox" checked={form.attach_resume} onChange={(event) => setForm({ ...form, attach_resume: event.target.checked })} /> Attach CV: <b>{resume.original_name}</b></label>}<label>Your proposal<textarea required minLength="40" value={form.cover_letter} onChange={(event) => setForm({ ...form, cover_letter: event.target.value })} placeholder="Explain why you are a great fit for this project." /></label><div><label>Your bid (MMK)<input required min="1000" type="number" value={form.bid_amount} onChange={(event) => setForm({ ...form, bid_amount: event.target.value })} /></label><label>Delivery days<input min="1" type="number" value={form.delivery_days} onChange={(event) => setForm({ ...form, delivery_days: event.target.value })} /></label></div><button disabled={busy} className="button button-primary">{busy ? 'Submitting...' : `Submit proposal · ${cost} credits`}</button></form>}
    {!ownsJob && !canApply && <aside className="job-cta"><h2>{job.status === 'open' ? 'Want to apply?' : 'This job is in progress'}</h2><p>{user ? 'Add the Freelancer role to submit a proposal.' : 'Sign in as a freelancer to submit a proposal.'}</p><Link className="button button-primary" to={user ? '/dashboard?role=freelancer' : '/register'}>{user ? 'Add freelancer role' : 'Create an account'}</Link></aside>}
  </section>
}

export function DashboardScreen() {
  const { role } = useOutletContext()
  const [params] = useSearchParams()
  const { user, loading, errorMessage } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [showForm, setShowForm] = useState(() => params.get('postJob') === '1')
  const [jobForm, setJobForm] = useState({ title: '', description: '', category: 'Development & IT', budget_min: '', budget_max: '', duration: 'Less than 1 month', skills: '' })
  const load = () => { if (user) api.get('/dashboard', { params: { role } }).then(({ data: response }) => setData(response.data)).catch((requestError) => setError(errorMessage(requestError))) }
  useEffect(() => { setData(null); setError(''); load() }, [role, user])
  const postJob = async (event) => { event.preventDefault(); setError(''); try { await api.post('/jobs', { ...jobForm, budget_min: Number(jobForm.budget_min), budget_max: Number(jobForm.budget_max), skills: jobForm.skills.split(',').map((skill) => skill.trim()).filter(Boolean), budget_type: 'fixed', experience_level: 'intermediate' }); setShowForm(false); load() } catch (requestError) { setError(errorMessage(requestError)) } }
  if (loading) return <section className="simple-page"><p>Loading your workspace…</p></section>
  if (!user) return <section className="simple-page"><p className="eyebrow">Your workspace</p><h1>Sign in to continue.</h1><p>Your jobs, proposals, and role-specific dashboard live in one place.</p><Link className="button button-primary" to="/login">Log in</Link></section>
  if (error && !data) return <section className="simple-page"><h1>Enable this workspace</h1><Notice>{error}</Notice></section>
  if (!data) return <section className="simple-page"><p>Loading your workspace…</p></section>
  const isClient = data.role === 'client'
  return <div className="dashboard-content"><header className="dashboard-header"><div><p className="eyebrow">{isClient ? 'Client' : 'Freelancer'} workspace</p><h1>Good morning, {user.name}</h1><p>{isClient ? 'Review your jobs and the people ready to help.' : 'Find the next project that moves your career forward.'}</p></div><div className="dashboard-header-actions"><Link className="button button-outline" to={`/work?role=${isClient ? 'client' : 'freelancer'}`}>{isClient ? 'Manage jobs' : 'Track proposals'}</Link>{isClient && <button className="button button-primary" onClick={() => setShowForm(!showForm)}>+ Post a job</button>}</div></header>{error && <Notice>{error}</Notice>}
    {showForm && <form className="post-job-form" onSubmit={postJob}><h2>Post a new job</h2><label>Job title<input required value={jobForm.title} onChange={(e) => setJobForm({ ...jobForm, title: e.target.value })} /></label><label>Project description<textarea required minLength="30" value={jobForm.description} onChange={(e) => setJobForm({ ...jobForm, description: e.target.value })} /></label><div><label>Budget from (MMK)<input required type="number" value={jobForm.budget_min} onChange={(e) => setJobForm({ ...jobForm, budget_min: e.target.value })} /></label><label>Budget to (MMK)<input required type="number" value={jobForm.budget_max} onChange={(e) => setJobForm({ ...jobForm, budget_max: e.target.value })} /></label></div><label>Skills (comma separated)<input value={jobForm.skills} onChange={(e) => setJobForm({ ...jobForm, skills: e.target.value })} placeholder="Laravel, React, MySQL" /></label><button className="button button-primary">Publish job</button></form>}
    <section className="metrics">{Object.entries(data.metrics).map(([key, value]) => <article className="metric" key={key}><span>✦</span><div><small>{key.replaceAll('_', ' ')}</small><b>{key.includes('completeness') ? `${value}%` : value}</b><em>{isClient ? 'Live marketplace data' : 'Updated from your activity'}</em></div></article>)}</section>
    {isClient ? <section className="dashboard-grid"><article className="panel jobs-panel"><div className="panel-title"><h2>Your jobs</h2></div>{data.jobs.map((job) => <div className="open-job" key={job.id}><span>▣</span><div><b>{job.title}</b><small>{formatMMK(job.budget_min)} – {formatMMK(job.budget_max)}</small><em>{job.proposals_count} proposals</em></div><Link to={`/search/jobs/${job.id}`}>→</Link></div>)}</article><article className="panel proposal-panel"><div className="panel-title"><h2>Recent proposals</h2></div>{data.recent_proposals.length ? data.recent_proposals.map((proposal) => <div className="proposal-row" key={proposal.id}><Avatar name={proposal.freelancer?.name} /><div className="proposal-person"><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title}</small><div><span className="tag">{proposal.status}</span></div></div><div className="proposal-amount"><b>{formatMMK(proposal.bid_amount)}</b><small>{proposal.delivery_days} days</small></div></div>) : <p className="empty-panel">New proposals will appear here.</p>}</article></section> : <section className="freelancer-grid"><article className="jobs-picked"><div className="panel-title"><h2>Jobs picked for you</h2><Link to="/search">Search marketplace →</Link></div>{data.recommended_jobs.map((job) => <article className="picked-job" key={job.id}><span className="job-icon">✦</span><div><b>{job.title}</b><small>{job.client?.client_profile?.company_name || job.client?.name}</small><div>{job.skills?.map((skill) => <span className="tag" key={skill}>{skill}</span>)}</div></div><div className="job-budget"><b>{formatMMK(job.budget_min)}</b><small>Fixed price</small></div><Link className="button button-outline" to={`/search/jobs/${job.id}`}>Submit proposal</Link></article>)}</article><aside className="panel activity"><div className="panel-title"><h2>Your proposals</h2></div>{data.proposals.length ? data.proposals.map((proposal) => <div key={proposal.id}><span>✦</span><section><b>{proposal.job?.title}</b><small>{formatMMK(proposal.bid_amount)} · {proposal.status}</small></section></div>) : <p className="empty-panel">No proposals yet. Find a job that fits.</p>}</aside></section>}
  </div>
}
