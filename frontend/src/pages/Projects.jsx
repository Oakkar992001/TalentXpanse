import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../reviews.css'

const money = (value) => `Ks ${Number(value || 0).toLocaleString()}`

export function ProjectsScreen() {
  const { user, errorMessage } = useAuth()
  const [contracts, setContracts] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    if (user) api.get('/contracts').then(({ data }) => setContracts(data.data)).catch((requestError) => setError(errorMessage(requestError)))
  }, [user?.id])

  if (!user) return <section className="simple-page"><h1>Your projects are waiting.</h1><Link className="button button-primary" to="/login">Log in</Link></section>

  return <section className="projects-page"><header><p className="eyebrow">Projects</p><h1>Move work forward, one milestone at a time.</h1><p>Milestones track delivery only for now. Funding and payout will be connected later through a payment partner.</p></header>{error && <p className="form-notice">{error}</p>}<div className="project-grid">{contracts.map((contract) => <Link className="project-card" key={contract.id} to={`/projects/${contract.id}`}><p>{contract.status}</p><h2>{contract.title}</h2><small>{contract.client_id === user.id ? contract.freelancer?.name : contract.client?.name}</small><strong>{money(contract.agreed_amount)}</strong><footer><span>{contract.milestones?.filter((milestone) => milestone.status === 'approved').length || 0}/{contract.milestones?.length || 0} milestones approved</span><b>Open project →</b></footer></Link>)}</div>{!contracts.length && <p className="empty-projects">Projects appear here after a client hires a freelancer.</p>}</section>
}

