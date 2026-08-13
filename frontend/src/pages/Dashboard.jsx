import { useCallback, useEffect, useState } from 'react'
import { Link, useOutletContext, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'
import OnboardingChecklist from '../components/OnboardingChecklist'
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
    folder: <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-9Z" />,
    bell: <><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" /></>,
    bookmark: <path d="M6 4.5A2.5 2.5 0 0 1 8.5 2h7A2.5 2.5 0 0 1 18 4.5V22l-6-3.5L6 22V4.5Z" />,
    check: <path d="m5 12 4.2 4.2L19 6.5" />,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></>,
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

function actionCopy(item, t) {
  const copy = {
    review_milestone: ['check', t('dashboard.action_review_delivery', 'Review delivered work'), t('dashboard.action_review_delivery_detail', 'A delivery is ready for your decision.')],
    review_proposals: ['people', t('dashboard.action_review_proposals', 'Review applications'), t('dashboard.action_review_proposals_detail', 'Candidates are waiting for your response.')],
    revise_milestone: ['spark', t('dashboard.action_revise_delivery', 'Update a delivery'), t('dashboard.action_revise_delivery_detail', 'A client requested changes before approval.')],
    start_milestone: ['clock', t('dashboard.action_start_milestone', 'Start the next milestone'), t('dashboard.action_start_milestone_detail', 'Keep the project moving with a clear update.')],
    continue_milestone: ['folder', t('dashboard.action_continue_project', 'Continue project work'), t('dashboard.action_continue_project_detail', 'Your active delivery is ready for progress.')],
    follow_up: ['bell', t('dashboard.action_follow_up', 'Follow up on an application'), t('dashboard.action_follow_up_detail', 'A client has moved your application forward.')],
  }
  const [icon, title, detail] = copy[item.type] || ['spark', t('dashboard.next_step', 'Next step'), t('dashboard.action_generic_detail', 'There is an update waiting for you.')]
  return { icon, title, detail }
}

function TodayPanel({ items = [], unreadCount = 0, isClient, t }) {
  const workPath = `/work?role=${isClient ? 'client' : 'freelancer'}`
  return <section className="workspace-today-panel">
    <header><div><p className="eyebrow">{t('dashboard.today', 'Today')}</p><h2>{t('dashboard.keep_moving', 'Keep your work moving.')}</h2></div><Link to="/notifications" className={unreadCount ? 'workspace-inbox-link unread' : 'workspace-inbox-link'}><WorkspaceIcon name="bell" />{unreadCount ? t('dashboard.unread_updates', `${unreadCount} unread`, { count: unreadCount }) : t('dashboard.all_caught_up', 'All caught up')}</Link></header>
    {items.length ? <div className="workspace-attention-list">{items.map((item, index) => {
      const copy = actionCopy(item, t)
      return <Link className="workspace-attention-item" to={item.href} key={`${item.type}-${item.href}-${index}`}><span><WorkspaceIcon name={copy.icon} /></span><div><small>{copy.title}</small><b>{item.label}</b><em>{item.context || copy.detail}</em></div><WorkspaceIcon name="arrow" /></Link>
    })}</div> : <div className="workspace-calm-state"><span><WorkspaceIcon name="check" /></span><div><b>{t('dashboard.no_urgent_actions', 'Nothing urgent right now.')}</b><p>{isClient ? t('dashboard.client_calm', 'Explore talented people or refine an open job while you wait for the right applications.') : t('dashboard.freelancer_calm', 'Explore new opportunities, save a search, or strengthen your profile.')}</p></div><Link to={isClient ? '/search?scope=talent' : '/search?scope=jobs'}>{isClient ? t('dashboard.explore_talent', 'Explore talent') : t('dashboard.explore_jobs', 'Explore jobs')}</Link></div>}
    <footer><Link to="/projects"><WorkspaceIcon name="folder" />{t('dashboard.open_projects', 'Open projects')}</Link><Link to={workPath}><WorkspaceIcon name="briefcase" />{isClient ? t('dashboard.manage_hiring', 'Manage hiring') : t('dashboard.track_proposals', 'Track proposals')}</Link></footer>
  </section>
}

function ProjectPulse({ projects = [], t, formatDate }) {
  return <section className="workspace-project-pulse">
    <header><div><p className="eyebrow">{t('dashboard.project_pulse', 'Project pulse')}</p><h2>{t('dashboard.active_projects', 'Active projects')}</h2></div><Link to="/projects">{t('common.view_all', 'View all')}</Link></header>
    {projects.length ? <div>{projects.map((project) => {
      const nextMilestone = project.milestones?.find((milestone) => milestone.status !== 'approved')
      return <Link key={project.id} to={`/projects/${project.id}`}><span><WorkspaceIcon name="folder" /></span><div><b>{project.title || project.job?.title}</b><small>{nextMilestone ? nextMilestone.title : t('dashboard.project_no_open_milestone', 'No open milestone')}</small><em>{nextMilestone?.due_date ? t('dashboard.due_date', `Due ${formatDate(nextMilestone.due_date, { dateStyle: 'medium' })}`, { date: formatDate(nextMilestone.due_date, { dateStyle: 'medium' }) }) : t('dashboard.open_workspace', 'Open workspace')}</em></div><WorkspaceIcon name="arrow" /></Link>
    })}</div> : <div className="workspace-project-empty"><span><WorkspaceIcon name="folder" /></span><b>{t('dashboard.no_active_projects', 'No active projects yet')}</b><p>{t('dashboard.projects_appear_here', 'Accepted work will appear here with milestones and shared activity.')}</p></div>}
  </section>
}

function SavedSearchPanel({ searches = [], t }) {
  const searchPath = (search) => {
    const params = new URLSearchParams({ scope: search.scope })
    Object.entries(search.filters || {}).forEach(([key, value]) => { if (typeof value === 'string' && value) params.set(key, value) })
    return `/search?${params.toString()}`
  }
  return <section className="workspace-return-panel">
    <header><div><p className="eyebrow">{t('dashboard.return_shortcuts', 'Return shortcuts')}</p><h2>{t('dashboard.saved_searches', 'Saved searches')}</h2></div><Link to="/search?scope=saved">{t('dashboard.manage_saved', 'Manage saved')}</Link></header>
    {searches.length ? <div>{searches.map((search) => <Link key={search.id} to={searchPath(search)}><span><WorkspaceIcon name="bookmark" /></span><div><b>{search.name}</b><small>{search.scope === 'talent' ? t('dashboard.talent_alert', 'Talent alert enabled') : t('dashboard.job_alert', 'Job alert enabled')}</small></div><WorkspaceIcon name="arrow" /></Link>)}</div> : <div className="workspace-return-empty"><span className="workspace-return-empty-icon"><WorkspaceIcon name="bookmark" /></span><div><b>{t('dashboard.no_saved_searches', 'Save a search you care about.')}</b><p>{t('dashboard.saved_searches_detail', 'Keep useful filters ready and receive matching alerts when they are available.')}</p></div><Link className="button button-primary" to="/search?scope=jobs">{t('dashboard.create_saved_search', 'Find and save a search')}</Link></div>}
  </section>
}

function ActivityPanel({ notifications = [], t, formatDate }) {
  return <section className="workspace-activity-panel">
    <header><div><p className="eyebrow">{t('dashboard.recent_activity', 'Recent activity')}</p><h2>{t('dashboard.marketplace_updates', 'Marketplace updates')}</h2></div><Link to="/notifications">{t('common.view_all', 'View all')}</Link></header>
    {notifications.length ? <div>{notifications.map((notification) => <Link className={notification.read_at ? '' : 'unread'} to={notification.url || '/notifications'} key={notification.id}><span><WorkspaceIcon name={notification.read_at ? 'bell' : 'spark'} /></span><div><b>{notification.title}</b><small>{notification.body}</small><em>{formatDate(notification.created_at, { dateStyle: 'medium' })}</em></div>{!notification.read_at && <i aria-label={t('dashboard.unread', 'Unread')} />}</Link>)}</div> : <div className="workspace-activity-empty"><WorkspaceIcon name="bell" /><b>{t('dashboard.no_updates', 'No updates yet')}</b><p>{t('dashboard.updates_appear_here', 'Project, proposal, and message updates will appear here.')}</p></div>}
  </section>
}

function TalentSpotlight({ profiles = [], t }) {
  return <section className="workspace-talent-spotlight">
    <header><div><p className="eyebrow">{t('dashboard.talent_spotlight', 'Talent spotlight')}</p><h2>{t('dashboard.people_for_jobs', 'People who may fit your open work')}</h2><p>{t('dashboard.talent_spotlight_detail', 'Suggestions use the skills on your active job posts. Review each profile before inviting anyone.')}</p></div><Link className="button button-outline" to="/search?scope=talent">{t('dashboard.search_talent', 'Search talent')}</Link></header>
    {profiles.length ? <div className="workspace-talent-grid">{profiles.map((profile) => <Link key={profile.id} to={`/search/freelancers/${profile.user_id}`}><span className="workspace-avatar">{profile.user?.profile_photo_url ? <img src={profile.user.profile_photo_url} alt="" /> : profile.user?.name?.slice(0, 2)}</span><div><b>{profile.user?.name}</b><small>{profile.title || t('common.freelancer', 'Freelancer')}</small><em>{profile.match?.skills?.length ? t('dashboard.matching_skills', `Matches ${profile.match.skills.join(', ')}`, { skills: profile.match.skills.join(', ') }) : t('dashboard.available_now', 'Available now')}</em></div><strong>{profile.user?.trust_summary?.average_rating ? `${profile.user.trust_summary.average_rating} / 5` : t('dashboard.new_talent', 'New')}</strong><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title={t('dashboard.talent_after_job', 'Talent suggestions appear after you post a job')} body={t('dashboard.talent_after_job_detail', 'Use the marketplace search to explore freelancer profiles in the meantime.')} action={t('dashboard.search_talent', 'Search talent')} to="/search?scope=talent" />}
  </section>
}

function JobForm({ form, onChange, busy, onCancel, onSubmit }) {
  const { t } = usePreferences()
  return <form className="dashboard-job-form" onSubmit={onSubmit}>
    <div className="dashboard-form-heading"><div><p className="eyebrow">{t('dashboard.new_job', 'New job')}</p><h2>{t('dashboard.job_form_title', 'Tell freelancers what you need.')}</h2><p>{t('dashboard.job_form_detail', 'A clear scope and budget attract more relevant proposals.')}</p></div><button type="button" className="dashboard-close" onClick={onCancel} aria-label={t('dashboard.close_job_form', 'Close job form')}>{t('common.close', 'Close')}</button></div>
    <label>{t('dashboard.job_title', 'Job title')}<input required minLength="8" value={form.title} onChange={(event) => onChange({ ...form, title: event.target.value })} placeholder={t('dashboard.job_title_hint', 'Enter a clear job title')} /></label>
    <label>{t('dashboard.description', 'Project description')}<textarea required minLength="30" value={form.description} onChange={(event) => onChange({ ...form, description: event.target.value })} placeholder={t('dashboard.description_hint', 'Describe the outcome, important deliverables, and what a successful handover looks like.')} /></label>
    <div className="dashboard-form-grid"><label>{t('dashboard.category', 'Category')}<select value={form.category} onChange={(event) => onChange({ ...form, category: event.target.value })}>{categories.map((category) => <option key={category} value={category}>{t(categoryLabels[category], category)}</option>)}</select></label><label>{t('dashboard.timeline', 'Timeline')}<select value={form.duration} onChange={(event) => onChange({ ...form, duration: event.target.value })}><option>{t('dashboard.less_month', 'Less than 1 month')}</option><option>{t('dashboard.one_three_months', '1 to 3 months')}</option><option>{t('dashboard.three_six_months', '3 to 6 months')}</option><option>{t('dashboard.more_six_months', 'More than 6 months')}</option></select></label><label>{t('dashboard.budget_from', 'Budget from (MMK)')}<input required min="1000" step="1000" inputMode="numeric" type="number" value={form.budget_min} onChange={(event) => onChange({ ...form, budget_min: event.target.value })} /></label><label>{t('dashboard.budget_to', 'Budget to (MMK)')}<input required min={form.budget_min || '1000'} step="1000" inputMode="numeric" type="number" value={form.budget_max} onChange={(event) => onChange({ ...form, budget_max: event.target.value })} /></label></div>
    <label>{t('dashboard.skills', 'Skills')}<input value={form.skills} onChange={(event) => onChange({ ...form, skills: event.target.value })} /><small>{t('dashboard.separate_skills', 'Separate skills with commas.')}</small></label>
    <footer><button disabled={busy} className="button button-primary">{busy ? t('dashboard.publishing', 'Publishing...') : t('dashboard.publish_job', 'Publish job')}</button><button type="button" disabled={busy} className="button button-outline" onClick={onCancel}>{t('common.cancel', 'Cancel')}</button></footer>
  </form>
}

export default function DashboardScreen() {
  const { role } = useOutletContext()
  const [params] = useSearchParams()
  const { user, loading, errorMessage } = useAuth()
  const { t, formatDate } = usePreferences()
  const [data, setData] = useState(null)
  const [onboarding, setOnboarding] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [showForm, setShowForm] = useState(() => params.get('postJob') === '1')
  const [busy, setBusy] = useState(false)
  const [jobForm, setJobForm] = useState({ title: '', description: '', category: 'Development & IT', budget_min: '1000', budget_max: '1000', duration: 'Less than 1 month', skills: '' })

  const load = useCallback(() => {
    if (!user?.id) return
    Promise.all([api.get('/dashboard', { params: { role } }), api.get('/onboarding')])
      .then(([{ data: response }, { data: onboardingResponse }]) => { setData(response.data); setOnboarding(onboardingResponse.data) })
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
      setJobForm({ title: '', description: '', category: 'Development & IT', budget_min: '1000', budget_max: '1000', duration: 'Less than 1 month', skills: '' })
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
  return <section className="workspace-dashboard">
    <header className="workspace-dashboard-header"><div><p className="eyebrow">{isClient ? t('workspace.client', 'Client workspace') : t('workspace.freelancer', 'Freelancer workspace')}</p><h1>{t('dashboard.welcome', `Welcome back, ${user.name?.split(' ')[0] || 'there'}.`, { name: user.name?.split(' ')[0] || 'there' })}</h1><p>{isClient ? t('dashboard.client_intro', 'Keep hiring decisions and project work moving from one focused place.') : t('dashboard.freelancer_intro', 'See what needs your attention and find work that fits your skills.')}</p></div><div className="workspace-header-actions"><Link className="button button-outline" to={`/work?role=${isClient ? 'client' : 'freelancer'}`}>{isClient ? t('dashboard.manage_jobs', 'Manage jobs') : t('dashboard.track_proposals', 'Track proposals')}</Link>{isClient && <button type="button" className="button button-primary" onClick={() => setShowForm(true)}>{t('dashboard.publish_job', 'Post a job')}</button>}</div></header>
    {notice && <p className="form-notice" role="status">{notice}</p>}
    {error && <p className="form-notice" role="alert">{error}</p>}
    {showForm && isClient && <JobForm form={jobForm} onChange={setJobForm} busy={busy} onCancel={() => setShowForm(false)} onSubmit={postJob} />}

    <OnboardingChecklist onboarding={onboarding} onRewardClaimed={load} />

    <section className="workspace-utility-grid">
      <TodayPanel items={data.action_items} unreadCount={data.metrics?.unread_notifications || 0} isClient={isClient} t={t} />
      <ProjectPulse projects={data.active_projects} t={t} formatDate={formatDate} />
    </section>

    {isClient ? <>
      <section className="workspace-metrics" aria-label={t('workspace.client', 'Client workspace')}><MetricCard icon="briefcase" label={t('dashboard.open_jobs', 'Open jobs')} value={openJobs} detail={t('dashboard.accepting_proposals', 'Currently accepting proposals')} to="/work?role=client" /><MetricCard icon="people" label={t('dashboard.applications', 'Applications')} value={proposalCount} detail={t('dashboard.all_job_posts', 'Across all your job posts')} to="/work?role=client" /><MetricCard icon="folder" label={t('dashboard.active_projects', 'Active projects')} value={data.metrics?.active_projects || 0} detail={t('dashboard.shared_workspaces', 'Shared workspaces in progress')} to="/projects" /><MetricCard icon="spark" label={t('dashboard.hires_made', 'Hires made')} value={data.metrics?.hired || 0} detail={t('dashboard.projects_started', 'Projects started through TalentXpanse')} to="/projects" /><MetricCard icon="shield" label={t('dashboard.reliability', 'Reliability')} value={data.reliability?.tier_label || t('common.new', 'New')} detail={data.reliability?.visibility_label || t('dashboard.normal_reach', 'Normal reach')} to="/settings/reliability" /></section>
      <section className="workspace-dashboard-grid client">
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.your_hiring', 'Your hiring')}</p><h2>{t('dashboard.open_job_posts', 'Open job posts')}</h2></div><Link to="/work?role=client">{t('common.view_all', 'View all')}</Link></header>{data.jobs?.length ? <div className="workspace-list">{data.jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{job.title}</b><small>{job.category} · {money(job.budget_min)} - {money(job.budget_max)}</small><em>{t('dashboard.proposal_count', `${job.proposals_count} proposal${job.proposals_count === 1 ? '' : 's'}`, { count: job.proposals_count, suffix: job.proposals_count === 1 ? '' : 's' })}</em></span><Status value={job.status} /><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_jobs', 'No jobs posted yet')} body={t('dashboard.no_jobs_detail', 'Create a clear job brief to receive proposals from freelancers.')} action={t('dashboard.first_job_action', 'Post your first job')} />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.candidate_activity', 'Candidate activity')}</p><h2>{t('dashboard.latest_applications', 'Latest applications')}</h2></div>{proposalCount > 0 && <Link to="/work?role=client">{t('dashboard.review_all', 'Review all')}</Link>}</header>{data.recent_proposals?.length ? <div className="workspace-list proposals">{data.recent_proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-avatar">{proposal.freelancer?.profile_photo_url ? <img src={proposal.freelancer.profile_photo_url} alt="" /> : proposal.freelancer?.name?.slice(0, 2)}</span><span><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title || t('common.freelancer', 'Freelancer')}</small><em>{money(proposal.bid_amount)} · {t('dashboard.delivery_days', `${proposal.delivery_days || 'Flexible'} days`, { count: proposal.delivery_days || t('dashboard.flexible', 'Flexible') })}</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title={t('dashboard.applications_here', 'Applications will appear here')} body={t('dashboard.applications_detail', 'When freelancers apply, you can shortlist, interview, and hire from the job page.')} />}</article>
      </section>
      <TalentSpotlight profiles={data.recommended_talent} t={t} />
    </> : <>
      <section className="workspace-metrics" aria-label={t('workspace.freelancer', 'Freelancer workspace')}><MetricCard icon="briefcase" label={t('dashboard.active_proposals', 'Active proposals')} value={data.metrics?.active_proposals || 0} detail={t('dashboard.proposal_detail', 'Submitted, shortlisted, or interviewing')} to="/work?role=freelancer" /><MetricCard icon="credit" label={t('dashboard.credits', 'Proposal Credits')} value={credits} detail={t('dashboard.credits_detail', 'Shown before you apply')} to="/settings/credits" /><MetricCard icon="folder" label={t('dashboard.active_projects', 'Active projects')} value={data.metrics?.active_projects || 0} detail={t('dashboard.shared_workspaces', 'Shared workspaces in progress')} to="/projects" /><MetricCard icon="profile" label={t('dashboard.profile_readiness', 'Profile readiness')} value={`${profileCompleteness}%`} detail={profileCompleteness < 80 ? t('dashboard.profile_stronger', 'A stronger profile earns trust') : t('dashboard.profile_ready', 'Ready to be discovered')} to="/profile" /><MetricCard icon="shield" label={t('dashboard.reliability', 'Reliability')} value={data.reliability?.tier_label || t('common.new', 'New')} detail={data.reliability?.visibility_label || t('dashboard.normal_reach', 'Normal reach')} to="/settings/reliability" /></section>
      <section className="workspace-dashboard-grid freelancer">
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.recommended', 'Recommended for you')}</p><h2>{t('dashboard.jobs_look', 'Jobs worth a look')}</h2></div><Link to="/search?scope=jobs">{t('dashboard.browse_all', 'Browse all')}</Link></header>{data.recommended_jobs?.length ? <div className="workspace-list">{data.recommended_jobs.map((job) => <Link className="workspace-job-row" key={job.id} to={`/search/jobs/${job.id}`}><span className="workspace-row-icon"><WorkspaceIcon name="spark" /></span><span><b>{job.title}</b><small>{job.client?.client_profile?.company_name || job.client?.name} · {job.category}</small><em>{job.match?.skills?.length ? t('dashboard.matching_skills', `Matches ${job.match.skills.join(', ')}`, { skills: job.match.skills.join(', ') }) : job.match?.saved_search ? t('dashboard.saved_search_match', 'Matches a saved search') : job.skills?.slice(0, 3).join(' · ') || t('dashboard.open_opportunity', 'Open opportunity')}</em></span><strong>{money(job.budget_min)}</strong><WorkspaceIcon name="arrow" /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_recommendations', 'No new recommendations yet')} body={t('dashboard.no_recommendations_detail', 'Try a wider search or save a search to receive matching job alerts.')} action={t('dashboard.search_jobs', 'Search jobs')} to="/search?scope=jobs" />}</article>
        <article className="workspace-panel"><header><div><p className="eyebrow">{t('dashboard.your_applications', 'Your applications')}</p><h2>{t('dashboard.keep_track', 'Keep track')}</h2></div><Link to="/work?role=freelancer">{t('common.view_all', 'View all')}</Link></header>{data.proposals?.length ? <div className="workspace-list proposals">{data.proposals.map((proposal) => <Link className="workspace-proposal-row" key={proposal.id} to={`/search/jobs/${proposal.job_id}`}><span className="workspace-row-icon"><WorkspaceIcon name="briefcase" /></span><span><b>{proposal.job?.title || t('dashboard.removed_job', 'Removed job post')}</b><small>{money(proposal.bid_amount)} · {t('dashboard.delivery_days', `${proposal.delivery_days || 'Flexible'} days`, { count: proposal.delivery_days || t('dashboard.flexible', 'Flexible') })}</small><em>{t('dashboard.sent', `Sent ${formatDate(proposal.created_at, { dateStyle: 'medium' })}`, { date: formatDate(proposal.created_at, { dateStyle: 'medium' }) })}</em></span><Status value={proposal.status} /></Link>)}</div> : <EmptyPanel title={t('dashboard.no_proposals', 'No proposals yet')} body={t('dashboard.no_proposals_detail', 'Find work that matches your skills before spending any Proposal Credits.')} action={t('dashboard.find_work', 'Find work')} to="/search?scope=jobs" />}</article>
      </section>
    </>}

    <section className="workspace-return-grid"><SavedSearchPanel searches={data.saved_searches} t={t} /><ActivityPanel notifications={data.notifications} t={t} formatDate={formatDate} /></section>
  </section>
}
