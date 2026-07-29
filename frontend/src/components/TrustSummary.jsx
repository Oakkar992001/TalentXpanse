const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`

export default function TrustSummary({ summary }) {
  if (!summary) return null

  return <section className="trust-summary">
    <div className="trust-metrics"><div><b>{summary.average_rating ? `★ ${summary.average_rating}` : 'New'}</b><small>{summary.review_count ? `${summary.review_count} review${summary.review_count === 1 ? '' : 's'}` : 'No visible reviews yet'}</small></div><div><b>{summary.completed_projects_count}</b><small>completed project{summary.completed_projects_count === 1 ? '' : 's'}</small></div></div>
    {summary.completed_projects?.length > 0 && <div className="trust-history"><p>Completed work</p>{summary.completed_projects.map((project) => <article key={project.id}><div><b>{project.title}</b><small>with {project.partner_name || 'TalentXpanse member'} • {new Date(project.completed_at).toLocaleDateString()}</small></div><strong>{money(project.amount)}</strong></article>)}</div>}
  </section>
}
