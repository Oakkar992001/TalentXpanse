import { useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

const label = (value) => String(value || 'unverified').replaceAll('_', ' ')
const MAX_IMAGE_SIZE = 5 * 1024 * 1024

export default function VerificationSettings({ account, onRefresh }) {
  const { errorMessage } = useAuth()
  const [note, setNote] = useState('')
  const [documents, setDocuments] = useState({ front: null, back: null })
  const [consent, setConsent] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(null)
  const identityStatus = account?.identity_verification_status || 'unverified'
  const companyStatus = account?.client_profile?.company_verification_status || 'unverified'
  const identitySubmissionPending = Boolean(account?.identity_verification_submission_pending)
  const canRequestIdentity = identityStatus !== 'verified' && !identitySubmissionPending

  const chooseDocument = (side, event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    const validType = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
    if (!validType || file.size > MAX_IMAGE_SIZE) {
      setError('Choose a JPG, PNG, or WebP image that is 5 MB or smaller.')
      return
    }

    setError('')
    setDocuments((current) => ({ ...current, [side]: file }))
  }

  const requestIdentity = async () => {
    if (!documents.front || !documents.back || !consent) {
      setError('Add both sides of your NRC and confirm the declaration before requesting review.')
      return
    }

    setBusy('identity')
    setError('')
    setNotice('')
    const data = new FormData()
    data.append('type', 'identity')
    data.append('nrc_front', documents.front)
    data.append('nrc_back', documents.back)
    if (note.trim()) data.append('note', note.trim())

    try {
      await api.post('/verification-requests', data)
      setNotice('Your NRC documents were sent securely for manual review.')
      setNote('')
      setDocuments({ front: null, back: null })
      setConsent(false)
      await onRefresh()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  const requestCompany = async () => {
    setBusy('company')
    setError('')
    setNotice('')
    try {
      await api.post('/verification-requests', { type: 'company', note: note.trim() || null })
      setNotice('Company verification was sent for manual review.')
      setNote('')
      await onRefresh()
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(null)
    }
  }

  if (!account) return <section className="settings-card"><p className="settings-loading">Loading verification status...</p></section>

  return <><section className="settings-card verification-settings"><p className="eyebrow">Trust and safety</p><h2>Verification status</h2><p className="settings-copy">Identity verification is reviewed by TalentXpanse. Do not send NRC or other identity documents through chat or email.</p>{error && <p className="form-notice" role="alert">{error}</p>}{notice && <p className="form-notice" role="status">{notice}</p>}<div className="verification-cards"><article className="identity-verification-card"><div><b>Identity verification</b><span className={`verification-status ${identityStatus}`}>{label(identityStatus)}</span><p>{identityStatus === 'verified' ? 'Your account identity has been reviewed. This applies to every workspace on this sign-in.' : identitySubmissionPending ? 'TalentXpanse is reviewing your NRC documents. You will be notified when the review is complete.' : identityStatus === 'pending' ? 'Your earlier request needs the current front and back NRC photos before it can be reviewed.' : account.identity_verification_note || 'Submit private front and back photos of your NRC for manual review.'}</p></div>{canRequestIdentity && <div className="identity-request-form"><div className="identity-document-grid"><label className="identity-document-upload"><span>NRC front</span><small>{documents.front?.name || 'Choose a JPG, PNG, or WebP image'}</small><input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => chooseDocument('front', event)} /></label><label className="identity-document-upload"><span>NRC back</span><small>{documents.back?.name || 'Choose a JPG, PNG, or WebP image'}</small><input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => chooseDocument('back', event)} /></label></div><label className="identity-consent"><input type="checkbox" checked={consent} onChange={(event) => setConsent(event.target.checked)} /><span>I confirm these are my own valid NRC documents and I consent to their use only for identity verification.</span></label><label className="verification-note">Note for the reviewer <small>Optional context. Do not write your NRC number here.</small><textarea maxLength="1000" value={note} onChange={(event) => setNote(event.target.value)} placeholder="Optional context for the review team" /></label><button disabled={busy !== null || !documents.front || !documents.back || !consent} className="button button-outline" onClick={requestIdentity}>{busy === 'identity' ? 'Uploading securely...' : 'Send for identity review'}</button></div>}</article>{account.roles.includes('client') && <article><div><b>Company verification</b><span className={`verification-status ${companyStatus}`}>{label(companyStatus)}</span><p>{companyStatus === 'verified' ? 'Your company workspace has been reviewed.' : companyStatus === 'pending' ? 'TalentXpanse is reviewing your company request.' : account.client_profile?.company_verification_note || 'Add company details, then request a manual review.'}</p></div>{companyStatus !== 'verified' && companyStatus !== 'pending' && <div className="company-verification-action"><label className="verification-note">Note for the reviewer <small>Optional business context.</small><textarea maxLength="1000" value={note} onChange={(event) => setNote(event.target.value)} placeholder="Optional context for the review team" /></label><button disabled={busy !== null || !account.client_profile?.company_name} className="button button-outline" onClick={requestCompany}>{busy === 'company' ? 'Sending...' : 'Request company review'}</button></div>}</article>}</div></section><section className="settings-card settings-note"><h2>How we handle your NRC photos</h2><p>Photos are stored privately for review, are visible only to authorized administrators, and are purged after the review decision. Verification is an account-trust signal; it is not payment, legal, or government verification.</p></section></>
}
