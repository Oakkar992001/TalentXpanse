import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

export default function PasswordRecoveryScreen({ reset = false }) {
  const { errorMessage } = useAuth()
  const [params] = useSearchParams()
  const [form, setForm] = useState({ email: params.get('email') || '', token: params.get('token') || '', password: '', password_confirmation: '' })
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const { data } = await api.post(reset ? '/auth/reset-password' : '/auth/forgot-password', reset ? form : { email: form.email })
      setNotice(data.message)
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  return <section className="auth-page"><div className="auth-panel"><div className="auth-card"><p className="eyebrow">Account recovery</p><h1>{reset ? 'Choose a new password.' : 'Reset your password.'}</h1><p className="auth-intro">{reset ? 'Create a strong new password for your TalentXpanse account.' : 'Enter your email and we will send a secure reset link if an account matches it.'}</p>
    <form onSubmit={submit}><div className="auth-fields">
      <label>Email<input required type="email" autoComplete="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="you@example.com" /></label>
      {reset && <><label>New password<input required minLength="8" type="password" autoComplete="new-password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder="At least 8 characters" /></label><label>Confirm new password<input required type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} placeholder="Repeat new password" /></label></>}
    </div>{notice && <p className="form-notice">{notice}</p>}{error && <p className="form-notice">{error}</p>}<button disabled={busy || (reset && !form.token)} className="button button-primary auth-continue">{busy ? 'Please wait…' : reset ? 'Reset password' : 'Send reset link'}</button></form>
    {reset && !form.token && <p className="auth-switch">This reset link is incomplete or expired. <Link to="/forgot-password">Request a new link</Link></p>}<p className="auth-switch"><Link to="/login">Back to log in</Link></p>
  </div></div><aside className="auth-art"><div className="art-card large"><span>🔐</span><b>Secure account recovery</b><small>Use the link sent to your email to safely create a new password.</small></div><div className="art-card small"><b>Your marketplace profile stays protected.</b></div><div className="art-figure">✦</div></aside></section>
}
