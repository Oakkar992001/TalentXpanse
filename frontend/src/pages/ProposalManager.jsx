import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import '../proposal-manager.css'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const statusLabel = (status) => ({ submitted: 'New', shortlisted: 'Shortlisted', interviewing: 'Interviewing', offered: 'Offer sent', declined: 'Declined', withdrawn: 'Withdrawn', hired: 'Hired' }[status] || status)
const tabs = [['all', 'All'], ['submitted', 'New'], ['shortlisted', 'Shortlisted'], ['interviewing', 'Interviewing'], ['offered', 'Offers'], ['declined', 'Declined']]
const offerExpiryText = (offer) => {
  if (!offer?.expires_at) return null
  const expiresAt = new Date(offer.expires_at)
  return expiresAt <= new Date() ? `This offer expired ${expiresAt.toLocaleString()}.` : `This offer expires ${expiresAt.toLocaleString()}.`
}

function OfferSummary({ offer }) {
  if (!offer) return null
  return <section className={`offer-summary ${offer.status}`}><span>Offer {offer.status}</span><h3>{money(offer.offered_amount)}</h3><p>{offer.delivery_days ? `${offer.delivery_days} delivery days` : 'Flexible delivery timing'}{offer.start_date ? ` · Starts ${new Date(offer.start_date).toLocaleDateString()}` : ''}</p>{offer.message && <p className="offer-message">{offer.message}</p>}<ol>{offer.milestones?.map((milestone, index) => <li key={`${milestone.title}-${index}`}><b>{milestone.title}</b><small>{money(milestone.amount)}{milestone.due_date ? ` · Due ${new Date(milestone.due_date).toLocaleDateString()}` : ''}</small></li>)}</ol></section>
}

function EmptyState({ tab }) {
  const message = tab === 'all' ? 'New applications will appear here once freelancers apply.' : `There are no ${statusLabel(tab).toLowerCase()} proposals right now.`
  return <div className="proposal-manager-empty"><h2>No proposals here yet</h2><p>{message}</p></div>
}

function OfferExpiryNotice({ offer }) {
  const message = offer?.status === 'pending' ? offerExpiryText(offer) : null
  return message ? <p className="offer-expiry-notice">{message}</p> : null
}

