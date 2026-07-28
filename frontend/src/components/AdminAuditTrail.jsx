const readable = (value) => String(value || '').replaceAll('_', ' ')

export default function AdminAuditTrail({ entries }) {
  return <section className="admin-table admin-audit-trail"><p>Administrative actions are recorded here so moderation, project support, and payment-safety decisions can be reviewed later.</p>{entries.length ? <table><thead><tr><th>When</th><th>Administrator</th><th>Action</th><th>Subject</th><th>Decision</th></tr></thead><tbody>{entries.map((entry) => <tr key={entry.id}><td>{new Date(entry.created_at).toLocaleString()}</td><td>{entry.administrator?.name || 'Deleted administrator'}<small>{entry.administrator?.email}</small></td><td>{readable(entry.action)}</td><td>{readable(entry.subject_type)} #{entry.subject_id || '—'}</td><td>{entry.summary}</td></tr>)}</tbody></table> : <p className="admin-empty">No administrator actions have been recorded yet.</p>}</section>
}
