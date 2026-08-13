import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import './beta-feedback.css'

const areaFor = (path) => path.startsWith('/manage') ? 'hiring' : path.startsWith('/projects') ? 'projects' : path.startsWith('/search') ? 'marketplace' : path.startsWith('/settings') ? 'safety' : 'general'

export default function BetaFeedbackButton() {
  const { pathname } = useLocation()
  const { errorMessage } = useAuth()
  const [open, setOpen] = useState(false)
  const [rating, setRating] = useState(0)
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const { data } = await api.post('/feedback', { area: areaFor(pathname), rating: rating || null, message: message.trim(), page_url: window.location.pathname })
      setNotice(data.message); setMessage(''); setRating(0)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }
  return <div className="beta-feedback"><button className="beta-feedback-trigger" onClick={() => { setOpen((value) => !value); setError(''); setNotice('') }} aria-expanded={open}>Feedback</button>{open && <form className="beta-feedback-popover" onSubmit={submit}><header><div><small>Open beta</small><h2>Help improve TalentXpanse</h2></div><button type="button" onClick={() => setOpen(false)} aria-label="Close feedback">×</button></header><p>Tell us what was useful, confusing, or missing on this page. Please do not include passwords or identity documents.</p><div className="feedback-rating" aria-label="Optional rating">{[1, 2, 3, 4, 5].map((value) => <button type="button" key={value} className={rating >= value ? 'active' : ''} onClick={() => setRating(value)} aria-label={`${value} out of 5`}>★</button>)}</div><label>Your feedback<textarea required minLength="10" maxLength="2000" value={message} onChange={(event) => setMessage(event.target.value)} /></label>{error && <p className="form-notice" role="alert">{error}</p>}{notice && <p className="form-notice" role="status">{notice}</p>}<footer><button type="button" onClick={() => setOpen(false)}>Cancel</button><button className="button button-primary" disabled={busy || message.trim().length < 10}>{busy ? 'Sending…' : 'Send feedback'}</button></footer></form>}</div>
}
