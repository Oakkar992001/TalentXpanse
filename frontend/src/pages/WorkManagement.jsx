import { useCallback, useEffect, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../work-management.css'

const money = (value) => `Ks ${Number(value || 0).toLocaleString()}`
const label = (value) => String(value || '').replaceAll('_', ' ')
const offerExpiryText = (offer) => {
  if (!offer?.expires_at) return null
  const expiresAt = new Date(offer.expires_at)
  return expiresAt <= new Date() ? `Offer expired ${expiresAt.toLocaleString()}.` : `Offer expires ${expiresAt.toLocaleString()}.`
}

function OfferExpiryNotice({ offer }) {
  const message = offer?.status === 'pending' ? offerExpiryText(offer) : null
  return message ? <p className="work-offer-expiry">{message}</p> : null
}

function OfferTerms({ offer }) {
  if (!offer) return null

  return <section className={`work-offer ${offer.status}`}>
    <div className="work-offer-heading">
      <div>
        <p className="eyebrow">Formal offer</p>
        <h3>{money(offer.offered_amount)}</h3>
      </div>
      <span className={`work-status ${offer.status}`}>{label(offer.status)}</span>
    </div>
    <p>{offer.delivery_days ? `${offer.delivery_days} delivery days` : 'Flexible delivery timing'}{offer.start_date ? ` · Starts ${new Date(offer.start_date).toLocaleDateString()}` : ''}</p>
    {offer.message && <p className="work-offer-message">{offer.message}</p>}
    {offer.milestones?.length > 0 && <ul>{offer.milestones.map((milestone, index) => <li key={`${milestone.title}-${index}`}><span><b>{milestone.title}</b>{milestone.due_date && <small>Due {new Date(milestone.due_date).toLocaleDateString()}</small>}</span><strong>{money(milestone.amount)}</strong></li>)}</ul>}
  </section>
}

function ClientJobCard({ job, busy, onUpdate }) {
  const actionBusy = busy === `job-${job.id}`
  return <article className="work-card">
    <div className="work-card-main">
      <div>
        <span className={`work-status ${job.status}`}>{label(job.status)}</span>
        <h2>{job.title}</h2>
        <p>{job.category} · {money(job.budget_min)}–{money(job.budget_max)}</p>
      </div>
      <div className="work-meta"><b>{job.proposals_count} proposal{job.proposals_count === 1 ? '' : 's'}</b><small>Posted {new Date(job.created_at).toLocaleDateString()}</small></div>
    </div>
    <footer>
      <Link className="button button-outline" to={`/manage/jobs/${job.id}/proposals`}>Manage proposals</Link>
      <div>
        {job.status === 'open' && <button disabled={actionBusy} onClick={() => onUpdate(job, 'paused')}>Pause</button>}
        {job.status === 'paused' && <button disabled={actionBusy} onClick={() => onUpdate(job, 'open')}>Reopen</button>}
        {['open', 'paused', 'draft'].includes(job.status) && <button className="danger-action" disabled={actionBusy} onClick={() => onUpdate(job, 'closed')}>Close job</button>}
      </div>
    </footer>
  </article>
}

function FreelancerProposalCard({ proposal, busy, onWithdraw, onRespondOffer, contractId }) {
  const offer = proposal.latest_offer
  const canWithdraw = ['submitted', 'shortlisted', 'interviewing'].includes(proposal.status)
  const offerBusy = busy === `offer-${offer?.id}`

  return <article className="work-card">
    <div className="work-card-main">
      <div>
        <span className={`work-status ${proposal.status}`}>{label(proposal.status)}</span>
        <h2>{proposal.job?.title || 'Removed job post'}</h2>
        <p>{proposal.job?.client?.client_profile?.company_name || proposal.job?.client?.name} · {money(proposal.bid_amount)} · {proposal.delivery_days || 'Flexible'} days</p>
        {proposal.client_note && <small className="work-client-note">Client note: {proposal.client_note}</small>}
        {proposal.decline_reason && <small className="work-client-note">Decision note: {proposal.decline_reason}</small>}
      </div>
      <div className="work-meta"><b>{proposal.credit_cost || 0} credit{proposal.credit_cost === 1 ? '' : 's'} used</b><small>Sent {new Date(proposal.created_at).toLocaleDateString()}</small></div>
    </div>
    <OfferTerms offer={offer} />
    <OfferExpiryNotice offer={offer} />
    <footer>
      <Link className="button button-outline" to={`/search/jobs/${proposal.job_id}`}>View job</Link>
      <div>
        {offer?.status === 'pending' && <><button disabled={offerBusy} onClick={() => onRespondOffer(offer, 'declined')}>Decline offer</button><button className="button button-primary" disabled={offerBusy} onClick={() => onRespondOffer(offer, 'accepted')}>{offerBusy ? 'Updating...' : 'Accept offer'}</button></>}
        {proposal.status === 'hired' && contractId && <Link className="button button-primary" to={`/projects/${contractId}`}>Open project</Link>}
        {canWithdraw && <button className="danger-action" disabled={busy === `proposal-${proposal.id}`} onClick={() => onWithdraw(proposal)}>Withdraw proposal</button>}
        {proposal.status === 'withdrawn' && <small className="work-note">Proposal Credits are not returned after withdrawal.</small>}
      </div>
    </footer>
  </article>
}

export default function WorkManagementScreen() {
  const { role } = useOutletContext()
  const { errorMessage } = useAuth()
  const [items, setItems] = useState([])
  const [invites, setInvites] = useState([])
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(null)
  const [credits, setCredits] = useState(null)
  const [newContractId, setNewContractId] = useState(null)
  const isClient = role === 'client'
  const endpoint = isClient ? '/jobs/mine' : '/proposals/mine'

  const load = useCallback(async () => {
    setError('')
    try {
      const [work, invitationResponse] = await Promise.all([
        api.get(endpoint),
        isClient ? Promise.resolve(null) : api.get('/freelancer-invites'),
      ])
      setItems(work.data.data)
      if (invitationResponse) setInvites(invitationResponse.data.data)
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }, [endpoint, errorMessage, isClient])

  useEffect(() => { load() }, [load])
  useEffect(() => {
    if (isClient) return
    api.get('/proposal-credits').then(({ data }) => setCredits(data.data)).catch(() => setCredits(null))
  }, [isClient])

  const updateJob = async (job, status) => {
    const action = status === 'closed' ? 'close' : status === 'paused' ? 'pause' : 'reopen'
    if (!window.confirm(`Are you sure you want to ${action} “${job.title}”?`)) return
    setBusy(`job-${job.id}`)
    setError('')
    setNotice('')
    try {
      await api.patch(`/jobs/${job.id}`, { status })
      setNotice(`Job ${action === 'reopen' ? 'reopened' : `${action}d`}.`)
      load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  const withdraw = async (proposal) => {
    if (!window.confirm(`Withdraw your proposal for “${proposal.job?.title}”? Proposal Credits are not returned after withdrawal.`)) return
    setBusy(`proposal-${proposal.id}`)
    setError('')
    setNotice('')
    try {
      await api.patch(`/proposals/${proposal.id}`, { status: 'withdrawn' })
      setNotice('Proposal withdrawn. The client has been notified.')
      load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  const respondOffer = async (offer, status) => {
    const isAccepting = status === 'accepted'
    const prompt = isAccepting
      ? 'Accept this offer? This will start the contract with the listed milestones.'
      : 'Decline this offer? The client will be notified and can send another offer.'
    if (!window.confirm(prompt)) return
    setBusy(`offer-${offer.id}`)
    setError('')
    setNotice('')
    try {
      const { data } = await api.patch(`/proposal-offers/${offer.id}`, { status })
      setNewContractId(data.contract?.id || null)
      setNotice(isAccepting ? 'Offer accepted. Your contract is ready.' : 'Offer declined. The client can send revised terms if needed.')
      await load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  const respondInvite = async (invite, status) => {
    setBusy(`invite-${invite.id}`)
    setError('')
    setNotice('')
    try {
      await api.patch(`/freelancer-invites/${invite.id}`, { status })
      setNotice(status === 'accepted' ? 'Invitation accepted. Review the job and submit a proposal when you are ready.' : 'Invitation declined. The client was notified.')
      load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  return <section className="work-management-page">
    <header>
      <div>
        <p className="eyebrow">{isClient ? 'Client workspace' : 'Freelancer workspace'}</p>
        <h1>{isClient ? 'Manage your job posts.' : 'Track your opportunities.'}</h1>
        <p>{isClient ? 'Review candidates and send clear formal terms before a project starts.' : 'See applications, invitations, and any offers waiting for your decision.'}</p>
      </div>
      <div className="work-header-actions">
        {!isClient && credits && <span className="work-credits"><b>{credits.balance}</b> Proposal Credits available</span>}
        {isClient ? <Link className="button button-primary" to="/dashboard?role=client&postJob=1">Post a job</Link> : <Link className="button button-primary" to="/search?scope=jobs">Find work</Link>}
      </div>
    </header>
    {error && <p className="form-notice" role="alert">{error}</p>}
    {notice && <p className="form-notice" role="status">{notice}</p>}

    {!isClient && invites.length > 0 && <section className="invite-list">
      <div><p className="eyebrow">Client invitations</p><h2>Opportunities picked for you</h2><p>A client can invite you, but you stay in control: accept the invitation, review the job, then decide whether to submit a proposal.</p></div>
      {invites.map((invite) => <article key={invite.id}>
        <div><span className={`work-status ${invite.status}`}>{label(invite.status)}</span><h3>{invite.job?.title || 'Removed job post'}</h3><small>{invite.client?.client_profile?.company_name || invite.client?.name} · {money(invite.job?.budget_min)}–{money(invite.job?.budget_max)}</small>{invite.message && <p>{invite.message}</p>}</div>
        <footer><Link className="button button-outline" to={`/search/jobs/${invite.job_id}`}>View job</Link>{invite.status === 'pending' && <div><button disabled={busy === `invite-${invite.id}`} onClick={() => respondInvite(invite, 'declined')}>Decline</button><button className="button button-primary" disabled={busy === `invite-${invite.id}`} onClick={() => respondInvite(invite, 'accepted')}>{busy === `invite-${invite.id}` ? 'Updating...' : 'Accept invitation'}</button></div>}{invite.status === 'accepted' && <Link className="button button-primary" to={`/search/jobs/${invite.job_id}`}>Submit a proposal</Link>}</footer>
      </article>)}
    </section>}

    {!items.length ? <section className="work-empty"><h2>{isClient ? 'No job posts yet' : 'No proposals yet'}</h2><p>{isClient ? 'Create a detailed job post to start receiving proposals.' : 'Search the marketplace and apply to work that fits your skills.'}</p><Link className="button button-outline" to={isClient ? '/dashboard?role=client&postJob=1' : '/search?scope=jobs'}>{isClient ? 'Post your first job' : 'Browse opportunities'}</Link></section> : <div className="work-list">{isClient ? items.map((job) => <ClientJobCard key={job.id} job={job} busy={busy} onUpdate={updateJob} />) : items.map((proposal) => <FreelancerProposalCard key={proposal.id} proposal={proposal} busy={busy} onWithdraw={withdraw} onRespondOffer={respondOffer} contractId={newContractId} />)}</div>}
  </section>
}