export function ProjectDetailScreen() {
  const { id } = useParams()
  const { user, errorMessage } = useAuth()
  const [contract, setContract] = useState(null)
  const [form, setForm] = useState({ title: '', description: '', amount: '', due_date: '' })
  const [review, setReview] = useState({ rating: 5, comment: '' })
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const load = () => api.get(`/contracts/${id}`).then(({ data }) => setContract(data.data)).catch((requestError) => setError(errorMessage(requestError)))

  useEffect(() => { load() }, [id])

  const addMilestone = async (event) => {
    event.preventDefault(); setBusy(true); setError('')
    try { await api.post(`/contracts/${id}/milestones`, { ...form, amount: Number(form.amount), due_date: form.due_date || null }); setForm({ title: '', description: '', amount: '', due_date: '' }); setNotice('Milestone created.'); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const milestoneAction = async (milestoneId, action) => {
    setBusy(true); setError('')
    try { await api.patch(`/milestones/${milestoneId}`, { action }); setNotice(action === 'approve' ? 'Milestone approved.' : `Milestone ${action.replace('_', ' ')}.`); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const complete = async () => {
    setBusy(true); setError('')
    try { await api.post(`/contracts/${id}/complete`); setNotice('Project completed. You can now leave a review.'); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  const submitReview = async (event) => {
    event.preventDefault(); setBusy(true); setError('')
    try { await api.post(`/contracts/${id}/reviews`, review); setNotice('Review submitted. It stays private until the other person reviews or the 14-day review window ends.'); load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  if (!contract) return <section className="simple-page"><p>Loading project...</p>{error && <p className="form-notice">{error}</p>}</section>

  const isClient = contract.client_id === user?.id
  const allocated = contract.milestones.reduce((total, milestone) => total + Number(milestone.amount), 0)
  const myReview = contract.reviews?.find((item) => item.reviewer_id === user?.id)
  const partnerReview = contract.reviews?.find((item) => item.reviewer_id !== user?.id)

  return <section className="projects-page project-detail"><Link to="/projects">← All projects</Link><header><p className="eyebrow">{contract.status} contract</p><h1>{contract.title}</h1><p>{contract.scope}</p></header>{notice && <p className="form-notice">{notice}</p>}{error && <p className="form-notice">{error}</p>}<section className="contract-summary"><div><small>Agreed amount</small><b>{money(contract.agreed_amount)}</b></div><div><small>Allocated to milestones</small><b>{money(allocated)}</b></div><div><small>Partner</small><b>{isClient ? contract.freelancer?.name : contract.client?.name}</b></div><Link className="button button-outline" to="/messages">Open project chat</Link></section><section className="milestone-panel"><div className="milestone-title"><div><p className="eyebrow">Delivery plan</p><h2>Milestones</h2></div>{isClient && contract.status === 'active' && <span>{money(Math.max(0, Number(contract.agreed_amount) - allocated))} remaining</span>}</div>{contract.milestones.map((milestone) => <article className="milestone-row" key={milestone.id}><div className={`milestone-dot ${milestone.status}`} /><div><b>{milestone.title}</b><small>{milestone.description || 'No delivery notes added.'}</small>{milestone.due_date && <em>Due {new Date(milestone.due_date).toLocaleDateString()}</em>}</div><div className="milestone-amount"><b>{money(milestone.amount)}</b><span>{milestone.status.replace('_', ' ')}</span></div><div className="milestone-actions">{!isClient && milestone.status === 'planned' && <button onClick={() => milestoneAction(milestone.id, 'start')} disabled={busy}>Start</button>}{!isClient && ['planned', 'in_progress', 'revision_requested'].includes(milestone.status) && <button className="primary" onClick={() => milestoneAction(milestone.id, 'submit')} disabled={busy}>Submit</button>}{isClient && milestone.status === 'submitted' && <><button onClick={() => milestoneAction(milestone.id, 'request_revision')} disabled={busy}>Request revision</button><button className="primary" onClick={() => milestoneAction(milestone.id, 'approve')} disabled={busy}>Approve</button></>}</div></article>)}{!contract.milestones.length && <p className="empty-panel">{isClient ? 'Create the first milestone to agree the delivery plan.' : 'The client has not created milestones yet.'}</p>}{isClient && contract.status === 'active' && <form className="milestone-form" onSubmit={addMilestone}><h3>Add a milestone</h3><div><input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="Milestone title" /><input required min="1000" type="number" value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} placeholder="Amount (MMK)" /><input type="date" value={form.due_date} onChange={(event) => setForm({ ...form, due_date: event.target.value })} /></div><textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="Deliverables and acceptance criteria" /><button disabled={busy} className="button button-primary">Add milestone</button></form>}{isClient && contract.status === 'active' && contract.milestones.length > 0 && contract.milestones.every((milestone) => milestone.status === 'approved') && <button disabled={busy} onClick={complete} className="button button-primary complete-project">Complete project</button>}</section>{contract.status === 'completed' && <section className="review-panel"><div><p className="eyebrow">Project feedback</p><h2>Rate your experience</h2><p>Reviews stay private until both people submit feedback, or the 14-day review window ends.</p></div>{!myReview ? <form onSubmit={submitReview}><label>Rating<select value={review.rating} onChange={(event) => setReview({ ...review, rating: Number(event.target.value) })}>{[5, 4, 3, 2, 1].map((rating) => <option key={rating} value={rating}>{rating} star{rating === 1 ? '' : 's'}</option>)}</select></label><label>Review <small>Optional</small><textarea value={review.comment} onChange={(event) => setReview({ ...review, comment: event.target.value })} maxLength="1500" placeholder={`How was working with ${isClient ? contract.freelancer?.name : contract.client?.name}?`} /></label><button disabled={busy} className="button button-primary">Submit review</button></form> : <div className="review-submitted"><b>Your {myReview.rating}-star review was submitted.</b><p>{partnerReview && !partnerReview.is_visible ? 'The other review is still private.' : 'Reviews are now visible to both people.'}</p></div>}{partnerReview?.is_visible && <article className="received-review"><b>{partnerReview.reviewer?.name} gave {partnerReview.rating} / 5</b>{partnerReview.comment && <p>“{partnerReview.comment}”</p>}</article>}</section>}</section>
}
