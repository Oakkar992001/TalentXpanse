import { useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

export default function MarketplaceReportButton({ targetType, targetId }) {
  const { user, errorMessage } = useAuth()
  const [reason, setReason] = useState('spam')
  const [details, setDetails] = useState('')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  if (!user) return null
  const submit = async (event) => { event.preventDefault(); setBusy(true); setError(''); try { const { data } = await api.post('/reports', { target_type: targetType, target_id: targetId, reason, details }); setNotice(data.created ? 'Report sent for review.' : 'You already reported this item.') } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) } }
  return <details className="marketplace-report-control"><summary>Report</summary>{notice ? <small>{notice}</small> : <form onSubmit={submit}><select value={reason} onChange={(event) => setReason(event.target.value)}><option value="spam">Spam</option><option value="fraud">Fraud or scam</option><option value="abuse">Abuse or harassment</option><option value="inappropriate_content">Inappropriate content</option><option value="other">Other</option></select><textarea value={details} onChange={(event) => setDetails(event.target.value)} maxLength="1500" placeholder="Optional details" /><button disabled={busy} type="submit">{busy ? 'Sending…' : 'Send report'}</button>{error && <small>{error}</small>}</form>}</details>
}
