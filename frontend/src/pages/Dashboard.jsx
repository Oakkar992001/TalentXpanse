import { useCallback, useEffect, useState } from 'react'
import { Link, useOutletContext, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../dashboard-ux.css'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const categories = ['Development & IT', 'Design & Creative', 'Writing & Translation', 'Sales & Marketing', 'Admin & Support']

const statusLabel = (status) => ({
  submitted: 'Submitted',
  shortlisted: 'Shortlisted',
  interviewing: 'Interviewing',
  hired: 'Hired',
  declined: 'Not selected',
  withdrawn: 'Withdrawn',
  open: 'Open',
  paused: 'Paused',
  in_progress: 'In progress',
}[status] || String(status || '').replaceAll('_', ' '))

function WorkspaceIcon({ name }) {
  const paths = {
    briefcase: <><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2" /></>,
    people: <><circle cx="9" cy="8" r="3" /><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M18.5 20a5.5 5.5 0 0 0-3-4.9" /></>,
    spark: <path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z" />,
    profile: <><circle cx="12" cy="8" r="3.5" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" /></>,
    credit: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h3" /></>,
    arrow: <path d="M5 12h14M13 6l6 6-6 6" />,
  }
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>
}

function MetricCard({ icon, label, value, detail, to }) {
  const content = <><span className="workspace-metric-icon"><WorkspaceIcon name={icon} /></span><span><small>{label}</small><b>{value}</b><em>{detail}</em></span>{to && <WorkspaceIcon name="arrow" />}</>
  return to ? <Link className="workspace-metric" to={to}>{content}</Link> : <article className="workspace-metric">{content}</article>
}

function Status({ value }) {
  return <span className={`workspace-status ${value}`}>{statusLabel(value)}</span>
}

function EmptyPanel({ title, body, action, to }) {
  return <div className="workspace-empty"><div><WorkspaceIcon name="spark" /></div><h3>{title}</h3><p>{body}</p>{to && <Link className="button button-outline" to={to}>{action}</Link>}</div>
}

function JobForm({ form, onChange, busy, onCancel, onSubmit }) {
  return <form className="dashboard-job-form" onSubmit={onSubmit}>
    <div className="dashboard-form-heading"><div><p className="eyebrow">New job</p><h2>Tell freelancers what you need.</h2><p>A clear scope and budget attract more relevant proposals.</p></div><button type="button" className="dashboard-close" onClick={onCancel} aria-label="Close job form">Close</button></div>
    <label>Job title<input required minLength="8" value={form.title} onChange={(event) => onChange({ ...form, title: event.target.value })} placeholder="e.g. Build a bilingual membership portal" /></label>
    <label>Project description<textarea required minLength="30" value={form.description} onChange={(event) => onChange({ ...form, description: event.target.value })} placeholder="Describe the outcome, important deliverables, and what a successful handover looks like." /></label>
    <div className="dashboard-form-grid"><label>Category<select value={form.category} onChange={(event) => onChange({ ...form, category: event.target.value })}>{categories.map((category) => <option key={category}>{category}</option>)}</select></label><label>Timeline<select value={form.duration} onChange={(event) => onChange({ ...form, duration: event.target.value })}><option>Less than 1 month</option><option>1 to 3 months</option><option>3 to 6 months</option><option>More than 6 months</option></select></label><label>Budget from (MMK)<input required min="1000" type="number" value={form.budget_min} onChange={(event) => onChange({ ...form, budget_min: event.target.value })} placeholder="200000" /></label><label>Budget to (MMK)<input required min="1000" type="number" value={form.budget_max} onChange={(event) => onChange({ ...form, budget_max: event.target.value })} placeholder="500000" /></label></div>
    <label>Skills<input value={form.skills} onChange={(event) => onChange({ ...form, skills: event.target.value })} placeholder="Laravel, React, MySQL" /><small>Separate skills with commas.</small></label>
    <footer><button disabled={busy} className="button button-primary">{busy ? 'Publishing...' : 'Publish job'}</button><button type="button" disabled={busy} className="button button-outline" onClick={onCancel}>Cancel</button></footer>
  </form>
}

export default function DashboardScreen() {
  const { role } = useOutletContext()
  const [params] = useSearchParams()
  const { user, loading, errorMessage } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [showForm, setShowForm] = useState(() => params.get('postJob') === '1')
  const [busy, setBusy] = useState(false)
  const [jobForm, setJobForm] = useState({ title: '', description: '', category: 'Development & IT', budget_min: '', budget_max: '', duration: 'Less than 1 month', skills: '' })

  const load = useCallback(() => {
    if (!user?.id) return
    api.get('/dashboard', { params: { role } })
      .then(({ data: response }) => setData(response.data))
      .catch((requestError) => setError(errorMessage(requestError)))
  }, [errorMessage, role, user?.id])

  useEffect(() => {
    setData(null)
    setError('')
    setNotice('')
    load()
  }, [load])

  const postJob = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    try {
      await api.post('/jobs', {
        ...jobForm,
        budget_min: Number(jobForm.budget_min),
        budget_max: Number(jobForm.budget_max),
        skills: jobForm.skills.split(',').map((skill) => skill.trim()).filter(Boolean),
        budget_type: 'fixed',
        experience_level: 'intermediate',
      })
      setJobForm({ title: '', description: '', category: 'Development & IT', budget_min: '', budget_max: '', duration: 'Less than 1 month', skills: '' })
      setShowForm(false)
      setNotice('Your job is live and ready for proposals.')
      load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  if (loading || !data) return <section className="dashboard-loading" aria-live="polite"><span /><span /><span /><p>{error ? error : 'Loading your workspace...'}</p></section>
  if (!user) return null

  const isClient = data.role === 'client'
  const openJobs = data.metrics?.active_jobs || 0
  const proposalCount = data.metrics?.total_proposals || 0
  const profileCompleteness = data.metrics?.profile_completeness || 0
  const credits = data.proposal_credits?.balance || 0
  const focus = isClient
    ? (openJobs ? { title: proposalCount ? 'Review the newest applications.' : 'Your job post is live.', body: proposalCount ? `${proposalCount} proposal${proposalCount === 1 ? '' : 's'} need a careful review.` : 'Invite a freelancer or share the job to start receiving relevant proposals.', action: proposalCount ? 'Review applications' : 'Manage jobs', to: '/work?role=client' } : { title: 'Post your first job.', body: 'A detailed brief helps the right freelancers decide whether they are a match.', action: 'Post a job', to: null })
    : (profileCompleteness < 80 ? { title: 'Make your profile easier to trust.', body: `Your freelancer profile is ${profileCompleteness}% complete. Add the missing details before sending more proposals.`, action: 'Complete profile', to: '/profile' } : { title: 'Choose your next opportunity.', body: 'Review the jobs selected for your skills, save the best ones, and apply only when the work fits.', action: 'Browse jobs', to: '/search?scope=jobs' })

  return <section className="workspace-dashboard">
    <header className="workspace-dashboard-header"><div><p className="eyebrow">{isClient ? 'Client workspace' : 'Freelancer workspace'}</p><h1>Welcome back, {user.name?.split(' ')[0] || 'there'}.</h1><p>{isClient ? 'Keep hiring decisions and project work moving from one focused place.' : 'See what needs your attention and find work that fits your skills.'}</p></div><div className="workspace-header-actions"><Link className="button button-outline" to={`/work?role=${isClient ? 'client' : 'freelancer'}`}>{isClient ? 'Manage jobs' : 'Track proposals'}</Link>{isClient && <button type="button" className="button button-primary" onClick={() => setShowForm(true)}>Post a job</button>}</div></header>
    {notice && <p className="form-notice" role="status">{notice}</p>}
    {error && <p className="form-notice" role="alert">{error}</p>}
    {showForm && isClient && <JobForm form={jobForm} onChange={setJobForm} busy={busy} onCancel={() => setShowForm(false)} onSubmit={postJob} />}

    <section className="workspace-focus"><div><p className="eyebrow">Next step</p><h2>{focus.title}</h2><p>{focus.body}</p></div>{focus.to ? <Link className="button button-primary" to={focus.to}>{focus.action}</Link> : <button type="button" className="button button-primary" onClick={() => setShowForm(true)}>{focus.action}</button>}</section>

    {isClient ? <>
      <section className="workspace-metrics" aria-label="Client activity"><MetricCard icon="briefcase" label="Open jobs" value={openJobs} detail="Currently accepting proposals" to="/work?role=client" /><MetricCard icon="people" label="Applications" value={proposalCount} detail="Across all your job posts" to="/work?role=client" /><MetricCard icon="spark" label="Hires made" value={data.metrics?.hired || 0} detail="Projects started through TalentXpanse" to="/projects" /></section>
      <section className="workspace-dashboard-grid client">
        <article className="workspace-panel"><header><div><p className="eyebrow">Your hiring</p><h2>Open job posts</h2></div><Link to="/work?role=client">View all</Link></header>{data.jobs?.length ? <div className="workspace-list">{data.jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{job.title}</b><small>{job.category} · {money(job.budget_min)} - {money(job.budget_max)}</small><em>{job.proposals_count} proposal{job.proposals_count === 1 ? '' : 's'}</em></span><Status value={job.status} /><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title="No jobs posted yet" body="Create a clear job brief to receive proposals from freelancers." action="Post your first job" />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">Candidate activity</p><h2>Latest applications</h2></div>{proposalCount > 0 && <Link to="/work?role=client">Review all</Link>}</header>{data.recent_proposals?.length ? <div className="workspace-list proposals">{data.recent_proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-avatar">{proposal.freelancer?.profile_photo_url ? <img src={proposal.freelancer.profile_photo_url} alt="" /> : proposal.freelancer?.name?.slice(0, 2)}</span><span><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title || 'Freelancer'}</small><em>{money(proposal.bid_amount)} · {proposal.delivery_days || 'Flexible'} days</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title="Applications will appear here" body="When freelancers apply, you can shortlist, interview, and hire from the job page." />}</article>
      </section>
    </> : <>
      <section className="workspace-metrics" aria-label="Freelancer activity"><MetricCard icon="briefcase" label="Active proposals" value={data.metrics?.active_proposals || 0} detail="Submitted, shortlisted, or interviewing" to="/work?role=freelancer" /><MetricCard icon="credit" label="Proposal Credits" value={credits} detail="Shown before you apply" to="/settings/credits" /><MetricCard icon="profile" label="Profile readiness" value={`${profileCompleteness}%`} detail={profileCompleteness < 80 ? 'A stronger profile earns trust' : 'Ready to be discovered'} to="/profile" /></section>
      <section className="workspace-dashboard-grid freelancer">
        <article className="workspace-panel"><header><div><p className="eyebrow">Recommended for you</p><h2>Jobs worth a look</h2></div><Link to="/search?scope=jobs">Browse all</Link></header>{data.recommended_jobs?.length ? <div className="workspace-list">{data.recommended_jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="spark" /></span><span><b>{job.title}</b><small>{job.client?.client_profile?.company_name || job.client?.name} · {job.category}</small><em>{job.skills?.slice(0, 3).join(' · ') || 'Open opportunity'}</em></span><strong>{money(job.budget_min)}</strong><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title="No new recommendations yet" body="Try a wider search or save a search to receive matching job alerts." action="Search jobs" to="/search?scope=jobs" />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">Your applications</p><h2>Keep track</h2></div><Link to="/work?role=freelancer">View all</Link></header>{data.proposals?.length ? <div className="workspace-list proposals">{data.proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{proposal.job?.title || 'Removed job post'}</b><small>{money(proposal.bid_amount)} · {proposal.delivery_days || 'Flexible'} days</small><em>Sent {new Date(proposal.created_at).toLocaleDateString()}</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title="No proposals yet" body="Find work that matches your skills before spending any Proposal Credits." action="Find work" to="/search?scope=jobs" />}</article>
      </section>
    </>}
  </section>
}
