import { useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

const label = (value) => String(value || 'unverified').replaceAll('_', ' ')

export default function VerificationSettings({ account, onRefresh }) {
  const { errorMessage } = useAuth()
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(null)
  const identityStatus = account?.identity_verification_status || 'unverified'
  const companyStatus = account?.client_profile?.company_verification_status || 'unverified'

  const request = async (type) => {
    setBusy(type)
    setError('')
    setNotice('')
    try {
      await api.post('/verification-requests', { type, note: note.trim() || null })
      setNotice(`${type === 'identity' ? 'Identity' : 'Company'} verification was sent for manual review.`)
      setNote('')
      await onRefresh()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  if (!account) return <section className="settings-card"><p className="settings-loading">Loading verification status...</p></section>

  return <><section className="settings-card verification-settings"><p className="eyebrow">Trust and safety</p><h2>Verification status</h2><p className="settings-copy">Verification is reviewed by TalentXpanse before live payment features are introduced. Do not send identity documents through chat or email.</p><label>Note for the verification reviewer <small>Optional. For example, explain the business name or account context.</small><textarea maxLength="1000" value={note} onChange={(event) => setNote(event.target.value)} placeholder="Optional context for the review team" /></label>{error && <p className="form-notice" role="alert">{error}</p>}{notice && <p className="form-notice" role="status">{notice}</p>}<div className="verification-cards"><article><div><b>Identity verification</b><span className={`verification-status ${identityStatus}`}>{label(identityStatus)}</span><p>{identityStatus === 'verified' ? 'Your account identity has been reviewed.' : identityStatus === 'pending' ? 'TalentXpanse is reviewing your request.' : account.identity_verification_note || 'Request a manual review when your account details are complete.'}</p></div>{identityStatus !== 'verified' && identityStatus !== 'pending' && <button disabled={busy !== null} className="button button-outline" onClick={() => request('identity')}>{busy === 'identity' ? 'Sending...' : 'Request review'}</button>}</article>{account.roles.includes('client') && <article><div><b>Company verification</b><span className={`verification-status ${companyStatus}`}>{label(companyStatus)}</span><p>{companyStatus === 'verified' ? 'Your company workspace has been reviewed.' : companyStatus === 'pending' ? 'TalentXpanse is reviewing your company request.' : account.client_profile?.company_verification_note || 'Add company details, then request a manual review.'}</p></div>{companyStatus !== 'verified' && companyStatus !== 'pending' && <button disabled={busy !== null || !account.client_profile?.company_name} className="button button-outline" onClick={() => request('company')}>{busy === 'company' ? 'Sending...' : 'Request review'}</button>}</article>}</div></section><section className="settings-card settings-note"><h2>What verification does not do yet</h2><p>It does not activate payment collection or payouts. Those remain unavailable until TalentXpanse completes payment-provider, compliance, and support processes.</p></section></>
}
