import { useEffect, useState } from 'react'
import api from '../services/api'

function VerificationRow({ title, detail, requestedAt, onAction, onPreview, busy, documentBusy }) {
  const [note, setNote] = useState('')
  const review = (status) => {
    if (status === 'rejected' && !note.trim()) return
    onAction(status, note.trim() || null)
  }

  return <article className="admin-verification-row"><div><b>{title}</b><small>{detail}</small><small>Requested {new Date(requestedAt).toLocaleString()}</small>{onPreview && <div className="identity-document-actions"><button type="button" disabled={documentBusy === 'front'} onClick={() => onPreview('front')}>{documentBusy === 'front' ? 'Opening NRC front...' : 'View NRC front'}</button><button type="button" disabled={documentBusy === 'back'} onClick={() => onPreview('back')}>{documentBusy === 'back' ? 'Opening NRC back...' : 'View NRC back'}</button></div>}</div><div><textarea value={note} onChange={(event) => setNote(event.target.value)} maxLength="1000" placeholder="Optional note for approval; required if rejecting" /><div><button disabled={busy} onClick={() => review('verified')}>Verify</button><button disabled={busy || !note.trim()} className="admin-danger" onClick={() => review('rejected')}>Reject</button></div></div></article>
}

export default function AdminVerificationPanel({ data, busy, onAction }) {
  const [preview, setPreview] = useState(null)
  const [previewError, setPreviewError] = useState('')
  const [documentBusy, setDocumentBusy] = useState(null)
  const identity = data?.identity || []
  const companies = data?.companies || []

  useEffect(() => () => { if (preview?.url) URL.revokeObjectURL(preview.url) }, [preview?.url])

  const closePreview = () => {
    if (preview?.url) URL.revokeObjectURL(preview.url)
    setPreview(null)
  }

  const openPreview = async (submissionId, side) => {
    setDocumentBusy(`${submissionId}-${side}`)
    setPreviewError('')
    try {
      const response = await api.get(`/admin/identity-verification-submissions/${submissionId}/documents/${side}`, { responseType: 'blob' })
      closePreview()
      setPreview({ side, url: URL.createObjectURL(response.data) })
    } catch {
      setPreviewError('Unable to open this private identity document. Refresh the review queue and try again.')
    } finally {
      setDocumentBusy(null)
    }
  }

  if (!data) return <p className="admin-empty">Loading verification requests...</p>

  return <section className="admin-verifications"><header><p>NRC images are sensitive documents. Open them only to complete the review, do not copy their details into notes, and verify or reject the request after checking both sides.</p></header>{previewError && <p className="form-notice" role="alert">{previewError}</p>}<section><h2>Identity requests</h2>{identity.length ? identity.map((submission) => { const account = submission.user; return <VerificationRow key={submission.id} title={account?.name || 'Removed account'} detail={`${account?.email || 'No email'} | ${(account?.roles || []).map((role) => role.name).join(', ') || 'No marketplace role'}`} requestedAt={submission.submitted_at} busy={busy === `/admin/users/${account?.id}/identity-verification`} documentBusy={documentBusy === `${submission.id}-front` ? 'front' : documentBusy === `${submission.id}-back` ? 'back' : null} onPreview={(side) => openPreview(submission.id, side)} onAction={(status, note) => onAction(`/admin/users/${account.id}/identity-verification`, { status, note })} /> }) : <p className="admin-empty">No identity requests are waiting.</p>}</section><section><h2>Company requests</h2>{companies.length ? companies.map((profile) => <VerificationRow key={profile.id} title={profile.company_name || 'Unnamed company'} detail={`${profile.user?.name || 'Removed user'} | ${profile.user?.email || 'No email'}`} requestedAt={profile.company_verification_requested_at} busy={busy === `/admin/client-profiles/${profile.id}/company-verification`} onAction={(status, note) => onAction(`/admin/client-profiles/${profile.id}/company-verification`, { status, note })} />) : <p className="admin-empty">No company requests are waiting.</p>}</section>{preview && <div className="identity-preview-backdrop" role="dialog" aria-modal="true" aria-label={`NRC ${preview.side} document`}><section><header><div><p className="eyebrow">Private identity document</p><h2>NRC {preview.side}</h2></div><button type="button" onClick={closePreview} aria-label="Close document preview">×</button></header><img src={preview.url} alt={`NRC ${preview.side} submitted for verification`} /><footer><button type="button" onClick={closePreview}>Close preview</button></footer></section></div>}</section>
}
