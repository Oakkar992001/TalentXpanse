import { useState } from 'react'

function VerificationRow({ title, detail, requestedAt, onAction, busy }) {
  const [note, setNote] = useState('')
  const review = (status) => {
    if (status === 'rejected' && !note.trim()) return
    onAction(status, note.trim() || null)
  }

  return <article className="admin-verification-row"><div><b>{title}</b><small>{detail}</small><small>Requested {new Date(requestedAt).toLocaleString()}</small></div><div><textarea value={note} onChange={(event) => setNote(event.target.value)} maxLength="1000" placeholder="Optional note for approval; required if rejecting" /><div><button disabled={busy} onClick={() => review('verified')}>Verify</button><button disabled={busy || !note.trim()} className="admin-danger" onClick={() => review('rejected')}>Reject</button></div></div></article>
}

export default function AdminVerificationPanel({ data, busy, onAction }) {
  if (!data) return <p className="admin-empty">Loading verification requests...</p>
  const identity = data.identity || []
  const companies = data.companies || []

  return <section className="admin-verifications"><header><p>Manual verification is an account-trust signal only. It must not be represented as payment or legal verification.</p></header><section><h2>Identity requests</h2>{identity.length ? identity.map((account) => <VerificationRow key={account.id} title={account.name} detail={`${account.email} | ${(account.roles || []).map((role) => role.name).join(', ') || 'No marketplace role'}`} requestedAt={account.identity_verification_requested_at} busy={busy === `/admin/users/${account.id}/identity-verification`} onAction={(status, note) => onAction(`/admin/users/${account.id}/identity-verification`, { status, note })} />) : <p className="admin-empty">No identity requests are waiting.</p>}</section><section><h2>Company requests</h2>{companies.length ? companies.map((profile) => <VerificationRow key={profile.id} title={profile.company_name || 'Unnamed company'} detail={`${profile.user?.name || 'Removed user'} | ${profile.user?.email || 'No email'}`} requestedAt={profile.company_verification_requested_at} busy={busy === `/admin/client-profiles/${profile.id}/company-verification`} onAction={(status, note) => onAction(`/admin/client-profiles/${profile.id}/company-verification`, { status, note })} />) : <p className="admin-empty">No company requests are waiting.</p>}</section></section>
}
