import { useCallback, useEffect, useState } from 'react'
import { Link, useOutletContext, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'
import '../dashboard-ux.css'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const categories = ['Development & IT', 'Design & Creative', 'Writing & Translation', 'Sales & Marketing', 'Admin & Support']
const categoryLabels = {
  'Development & IT': 'dashboard.category_development',
  'Design & Creative': 'dashboard.category_design',
  'Writing & Translation': 'dashboard.category_writing',
  'Sales & Marketing': 'dashboard.category_sales',
  'Admin & Support': 'dashboard.category_admin',
}

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
    shield: <path d="M12 3 19 6v5c0 4.7-2.9 8-7 10-4.1-2-7-5.3-7-10V6l7-3Zm-3.2 9 2.1 2.1 4.4-4.4" />,
    arrow: <path d="M5 12h14M13 6l6 6-6 6" />,
  }
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>
}

function MetricCard({ icon, label, value, detail, to }) {
  const content = <><span className="workspace-metric-icon"><WorkspaceIcon name={icon} /></span><span><small>{label}</small><b>{value}</b><em>{detail}</em></span>{to && <WorkspaceIcon name="arrow" />}</>
  return to ? <Link className="workspace-metric" to={to}>{content}</Link> : <article className="workspace-metric">{content}</article>
}

function Status({ value }) {
  const { t } = usePreferences()
  const labels = {
    submitted: 'status.submitted', shortlisted: 'status.shortlisted', interviewing: 'status.interviewing', hired: 'status.hired', declined: 'status.declined', withdrawn: 'status.withdrawn', open: 'status.open', paused: 'status.paused', in_progress: 'status.in_progress',
  }
  return <span className={`workspace-status ${value}`}>{labels[value] ? t(labels[value], statusLabel(value)) : statusLabel(value)}</span>
}

function EmptyPanel({ title, body, action, to }) {
  return <div className="workspace-empty"><div><WorkspaceIcon name="spark" /></div><h3>{title}</h3><p>{body}</p>{to && <Link className="button button-outline" to={to}>{action}</Link>}</div>
}

function JobForm({ form, onChange, busy, onCancel, onSubmit }) {
  const { t } = usePreferences()
  return <form className="dashboard-job-form" onSubmit={onSubmit}>
    <div className="dashboard-form-heading"><div><p className="eyebrow">{t('dashboard.new_job', 'New job')}</p><h2>{t('dashboard.job_form_title', 'Tell freelancers what you need.')}</h2><p>{t('dashboard.job_form_detail', 'A clear scope and budget attract more relevant proposals.')}</p></div><button type="button" className="dashboard-close" onClick={onCancel} aria-label={t('dashboard.close_job_form', 'Close job form')}>{t('common.close', 'Close')}</button></div>
    <label>{t('dashboard.job_title', 'Job title')}<input required minLength="8" value={form.title} onChange={(event) => onChange({ ...form, title: event.target.value })} placeholder={t('dashboard.job_title_hint', 'e.g. Build a bilingual membership portal')} /></label>
    <label>{t('dashboard.description', 'Project description')}<textarea required minLength="30" value={form.description} onChange={(event) => onChange({ ...form, description: event.target.value })} placeholder={t('dashboard.description_hint', 'Describe the outcome, important deliverables, and what a successful handover looks like.')} /></label>
    <div className="dashboard-form-grid"><label>{t('dashboard.category', 'Category')}<select value={form.category} onChange={(event) => onChange({ ...form, category: event.target.value })}>{categories.map((category) => <option key={category} value={category}>{t(categoryLabels[category], category)}</option>)}</select></label><label>{t('dashboard.timeline', 'Timeline')}<select value={form.duration} onChange={(event) => onChange({ ...form, duration: event.target.value })}><option>{t('dashboard.less_month', 'Less than 1 month')}</option><option>{t('dashboard.one_three_months', '1 to 3 months')}</option><option>{t('dashboard.three_six_months', '3 to 6 months')}</option><option>{t('dashboard.more_six_months', 'More than 6 months')}</option></select></label><label>{t('dashboard.budget_from', 'Budget from (MMK)')}<input required min="1000" type="number" value={form.budget_min} onChange={(event) => onChange({ ...form, budget_min: event.target.value })} placeholder="200000" /></label><label>{t('dashboard.budget_to', 'Budget to (MMK)')}<input required min="1000" type="number" value={form.budget_max} onChange={(event) => onChange({ ...form, budget_max: event.target.value })} placeholder="500000" /></label></div>
    <label>{t('dashboard.skills', 'Skills')}<input value={form.skills} onChange={(event) => onChange({ ...form, skills: event.target.value })} placeholder="Laravel, React, MySQL" /><small>{t('dashboard.separate_skills', 'Separate skills with commas.')}</small></label>
    <footer><button disabled={busy} className="button button-primary">{busy ? t('dashboard.publishing', 'Publishing...') : t('dashboard.publish_job', 'Publish job')}</button><button type="button" disabled={busy} className="button button-outline" onClick={onCancel}>{t('common.cancel', 'Cancel')}</button></footer>
  </form>
}

