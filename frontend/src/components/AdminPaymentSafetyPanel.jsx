import { useState } from 'react'

const money = (value) => `Ks ${Number(value || 0).toLocaleString()}`
const readable = (value) => String(value || '').replaceAll('_', ' ')

export default function AdminPaymentSafetyPanel({ data, busy, onAction }) {
  const [notes, setNotes] = useState({})
  const holds = data?.on_hold_contracts || []
  const records = data?.records?.data || []

  return <section className="admin-payment-safety">
    <header><div><p className="eyebrow">Payment safety</p><h2>{data?.payments_enabled ? 'Provider processing enabled' : 'Provider setup is disabled'}</h2><p>This is an operational safety view. Do not promise a payment, clearance, or payout until the provider record is verified.</p></div><span>{holds.length} active hold{holds.length === 1 ? '' : 's'}</span></header>

    <div className="admin-payment-section"><h3>Active payment holds</h3>{holds.length ? holds.map((contract) => <article key={contract.id}><div><b>{contract.title}</b><small>{contract.client?.client_profile?.company_name || contract.client?.name} · {contract.freelancer?.name}</small><p>{contract.payment_hold_note || 'No hold note was provided.'}</p><small>{contract.payment_hold_at ? `Placed ${new Date(contract.payment_hold_at).toLocaleString()}` : 'Hold time unavailable'}</small></div><div className="admin-hold-action"><textarea aria-label={`Clearance note for ${contract.title}`} value={notes[contract.id] || ''} onChange={(event) => setNotes({ ...notes, [contract.id]: event.target.value })} placeholder="Explain the review outcome and why this hold may be cleared" minLength="10" maxLength="2000" /><button disabled={busy === `/admin/contracts/${contract.id}/payment-hold` || !(notes[contract.id] || '').trim()} onClick={() => onAction(`/admin/contracts/${contract.id}/payment-hold`, { status: 'clear', note: notes[contract.id] })}>Clear hold</button></div></article>) : <p className="admin-empty">No contracts are currently on payment safety hold.</p>}</div>

    <div className="admin-payment-section"><h3>Provider reconciliation records</h3>{records.length ? <div className="admin-table"><table><thead><tr><th>Project</th><th>Type</th><th>Amounts</th><th>Provider</th><th>Status</th></tr></thead><tbody>{records.map((record) => <tr key={record.id}><td><b>{record.contract?.title || 'Removed project'}</b><small>{record.client?.name} · {record.freelancer?.name}</small></td><td>{readable(record.type)}</td><td>{money(record.client_total_amount)}<small>Milestone {money(record.milestone_amount)} · fee {money(record.platform_fee_amount)}</small></td><td>{record.provider || 'Not configured'}<small>{record.provider_reference || 'No reference'}</small></td><td><span className={`admin-pill ${record.status}`}>{readable(record.status)}</span></td></tr>)}</tbody></table></div> : <p className="admin-empty">No provider records exist. This is expected until an approved payment partner is connected.</p>}</div>
  </section>
}
