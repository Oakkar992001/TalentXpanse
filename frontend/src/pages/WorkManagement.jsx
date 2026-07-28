import { useEffect, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../work-management.css'

const money = (value) => `Ks ${Number(value || 0).toLocaleString()}`
const label = (value) => String(value || '').replaceAll('_', ' ')

export default function WorkManagementScreen() {
  const { role } = useOutletContext()
  const { errorMessage } = useAuth()
  const [items, setItems] = useState([])
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(null)
  const [credits, setCredits] = useState(null)
  const isClient = role === 'client'
  const endpoint = isClient ? '/jobs/mine' : '/proposals/mine'

  const load = async () => {
    setError('')
    try { const { data } = await api.get(endpoint); setItems(data.data) } catch (requestError) { setError(errorMessage(requestError)) }
  }
  useEffect(() => { load() }, [endpoint])
  useEffect(() => { if (!isClient) api.get('/proposal-credits').then(({ data }) => setCredits(data.data)).catch(() => setCredits(null)) }, [isClient])

  const updateJob = async (job, status) => {
    const action = status === 'closed' ? 'close' : status === 'paused' ? 'pause' : 'reopen'
    if (!window.confirm(`Are you sure you want to ${action} “${job.title}”?`)) return
    setBusy(`job-${job.id}`); setError(''); setNotice('')
    try { await api.patch(`/jobs/${job.id}`, { status }); setNotice(`Job ${action === 'reopen' ? 'reopened' : `${action}d`}.`); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(null) }
  }
  const withdraw = async (proposal) => {
    if (!window.confirm(`Withdraw your proposal for “${proposal.job?.title}”? Proposal Credits are not returned after withdrawal.`)) return
    setBusy(`proposal-${proposal.id}`); setError(''); setNotice('')
    try { await api.patch(`/proposals/${proposal.id}`, { status: 'withdrawn' }); setNotice('Proposal withdrawn. The client has been notified.'); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(null) }
  }

  return <section className="work-management-page"><header><div><p className="eyebrow">{isClient ? 'Client workspace' : 'Freelancer workspace'}</p><h1>{isClient ? 'Manage your job posts.' : 'Track your proposals.'}</h1><p>{isClient ? 'Keep posts accurate and review applications from one focused place.' : 'See every application, its current status, and the next action.'}</p></div><div className="work-header-actions">{!isClient && credits && <span className="work-credits"><b>{credits.balance}</b> Proposal Credits available</span>}{isClient ? <Link className="button button-primary" to="/dashboard?role=client&postJob=1">Post a job</Link> : <Link className="button button-primary" to="/search">Find work</Link>}</div></header>
    {error && <p className="form-notice">{error}</p>}{notice && <p className="form-notice">{notice}</p>}
    {!items.length ? <section className="work-empty"><h2>{isClient ? 'No job posts yet' : 'No proposals yet'}</h2><p>{isClient ? 'Create a detailed job post to start receiving proposals.' : 'Search the marketplace and apply to work that fits your skills.'}</p><Link className="button button-outline" to={isClient ? '/dashboard?role=client&postJob=1' : '/search'}>{isClient ? 'Post your first job' : 'Browse opportunities'}</Link></section> : <div className="work-list">{isClient ? items.map((job) => <article className="work-card" key={job.id}><div className="work-card-main"><div><span className={`work-status ${job.status}`}>{label(job.status)}</span><h2>{job.title}</h2><p>{job.category} · {money(job.budget_min)}–{money(job.budget_max)}</p></div><div className="work-meta"><b>{job.proposals_count} proposal{job.proposals_count === 1 ? '' : 's'}</b><small>Posted {new Date(job.created_at).toLocaleDateString()}</small></div></div><footer><Link className="button button-outline" to={`/search/jobs/${job.id}`}>View applications</Link><div>{job.status === 'open' && <button disabled={busy === `job-${job.id}`} onClick={() => updateJob(job, 'paused')}>Pause</button>}{job.status === 'paused' && <button disabled={busy === `job-${job.id}`} onClick={() => updateJob(job, 'open')}>Reopen</button>}{['open', 'paused', 'draft'].includes(job.status) && <button className="danger-action" disabled={busy === `job-${job.id}`} onClick={() => updateJob(job, 'closed')}>Close job</button>}</div></footer></article>) : items.map((proposal) => <article className="work-card" key={proposal.id}><div className="work-card-main"><div><span className={`work-status ${proposal.status}`}>{label(proposal.status)}</span><h2>{proposal.job?.title || 'Removed job post'}</h2><p>{proposal.job?.client?.client_profile?.company_name || proposal.job?.client?.name} · {money(proposal.bid_amount)} · {proposal.delivery_days || 'Flexible'} days</p></div><div className="work-meta"><b>{proposal.credit_cost || 0} credit{proposal.credit_cost === 1 ? '' : 's'} used</b><small>Sent {new Date(proposal.created_at).toLocaleDateString()}</small></div></div><footer><Link className="button button-outline" to={`/search/jobs/${proposal.job_id}`}>View job</Link>{['submitted', 'shortlisted'].includes(proposal.status) && <button className="danger-action" disabled={busy === `proposal-${proposal.id}`} onClick={() => withdraw(proposal)}>Withdraw proposal</button>}{proposal.status === 'withdrawn' && <small className="work-note">Proposal Credits are not returned after withdrawal.</small>}</footer></article>)}</div>}
  </section>
}