export default function DashboardScreen() {
  const { role } = useOutletContext()
  const [params] = useSearchParams()
  const { user, loading, errorMessage } = useAuth()
  const { t, formatDate } = usePreferences()
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
      setNotice(t('dashboard.job_live', 'Your job is live and ready for proposals.'))
      load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  if (loading || !data) return <section className="dashboard-loading" aria-live="polite"><span /><span /><span /><p>{error || t('dashboard.loading_workspace', 'Loading your workspace...')}</p></section>
  if (!user) return null

  const isClient = data.role === 'client'
  const openJobs = data.metrics?.active_jobs || 0
  const proposalCount = data.metrics?.total_proposals || 0
  const profileCompleteness = data.metrics?.profile_completeness || 0
  const credits = data.proposal_credits?.balance || 0
  const focus = isClient
    ? (openJobs ? { title: proposalCount ? t('dashboard.review_applications', 'Review the newest applications.') : t('dashboard.job_live_title', 'Your job post is live.'), body: proposalCount ? t('dashboard.review_count', `${proposalCount} proposals need a careful review.`, { count: proposalCount }) : t('dashboard.invite_share', 'Invite a freelancer or share the job to start receiving relevant proposals.'), action: proposalCount ? t('dashboard.review_applications', 'Review applications') : t('dashboard.manage_jobs', 'Manage jobs'), to: '/work?role=client' } : { title: t('dashboard.first_job', 'Post your first job.'), body: t('dashboard.brief_help', 'A detailed brief helps the right freelancers decide whether they are a match.'), action: t('dashboard.publish_job', 'Post a job'), to: null })
    : (profileCompleteness < 80 ? { title: t('dashboard.profile_trust', 'Make your profile easier to trust.'), body: t('dashboard.profile_complete', `Your freelancer profile is ${profileCompleteness}% complete. Add the missing details before sending more proposals.`, { percent: profileCompleteness }), action: t('dashboard.complete_profile', 'Complete profile'), to: '/profile' } : { title: t('dashboard.choose_opportunity', 'Choose your next opportunity.'), body: t('dashboard.choose_opportunity_detail', 'Review the jobs selected for your skills, save the best ones, and apply only when the work fits.'), action: t('dashboard.browse_jobs', 'Browse jobs'), to: '/search?scope=jobs' })

  return <section className="workspace-dashboard">
    <header className="workspace-dashboard-header"><div><p className="eyebrow">{isClient ? t('workspace.client', 'Client workspace') : t('workspace.freelancer', 'Freelancer workspace')}</p><h1>{t('dashboard.welcome', `Welcome back, ${user.name?.split(' ')[0] || 'there'}.`, { name: user.name?.split(' ')[0] || 'there' })}</h1><p>{isClient ? t('dashboard.client_intro', 'Keep hiring decisions and project work moving from one focused place.') : t('dashboard.freelancer_intro', 'See what needs your attention and find work that fits your skills.')}</p></div><div className="workspace-header-actions"><Link className="button button-outline" to={`/work?role=${isClient ? 'client' : 'freelancer'}`}>{isClient ? t('dashboard.manage_jobs', 'Manage jobs') : t('dashboard.track_proposals', 'Track proposals')}</Link>{isClient && <button type="button" className="button button-primary" onClick={() => setShowForm(true)}>{t('dashboard.publish_job', 'Post a job')}</button>}</div></header>
    {notice && <p className="form-notice" role="status">{notice}</p>}
    {error && <p className="form-notice" role="alert">{error}</p>}
    {showForm && isClient && <JobForm form={jobForm} onChange={setJobForm} busy={busy} onCancel={() => setShowForm(false)} onSubmit={postJob} />}

    <section className="workspace-focus"><div><p className="eyebrow">{t('dashboard.next_step', 'Next step')}</p><h2>{focus.title}</h2><p>{focus.body}</p></div>{focus.to ? <Link className="button button-primary" to={focus.to}>{focus.action}</Link> : <button type="button" className="button button-primary" onClick={() => setShowForm(true)}>{focus.action}</button>}</section>

    {isClient ? <>
      <section className="workspace-metrics" aria-label={t('workspace.client', 'Client workspace')}><MetricCard icon="briefcase" label={t('dashboard.open_jobs', 'Open jobs')} value={openJobs} detail={t('dashboard.accepting_proposals', 'Currently accepting proposals')} to="/work?role=client" /><MetricCard icon="people" label={t('dashboard.applications', 'Applications')} value={proposalCount} detail={t('dashboard.all_job_posts', 'Across all your job posts')} to="/work?role=client" /><MetricCard icon="spark" label={t('dashboard.hires_made', 'Hires made')} value={data.metrics?.hired || 0} detail={t('dashboard.projects_started', 'Projects started through TalentXpanse')} to="/projects" /><MetricCard icon="shield" label={t('dashboard.reliability', 'Reliability')} value={data.reliability?.tier_label || t('common.new', 'New')} detail={data.reliability?.visibility_label || t('dashboard.normal_reach', 'Normal reach')} to="/settings/reliability" /></section>
      <section className="workspace-dashboard-grid client">
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.your_hiring', 'Your hiring')}</p><h2>{t('dashboard.open_job_posts', 'Open job posts')}</h2></div><Link to="/work?role=client">{t('common.view_all', 'View all')}</Link></header>{data.jobs?.length ? <div className="workspace-list">{data.jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{job.title}</b><small>{job.category} · {money(job.budget_min)} - {money(job.budget_max)}</small><em>{t('dashboard.proposal_count', `${job.proposals_count} proposal${job.proposals_count === 1 ? '' : 's'}`, { count: job.proposals_count, suffix: job.proposals_count === 1 ? '' : 's' })}</em></span><Status value={job.status} /><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_jobs', 'No jobs posted yet')} body={t('dashboard.no_jobs_detail', 'Create a clear job brief to receive proposals from freelancers.')} action={t('dashboard.first_job_action', 'Post your first job')} />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.candidate_activity', 'Candidate activity')}</p><h2>{t('dashboard.latest_applications', 'Latest applications')}</h2></div>{proposalCount > 0 && <Link to="/work?role=client">{t('dashboard.review_all', 'Review all')}</Link>}</header>{data.recent_proposals?.length ? <div className="workspace-list proposals">{data.recent_proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-avatar">{proposal.freelancer?.profile_photo_url ? <img src={proposal.freelancer.profile_photo_url} alt="" /> : proposal.freelancer?.name?.slice(0, 2)}</span><span><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title || t('common.freelancer', 'Freelancer')}</small><em>{money(proposal.bid_amount)} · {t('dashboard.delivery_days', `${proposal.delivery_days || 'Flexible'} days`, { count: proposal.delivery_days || t('dashboard.flexible', 'Flexible') })}</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title={t('dashboard.applications_here', 'Applications will appear here')} body={t('dashboard.applications_detail', 'When freelancers apply, you can shortlist, interview, and hire from the job page.')} />}</article>
      </section>
    </> : <>
      <section className="workspace-metrics" aria-label={t('workspace.freelancer', 'Freelancer workspace')}><MetricCard icon="briefcase" label={t('dashboard.active_proposals', 'Active proposals')} value={data.metrics?.active_proposals || 0} detail={t('dashboard.proposal_detail', 'Submitted, shortlisted, or interviewing')} to="/work?role=freelancer" /><MetricCard icon="credit" label={t('dashboard.credits', 'Proposal Credits')} value={credits} detail={t('dashboard.credits_detail', 'Shown before you apply')} to="/settings/credits" /><MetricCard icon="profile" label={t('dashboard.profile_readiness', 'Profile readiness')} value={`${profileCompleteness}%`} detail={profileCompleteness < 80 ? t('dashboard.profile_stronger', 'A stronger profile earns trust') : t('dashboard.profile_ready', 'Ready to be discovered')} to="/profile" /><MetricCard icon="shield" label={t('dashboard.reliability', 'Reliability')} value={data.reliability?.tier_label || t('common.new', 'New')} detail={data.reliability?.visibility_label || t('dashboard.normal_reach', 'Normal reach')} to="/settings/reliability" /></section>
      <section className="workspace-dashboard-grid freelancer">
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.recommended', 'Recommended for you')}</p><h2>{t('dashboard.jobs_look', 'Jobs worth a look')}</h2></div><Link to="/search?scope=jobs">{t('dashboard.browse_all', 'Browse all')}</Link></header>{data.recommended_jobs?.length ? <div className="workspace-list">{data.recommended_jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="spark" /></span><span><b>{job.title}</b><small>{job.client?.client_profile?.company_name || job.client?.name} · {job.category}</small><em>{job.skills?.slice(0, 3).join(' · ') || t('dashboard.open_opportunity', 'Open opportunity')}</em></span><strong>{money(job.budget_min)}</strong><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_recommendations', 'No new recommendations yet')} body={t('dashboard.no_recommendations_detail', 'Try a wider search or save a search to receive matching job alerts.')} action={t('dashboard.search_jobs', 'Search jobs')} to="/search?scope=jobs" />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.your_applications', 'Your applications')}</p><h2>{t('dashboard.keep_track', 'Keep track')}</h2></div><Link to="/work?role=freelancer">{t('common.view_all', 'View all')}</Link></header>{data.proposals?.length ? <div className="workspace-list proposals">{data.proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{proposal.job?.title || t('dashboard.removed_job', 'Removed job post')}</b><small>{money(proposal.bid_amount)} · {t('dashboard.delivery_days', `${proposal.delivery_days || 'Flexible'} days`, { count: proposal.delivery_days || t('dashboard.flexible', 'Flexible') })}</small><em>{t('dashboard.sent', `Sent ${formatDate(proposal.created_at, { dateStyle: 'medium' })}`, { date: formatDate(proposal.created_at, { dateStyle: 'medium' }) })}</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_proposals', 'No proposals yet')} body={t('dashboard.no_proposals_detail', 'Find work that matches your skills before spending any Proposal Credits.')} action={t('dashboard.find_work', 'Find work')} to="/search?scope=jobs" />}</article>
      </section>
    </>}
  </section>
}