export default function ProposalManagerScreen() {
  const { id } = useParams()
  const { user, errorMessage } = useAuth()
  const confirm = useConfirmation()
  const [job, setJob] = useState(null)
  const [proposals, setProposals] = useState([])
  const [tab, setTab] = useState('all')
  const [selectedId, setSelectedId] = useState(null)
  const [compareIds, setCompareIds] = useState([])
  const [note, setNote] = useState('')
  const [interviewDate, setInterviewDate] = useState('')
  const [declineOpen, setDeclineOpen] = useState(false)
  const [declineReason, setDeclineReason] = useState('')
  const [offerOpen, setOfferOpen] = useState(false)
  const [offer, setOffer] = useState({ offered_amount: '', delivery_days: '', start_date: '', message: '', milestones: [{ title: 'Project delivery', description: '', amount: '', due_date: '' }] })
  const [busy, setBusy] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [jobResponse, proposalResponse] = await Promise.all([api.get(`/jobs/${id}`), api.get(`/jobs/${id}/proposals`)])
      setJob(jobResponse.data.data)
      setProposals(proposalResponse.data.data)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setLoading(false)
    }
  }, [errorMessage, id])

  useEffect(() => { load() }, [load])

  const visible = useMemo(() => tab === 'all' ? proposals : proposals.filter((proposal) => proposal.status === tab), [proposals, tab])
  const selected = proposals.find((proposal) => proposal.id === selectedId) || visible[0] || proposals[0]

  useEffect(() => {
    if (!selected) return
    setSelectedId(selected.id)
    setNote(selected.client_note || '')
    setInterviewDate(selected.interview_at ? String(selected.interview_at).slice(0, 10) : '')
    setDeclineOpen(false)
    setOfferOpen(false)
    setDeclineReason('')
    setOffer({
      offered_amount: selected.bid_amount || '',
      delivery_days: selected.delivery_days || '',
      start_date: '',
      message: '',
      milestones: [{ title: 'Project delivery', description: '', amount: selected.bid_amount || '', due_date: '' }],
    })
  }, [selected])

  const run = async (key, action, success) => {
    setBusy(key)
    setError('')
    setNotice('')
    try {
      await action()
      setNotice(success)
      await load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy('')
    }
  }

  const updateStatus = (status, data = {}) => run(`status-${status}`, () => api.patch(`/proposals/${selected.id}`, { status, ...data }), status === 'shortlisted' ? 'Proposal shortlisted.' : 'Interview status updated.')
  const toggleCompare = (proposal) => {
    setError('')
    setCompareIds((current) => {
      if (current.includes(proposal.id)) return current.filter((id) => id !== proposal.id)
      if (current.length >= 3) { setError('Compare up to three candidates at a time.'); return current }
      return [...current, proposal.id]
    })
  }
  const downloadResume = async (proposal) => {
    setBusy(`resume-${proposal.id}`); setError('')
    try {
      const response = await api.get(`/proposals/${proposal.id}/resume`, { responseType: 'blob' })
      const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
      const link = document.createElement('a'); link.href = url; link.download = proposal.resume_name || 'candidate-cv.pdf'; link.click(); URL.revokeObjectURL(url)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy('') }
  }
  const saveNote = () => run('note', () => api.patch(`/proposals/${selected.id}`, { status: selected.status, client_note: note.trim() || null }), 'Private note saved.')
  const decline = () => {
    if (!declineReason.trim()) return
    run('decline', () => api.patch(`/proposals/${selected.id}`, { status: 'declined', decline_reason: declineReason.trim() }), 'Proposal declined. The freelancer received your decision note.')
  }
  const withdrawOffer = async () => {
    if (!await confirm({ title: 'Withdraw this formal offer?', message: 'The freelancer will be notified. You can send revised terms later if needed.', confirmLabel: 'Withdraw offer' })) return
    run('withdraw-offer', () => api.patch(`/proposal-offers/${selected.latest_offer.id}`, { status: 'withdrawn' }), 'Offer withdrawn. You can send revised terms when ready.')
  }
  const updateMilestone = (index, updates) => setOffer((current) => ({ ...current, milestones: current.milestones.map((item, itemIndex) => itemIndex === index ? { ...item, ...updates } : item) }))
  const sendOffer = (event) => {
    event.preventDefault()
    run('offer', () => api.post(`/proposals/${selected.id}/offers`, {
      offered_amount: Number(offer.offered_amount),
      delivery_days: offer.delivery_days ? Number(offer.delivery_days) : null,
      start_date: offer.start_date || null,
      message: offer.message.trim() || null,
      milestones: offer.milestones.map((milestone) => ({ ...milestone, amount: Number(milestone.amount), description: milestone.description.trim() || null, due_date: milestone.due_date || null })),
    }), 'Offer sent. The freelancer must accept before the contract starts.')
  }

  if (!user) return null
  if (loading) return <section className="proposal-manager-page proposal-manager-loading" aria-live="polite"><span /><span /><p>Loading proposals...</p></section>
  if (!job) return <section className="simple-page"><h1>Proposal manager unavailable</h1><p>{error || 'This job could not be loaded.'}</p><Link className="button button-primary" to="/work?role=client">Back to your jobs</Link></section>
  if (job.client_id !== user.id) return <section className="simple-page"><h1>Proposal manager unavailable</h1><p>You can only review proposals for your own jobs.</p><Link className="button button-primary" to="/work?role=client">Back to your jobs</Link></section>

  const canAct = selected && ['submitted', 'shortlisted', 'interviewing'].includes(selected.status)
  const tabCount = (status) => status === 'all' ? proposals.length : proposals.filter((proposal) => proposal.status === status).length
  const compared = proposals.filter((proposal) => compareIds.includes(proposal.id))

  return <section className="proposal-manager-page">
    <Link className="proposal-manager-back" to="/work?role=client">Back to your jobs</Link>
    <header><div><p className="eyebrow">Proposal manager</p><h1>{job.title}</h1><p>Compare applicants, keep private notes, and send clear terms before starting a contract.</p></div><div className="proposal-manager-header-actions"><button className={compareIds.length ? 'button button-primary' : 'button button-outline'} disabled={!compareIds.length} onClick={() => document.getElementById('candidate-comparison')?.scrollIntoView({ behavior: 'smooth', block: 'start' })}>Compare {compareIds.length || ''} candidate{compareIds.length === 1 ? '' : 's'}</button><Link className="button button-outline" to={`/search/jobs/${job.id}`}>View job post</Link></div></header>
    {notice && <p className="form-notice" role="status">{notice}</p>}{error && <p className="form-notice" role="alert">{error}</p>}
    <nav className="proposal-tabs" aria-label="Proposal filters">{tabs.map(([value, label]) => <button key={value} className={tab === value ? 'active' : ''} onClick={() => setTab(value)}>{label}<span>{tabCount(value)}</span></button>)}</nav>
    {compared.length > 0 && <section id="candidate-comparison" className="candidate-comparison"><header><div><p className="eyebrow">Shortlist comparison</p><h2>Review the evidence side by side</h2><p>Compare up to three candidates. This is a private client workspace.</p></div><button onClick={() => setCompareIds([])}>Clear comparison</button></header><div className="candidate-comparison-grid">{compared.map((proposal) => <article key={proposal.id}><div className="candidate-comparison-person"><span>{proposal.freelancer?.profile_photo_url ? <img src={proposal.freelancer.profile_photo_url} alt="" /> : proposal.freelancer?.name?.slice(0, 2)}</span><div><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title || 'Freelancer'}</small></div></div><dl><div><dt>Bid</dt><dd>{money(proposal.bid_amount)}</dd></div><div><dt>Delivery</dt><dd>{proposal.delivery_days ? `${proposal.delivery_days} days` : 'Flexible'}</dd></div><div><dt>Experience</dt><dd>{proposal.freelancer?.freelancer_profile?.experience_level || 'Not listed'}</dd></div><div><dt>Rating</dt><dd>{proposal.freelancer?.trust_summary?.average_rating ? `${proposal.freelancer.trust_summary.average_rating} / 5` : 'New to TalentXpanse'}</dd></div></dl>{proposal.resume_name ? <button className="candidate-document" disabled={busy === `resume-${proposal.id}`} onClick={() => downloadResume(proposal)}>{busy === `resume-${proposal.id}` ? 'Preparing CV…' : `Download CV · ${proposal.resume_name}`}</button> : <p className="candidate-document muted">No CV attached to this proposal</p>}{proposal.work_samples?.length ? <div className="candidate-samples"><b>Portfolio evidence</b>{proposal.work_samples.map((sample) => sample.project_url ? <a key={sample.id} href={sample.project_url} target="_blank" rel="noreferrer">{sample.title} ↗</a> : <span key={sample.id}>{sample.title}</span>)}</div> : <p className="candidate-document muted">No portfolio samples selected</p>}<footer><button onClick={() => { setSelectedId(proposal.id); document.querySelector('.proposal-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }}>Open full proposal</button><button onClick={() => toggleCompare(proposal)}>Remove</button></footer></article>)}</div></section>}
    <div className="proposal-manager-layout">
      <aside className="proposal-list" aria-label="Proposals">{visible.length ? visible.map((proposal) => <button key={proposal.id} className={proposal.id === selected?.id ? 'selected' : ''} onClick={() => setSelectedId(proposal.id)}><span className="proposal-list-avatar">{proposal.freelancer?.profile_photo_url ? <img src={proposal.freelancer.profile_photo_url} alt="" /> : proposal.freelancer?.name?.slice(0, 2)}</span><span><b>{proposal.freelancer?.name}</b><small>{proposal.freelancer?.freelancer_profile?.title || 'Freelancer'}</small><em>{money(proposal.bid_amount)}</em></span><i className={`proposal-manager-status ${proposal.status}`}>{statusLabel(proposal.status)}</i></button>) : <EmptyState tab={tab} />}</aside>
      <main className="proposal-detail">{selected ? <>
        <header className="proposal-detail-header"><div className="proposal-profile"><span className="proposal-detail-avatar">{selected.freelancer?.profile_photo_url ? <img src={selected.freelancer.profile_photo_url} alt="" /> : selected.freelancer?.name?.slice(0, 2)}</span><div><h2>{selected.freelancer?.name}</h2><p>{selected.freelancer?.freelancer_profile?.title || 'Freelancer'}{selected.freelancer?.trust_summary?.average_rating ? ` · ${selected.freelancer.trust_summary.average_rating} rating` : ''}</p></div></div><span className={`proposal-manager-status ${selected.status}`}>{statusLabel(selected.status)}</span></header>
        <section className="proposal-term-grid"><div><small>Proposal amount</small><b>{money(selected.bid_amount)}</b></div><div><small>Delivery</small><b>{selected.delivery_days ? `${selected.delivery_days} days` : 'Flexible'}</b></div><div><small>Submitted</small><b>{new Date(selected.created_at).toLocaleDateString()}</b></div></section>
        <section className="proposal-section"><div className="proposal-evidence-heading"><div><h3>Cover letter</h3><p>{selected.cover_letter}</p></div><button className={compareIds.includes(selected.id) ? 'selected' : ''} onClick={() => toggleCompare(selected)}>{compareIds.includes(selected.id) ? 'Added to comparison' : 'Add to comparison'}</button></div>{selected.resume_name ? <div className="proposal-resume-preview"><span>PDF</span><div><b>{selected.resume_name}</b><small>Attached for this proposal only</small></div><button disabled={busy === `resume-${selected.id}`} onClick={() => downloadResume(selected)}>{busy === `resume-${selected.id}` ? 'Preparing…' : 'Preview / download'}</button></div> : <p className="proposal-document-empty">This freelancer did not attach a CV to this proposal.</p>}{selected.work_samples?.length > 0 && <div className="proposal-work-samples"><b>Selected work samples</b>{selected.work_samples.map((sample) => sample.project_url ? <a key={sample.id} href={sample.project_url} target="_blank" rel="noreferrer">{sample.title} ↗</a> : <span key={sample.id}>{sample.title}</span>)}</div>}</section>
        <OfferSummary offer={selected.latest_offer} />
        <OfferExpiryNotice offer={selected.latest_offer} />
        {selected.latest_offer?.status === 'pending' && <section className="proposal-section proposal-offer-control"><div><h3>Offer awaiting response</h3><p>The freelancer can accept or decline the terms. Withdraw only if you need to correct the offer.</p></div><button disabled={busy !== ''} onClick={withdrawOffer}>{busy === 'withdraw-offer' ? 'Withdrawing...' : 'Withdraw offer'}</button></section>}
        {canAct && <section className="proposal-section proposal-actions-panel"><div><h3>Candidate decision</h3><p>Shortlist now, schedule an interview, or send terms when you are ready.</p></div><div className="proposal-manager-actions"><button disabled={busy !== ''} onClick={() => updateStatus('shortlisted')}>Shortlist</button><button disabled={busy !== ''} onClick={() => updateStatus('interviewing', { interview_at: interviewDate || null })}>Mark interviewing</button><button className="primary" disabled={busy !== ''} onClick={() => setOfferOpen((value) => !value)}>{offerOpen ? 'Close offer' : 'Make an offer'}</button><button className="danger" disabled={busy !== ''} onClick={() => setDeclineOpen((value) => !value)}>Decline</button></div>{selected.status === 'interviewing' && <label className="proposal-interview-date">Interview date <input type="date" value={interviewDate} onChange={(event) => setInterviewDate(event.target.value)} /></label>}</section>}
        {canAct && <section className="proposal-section private-note"><div><h3>Private client note</h3><p>Visible only to you. Use it to remember feedback or compare candidates.</p></div><textarea value={note} maxLength="2000" onChange={(event) => setNote(event.target.value)} placeholder="Add a private note" /><button disabled={busy !== '' || note === (selected.client_note || '')} onClick={saveNote}>{busy === 'note' ? 'Saving...' : 'Save note'}</button></section>}
        {declineOpen && <section className="proposal-section proposal-decline"><h3>Decline this proposal</h3><p>A brief constructive reason helps the freelancer improve.</p><textarea value={declineReason} maxLength="180" onChange={(event) => setDeclineReason(event.target.value)} placeholder="Provide a short reason" /><div><button className="danger" disabled={busy !== '' || !declineReason.trim()} onClick={decline}>{busy === 'decline' ? 'Declining...' : 'Confirm decline'}</button><button disabled={busy !== ''} onClick={() => setDeclineOpen(false)}>Cancel</button></div></section>}
        {offerOpen && <form className="proposal-offer-form" onSubmit={sendOffer}><header><div><p className="eyebrow">Formal offer</p><h3>Confirm the work before the contract begins.</h3><p>The freelancer must accept these terms. Milestone amounts must equal the total offer.</p></div></header><div className="offer-basics"><label>Total offer (MMK)<input required min="1000" step="1000" inputMode="numeric" type="number" value={offer.offered_amount} onChange={(event) => setOffer({ ...offer, offered_amount: event.target.value })} /></label><label>Delivery days <small>Optional</small><input min="1" type="number" value={offer.delivery_days} onChange={(event) => setOffer({ ...offer, delivery_days: event.target.value })} /></label><label>Start date <small>Optional</small><input type="date" value={offer.start_date} onChange={(event) => setOffer({ ...offer, start_date: event.target.value })} /></label></div><label>Message <small>Optional</small><textarea value={offer.message} maxLength="3000" onChange={(event) => setOffer({ ...offer, message: event.target.value })} placeholder="Summarize the agreed scope, communication expectations, or next step." /></label><div className="offer-milestones"><div><h4>Milestones</h4><button type="button" disabled={offer.milestones.length >= 10} onClick={() => setOffer((current) => ({ ...current, milestones: [...current.milestones, { title: '', description: '', amount: '1000', due_date: '' }] }))}>Add milestone</button></div>{offer.milestones.map((milestone, index) => <article key={index}><label>Milestone title<input required value={milestone.title} onChange={(event) => updateMilestone(index, { title: event.target.value })} /></label><label>Amount (MMK)<input required min="1000" step="1000" inputMode="numeric" type="number" value={milestone.amount} onChange={(event) => updateMilestone(index, { amount: event.target.value })} /></label><label>Due date <small>Optional</small><input type="date" value={milestone.due_date} onChange={(event) => updateMilestone(index, { due_date: event.target.value })} /></label><label>Deliverables <small>Optional</small><textarea value={milestone.description} onChange={(event) => updateMilestone(index, { description: event.target.value })} placeholder="What should be delivered and how will it be reviewed?" /></label>{offer.milestones.length > 1 && <button type="button" className="remove-milestone" onClick={() => setOffer((current) => ({ ...current, milestones: current.milestones.filter((_, itemIndex) => itemIndex !== index) }))}>Remove</button>}</article>)}</div><footer><p>{money(offer.milestones.reduce((sum, milestone) => sum + Number(milestone.amount || 0), 0))} in milestones of {money(offer.offered_amount)}</p><button disabled={busy !== ''} className="button button-primary">{busy === 'offer' ? 'Sending offer...' : 'Send offer'}</button></footer></form>}
      </> : <EmptyState tab={tab} />}</main>
    </div>
  </section>
}
