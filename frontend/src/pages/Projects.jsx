import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../reviews.css'

const money = (value) => `Ks ${Number(value || 0).toLocaleString()}`
const readableStatus = (value) => (value || '').replaceAll('_', ' ')
const fileSize = (bytes) => bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
const acceptedFiles = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv,.jpg,.jpeg,.png,.webp'

export function ProjectsScreen() {
  const { user, errorMessage } = useAuth()
  const [contracts, setContracts] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    if (!user?.id) return
    api.get('/contracts').then(({ data }) => setContracts(data.data)).catch((requestError) => setError(errorMessage(requestError)))
  }, [errorMessage, user?.id])

  if (!user) return <section className="simple-page"><h1>Your projects are waiting.</h1><Link className="button button-primary" to="/login">Log in</Link></section>

  return <section className="projects-page">
    <header><p className="eyebrow">Projects</p><h1>Move work forward, one milestone at a time.</h1><p>Submit delivery files, request clear revisions, and keep the project history in one place.</p></header>
    {error && <p className="form-notice" role="alert">{error}</p>}
    <div className="project-grid">{contracts.map((contract) => <Link className="project-card" key={contract.id} to={`/projects/${contract.id}`}><p>{contract.status}</p><h2>{contract.title}</h2><small>{contract.client_id === user.id ? contract.freelancer?.name : contract.client?.name}</small><strong>{money(contract.agreed_amount)}</strong><footer><span>{contract.milestones?.filter((milestone) => milestone.status === 'approved').length || 0}/{contract.milestones?.length || 0} milestones approved</span><b>Open project →</b></footer></Link>)}</div>
    {!contracts.length && <p className="empty-projects">Projects appear here after a client hires a freelancer.</p>}
  </section>
}

export function ProjectDetailScreen() {
  const { id } = useParams()
  const { user, errorMessage } = useAuth()
  const [contract, setContract] = useState(null)
  const [form, setForm] = useState({ title: '', description: '', amount: '', due_date: '' })
  const [review, setReview] = useState({ rating: 5, comment: '' })
  const [support, setSupport] = useState({ reason: 'delivery_issue', details: '' })
  const [deliveryDrafts, setDeliveryDrafts] = useState({})
  const [revisionDrafts, setRevisionDrafts] = useState({})
  const [deliveryOpenId, setDeliveryOpenId] = useState(null)
  const [revisionOpenId, setRevisionOpenId] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const load = useCallback(() => api.get(`/contracts/${id}`).then(({ data }) => setContract(data.data)).catch((requestError) => setError(errorMessage(requestError))), [errorMessage, id])

  useEffect(() => { load() }, [load])

  const run = async (request, success) => {
    setBusy(true)
    setError('')
    try {
      await request()
      setNotice(success)
      await load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const addMilestone = (event) => {
    event.preventDefault()
    run(() => api.post(`/contracts/${id}/milestones`, { ...form, amount: Number(form.amount), due_date: form.due_date || null }), 'Milestone created.').then(() => setForm({ title: '', description: '', amount: '', due_date: '' }))
  }
  const milestoneAction = (milestoneId, action) => run(() => api.patch(`/milestones/${milestoneId}`, { action }), action === 'approve' ? 'Milestone approved.' : `Milestone ${readableStatus(action)}.`)
  const complete = () => run(() => api.post(`/contracts/${id}/complete`), 'Project completed. You can now leave a review.')
  const submitReview = (event) => { event.preventDefault(); run(() => api.post(`/contracts/${id}/reviews`, review), 'Review submitted. It stays private until the other person reviews or the 14-day review window ends.') }
  const openSupportRequest = (event) => { event.preventDefault(); run(() => api.post(`/contracts/${id}/support-requests`, support), 'Your request has been sent to TalentXpanse support. Your project partner was notified.').then(() => setSupport({ reason: 'delivery_issue', details: '' })) }

  const submitDelivery = (event, milestone) => {
    event.preventDefault()
    const draft = deliveryDrafts[milestone.id] || { note: '', files: [] }
    const payload = new FormData()
    if (draft.note.trim()) payload.append('note', draft.note.trim())
    Array.from(draft.files || []).forEach((file) => payload.append('files[]', file))
    run(() => api.post(`/milestones/${milestone.id}/submissions`, payload), `Delivery version ${(milestone.submissions?.length || 0) + 1} was submitted for review.`).then(() => {
      setDeliveryDrafts((current) => ({ ...current, [milestone.id]: { note: '', files: [] } }))
      setDeliveryOpenId(null)
    })
  }

  const requestRevision = (event, milestone) => {
    event.preventDefault()
    const revisionNote = (revisionDrafts[milestone.id] || '').trim()
    run(() => api.patch(`/milestones/${milestone.id}`, { action: 'request_revision', revision_note: revisionNote }), 'Revision request sent.').then(() => {
      setRevisionDrafts((current) => ({ ...current, [milestone.id]: '' }))
      setRevisionOpenId(null)
    })
  }

  const downloadDelivery = async (file) => {
    try {
      const response = await api.get(`/milestone-submission-files/${file.id}/download`, { responseType: 'blob' })
      const url = URL.createObjectURL(new Blob([response.data], { type: file.mime_type }))
      const link = document.createElement('a')
      link.href = url
      link.download = file.original_name
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  if (!contract) return <section className="simple-page"><p>Loading project…</p>{error && <p className="form-notice" role="alert">{error}</p>}</section>

  const isClient = contract.client_id === user?.id
  const allocated = contract.milestones.reduce((total, milestone) => total + Number(milestone.amount), 0)
  const paymentPolicy = contract.payment_policy || { currency: 'MMK', platform_fee_percent: 10, payments_enabled: false }
  const paymentSafety = contract.payment_safety || { payment_hold_status: 'clear', status_message: 'Payment setup is not available yet.' }
  const totalClientFee = contract.milestones.reduce((total, milestone) => total + Number(milestone.payment_summary?.platform_fee_amount || milestone.client_fee_amount || 0), 0)
  const totalClientFunding = allocated + totalClientFee
  const draftAmount = Number(form.amount || 0)
  const draftFee = Math.ceil(draftAmount * Number(paymentPolicy.platform_fee_percent || 0) / 100)
  const myReview = contract.reviews?.find((item) => item.reviewer_id === user?.id)
  const partnerReview = contract.reviews?.find((item) => item.reviewer_id !== user?.id)
  const myOpenSupportRequest = contract.support_requests?.find((item) => item.opened_by === user?.id && ['open', 'under_review'].includes(item.status))

  return <section className="projects-page project-detail">
    <Link to="/projects">← All projects</Link>
    <header><p className="eyebrow">{contract.status} contract</p><h1>{contract.title}</h1><p>{contract.scope}</p></header>
    {notice && <p className="form-notice" role="status">{notice}</p>}
    {error && <p className="form-notice" role="alert">{error}</p>}

    <section className="contract-summary"><div><small>Agreed amount</small><b>{money(contract.agreed_amount)}</b></div><div><small>Allocated to milestones</small><b>{money(allocated)}</b></div><div><small>Partner</small><b>{isClient ? contract.freelancer?.name : contract.client?.name}</b></div><Link className="button button-outline" to="/messages">Open project chat</Link></section>

    <section className="payment-overview"><div><p className="eyebrow">Milestone payments</p><h2>{paymentSafety.payment_hold_status === 'on_hold' ? 'Payment safety hold is active.' : (paymentPolicy.payments_enabled ? 'Funding is available through TalentXpanse.' : 'Payment setup is in progress.')}</h2><p>{isClient ? `A ${paymentPolicy.platform_fee_percent}% TalentXpanse service fee is added to each milestone. Your freelancer keeps the full agreed milestone amount.` : 'Your agreed milestone amount is your payout amount. TalentXpanse charges the client a separate service fee.'}</p></div><dl><div><dt>{isClient ? 'Client service fees' : 'Your agreed payout'}</dt><dd>{money(isClient ? totalClientFee : allocated)}</dd></div><div><dt>{isClient ? 'Total client funding' : 'Client funding total'}</dt><dd>{money(totalClientFunding)}</dd></div></dl><small>{paymentSafety.status_message}{!paymentPolicy.payments_enabled && ' Checkout, custody, and payout are unavailable until an approved Myanmar payment partner is configured. No money moves through TalentXpanse yet.'}</small></section>
    {paymentSafety.payment_hold_status === 'on_hold' && <section className="payment-hold-notice" role="status"><div><b>Payment safety hold</b><p>{paymentSafety.payment_hold_note || 'TalentXpanse is reviewing a payment-related concern. Delivery discussion can continue, but the project cannot be completed or released until the hold is cleared.'}</p></div><small>{paymentSafety.payment_hold_at && `Placed ${new Date(paymentSafety.payment_hold_at).toLocaleString()}`}</small></section>}

    <section className="milestone-panel"><div className="milestone-title"><div><p className="eyebrow">Delivery plan</p><h2>Milestones</h2></div>{isClient && contract.status === 'active' && <span>{money(Math.max(0, Number(contract.agreed_amount) - allocated))} remaining</span>}</div>
      {contract.milestones.map((milestone) => {
        const payment = milestone.payment_summary || { platform_fee_amount: milestone.client_fee_amount || 0, client_total_amount: milestone.client_total_amount || milestone.amount, freelancer_payout_amount: milestone.amount }
        const deliveryDraft = deliveryDrafts[milestone.id] || { note: '', files: [] }
        const submissions = milestone.submissions || []
        return <article className="milestone-row" key={milestone.id}><div className={`milestone-dot ${milestone.status}`} /><div className="milestone-content"><b>{milestone.title}</b><small>{milestone.description || 'No delivery notes added.'}</small>{milestone.due_date && <em>Due {new Date(milestone.due_date).toLocaleDateString()}</em>}<span className="milestone-payment">{isClient ? <>Client funding: <b>{money(payment.client_total_amount)}</b> <small>({money(payment.platform_fee_amount)} service fee)</small></> : <>Your payout: <b>{money(payment.freelancer_payout_amount)}</b></>} <small>· {paymentSafety.payment_hold_status === 'on_hold' ? 'Payment safety hold active' : 'Payment setup pending'}</small></span>
          {submissions.length > 0 && <div className="submission-history"><b>Delivery history</b>{submissions.map((submission) => <article key={submission.id}><div><strong>Version {submission.version}</strong><span className={`submission-status ${submission.status}`}>{readableStatus(submission.status)}</span><small>Submitted {new Date(submission.submitted_at).toLocaleString()}</small></div>{submission.note && <p>{submission.note}</p>}{submission.review_note && <p className="submission-review"><b>Client feedback:</b> {submission.review_note}</p>}{submission.files?.length > 0 && <div className="submission-files">{submission.files.map((file) => <button type="button" key={file.id} onClick={() => downloadDelivery(file)}>Download {file.original_name} <small>{fileSize(file.file_size)}</small></button>)}</div>}</article>)}</div>}
          {!isClient && deliveryOpenId === milestone.id && <form className="delivery-form" onSubmit={(event) => submitDelivery(event, milestone)}><label>Delivery notes<textarea value={deliveryDraft.note} onChange={(event) => setDeliveryDrafts((current) => ({ ...current, [milestone.id]: { ...deliveryDraft, note: event.target.value } }))} maxLength="4000" placeholder="Describe what you delivered, where to review it, and any setup notes." /></label><label>Delivery files <small>Up to 5 files, 20 MB each. PDF, Office files, ZIP, images, text, or CSV.</small><input key={`${milestone.id}-${submissions.length}`} type="file" multiple accept={acceptedFiles} onChange={(event) => setDeliveryDrafts((current) => ({ ...current, [milestone.id]: { ...deliveryDraft, files: Array.from(event.target.files || []) } }))} /></label><div><button disabled={busy || (!deliveryDraft.note.trim() && !deliveryDraft.files.length)} className="button button-primary">Submit delivery</button><button type="button" disabled={busy} className="button button-outline" onClick={() => setDeliveryOpenId(null)}>Cancel</button></div></form>}
          {isClient && revisionOpenId === milestone.id && <form className="revision-form" onSubmit={(event) => requestRevision(event, milestone)}><label>Revision request<textarea required minLength="10" maxLength="2000" value={revisionDrafts[milestone.id] || ''} onChange={(event) => setRevisionDrafts((current) => ({ ...current, [milestone.id]: event.target.value }))} placeholder="Explain what needs to change and how you will review the revision." /></label><div><button disabled={busy || !(revisionDrafts[milestone.id] || '').trim()} className="button button-primary">Send revision request</button><button type="button" disabled={busy} className="button button-outline" onClick={() => setRevisionOpenId(null)}>Cancel</button></div></form>}
        </div><div className="milestone-amount"><b>{money(milestone.amount)}</b><span>{readableStatus(milestone.status)}</span></div><div className="milestone-actions">{!isClient && milestone.status === 'planned' && <button onClick={() => milestoneAction(milestone.id, 'start')} disabled={busy}>Start</button>}{!isClient && ['planned', 'in_progress', 'revision_requested'].includes(milestone.status) && <button className="primary" onClick={() => setDeliveryOpenId(milestone.id)} disabled={busy}>Submit delivery</button>}{isClient && milestone.status === 'submitted' && <><button onClick={() => setRevisionOpenId(milestone.id)} disabled={busy}>Request revision</button><button className="primary" onClick={() => milestoneAction(milestone.id, 'approve')} disabled={busy}>Approve</button></>}</div></article>
      })}
      {!contract.milestones.length && <p className="empty-panel">{isClient ? 'Create the first milestone to agree the delivery plan.' : 'The client has not created milestones yet.'}</p>}
      {isClient && contract.status === 'active' && <form className="milestone-form" onSubmit={addMilestone}><h3>Add a milestone</h3><div><input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="Milestone title" /><input required min="1000" type="number" value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} placeholder="Amount (MMK)" /><input type="date" value={form.due_date} onChange={(event) => setForm({ ...form, due_date: event.target.value })} /></div><textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="Deliverables and acceptance criteria" />{draftAmount >= 1000 && <p className="milestone-fee-preview">Client funding will be {money(draftAmount + draftFee)}: {money(draftAmount)} milestone amount + {money(draftFee)} TalentXpanse service fee. The freelancer payout remains {money(draftAmount)}.</p>}<button disabled={busy} className="button button-primary">Add milestone</button></form>}
      {isClient && contract.status === 'active' && contract.milestones.length > 0 && contract.milestones.every((milestone) => milestone.status === 'approved') && <button disabled={busy || paymentSafety.payment_hold_status === 'on_hold'} onClick={complete} className="button button-primary complete-project" title={paymentSafety.payment_hold_status === 'on_hold' ? 'TalentXpanse must clear the payment safety hold first.' : undefined}>{paymentSafety.payment_hold_status === 'on_hold' ? 'Payment safety hold active' : 'Complete project'}</button>}
    </section>

    {contract.status === 'active' && <section className="support-panel"><div><p className="eyebrow">Project support</p><h2>Need help with this project?</h2><p>Use milestones and project chat first. If that does not resolve a delivery, communication, scope, or payment concern, ask TalentXpanse to review it. A payment safety request blocks project completion and any future release until an administrator clears it.</p></div>{myOpenSupportRequest ? <p className="support-status">Your request is <b>{readableStatus(myOpenSupportRequest.status)}</b>. Support will update it here.</p> : <form onSubmit={openSupportRequest}><label>Issue type<select value={support.reason} onChange={(event) => setSupport({ ...support, reason: event.target.value })}><option value="delivery_issue">Delivery issue</option><option value="communication_issue">Communication issue</option><option value="scope_issue">Scope or agreement issue</option><option value="payment_issue">Payment safety issue</option><option value="other">Other project issue</option></select></label><label>What happened?<textarea required minLength="20" maxLength="2000" value={support.details} onChange={(event) => setSupport({ ...support, details: event.target.value })} placeholder="Explain what happened and what you have already tried." /></label><button disabled={busy} className="button button-outline">Send support request</button></form>}{contract.support_requests?.map((item) => <small className="support-history" key={item.id}>{item.opener?.name} opened a {readableStatus(item.reason)} request · {readableStatus(item.status)}{item.resolution_note ? ` · ${item.resolution_note}` : ''}</small>)}</section>}

    {contract.status === 'completed' && <section className="review-panel"><div><p className="eyebrow">Project feedback</p><h2>Rate your experience</h2><p>Reviews stay private until both people submit feedback, or the 14-day review window ends.</p></div>{!myReview ? <form onSubmit={submitReview}><label>Rating<select value={review.rating} onChange={(event) => setReview({ ...review, rating: Number(event.target.value) })}>{[5, 4, 3, 2, 1].map((rating) => <option key={rating} value={rating}>{rating} star{rating === 1 ? '' : 's'}</option>)}</select></label><label>Review <small>Optional</small><textarea value={review.comment} onChange={(event) => setReview({ ...review, comment: event.target.value })} maxLength="1500" placeholder={`How was working with ${isClient ? contract.freelancer?.name : contract.client?.name}?`} /></label><button disabled={busy} className="button button-primary">Submit review</button></form> : <div className="review-submitted"><b>Your {myReview.rating}-star review was submitted.</b><p>{partnerReview && !partnerReview.is_visible ? 'The other review is still private.' : 'Reviews are now visible to both people.'}</p></div>}{partnerReview?.is_visible && <article className="received-review"><b>{partnerReview.reviewer?.name} gave {partnerReview.rating} / 5</b>{partnerReview.comment && <p>“{partnerReview.comment}”</p>}</article>}</section>}
  </section>
}
