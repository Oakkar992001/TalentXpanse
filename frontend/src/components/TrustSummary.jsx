import { usePreferences } from '../contexts/PreferencesContext'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`

export default function TrustSummary({ summary }) {
  const { t, formatDate } = usePreferences()
  if (!summary) return null

  return <section className="trust-summary">
    <div className="trust-metrics"><div><b>{summary.average_rating ? `★ ${summary.average_rating}` : t('common.new', 'New')}</b><small>{summary.review_count ? t('trust.reviews', `${summary.review_count} review${summary.review_count === 1 ? '' : 's'}`, { count: summary.review_count, suffix: summary.review_count === 1 ? '' : 's' }) : t('trust.no_reviews', 'No visible reviews yet')}</small></div><div><b>{summary.completed_projects_count}</b><small>{t('trust.projects', `${summary.completed_projects_count} completed project${summary.completed_projects_count === 1 ? '' : 's'}`, { count: summary.completed_projects_count, suffix: summary.completed_projects_count === 1 ? '' : 's' })}</small></div></div>
    {summary.completed_projects?.length > 0 && <div className="trust-history"><p>{t('trust.completed_work', 'Completed work')}</p>{summary.completed_projects.map((project) => <article key={project.id}><div><b>{project.title}</b><small>{t('trust.with_partner', `with ${project.partner_name || 'TalentXpanse member'} · ${formatDate(project.completed_at, { dateStyle: 'medium' })}`, { partner: project.partner_name || t('common.member', 'TalentXpanse member'), date: formatDate(project.completed_at, { dateStyle: 'medium' }) })}</small></div><strong>{money(project.amount)}</strong></article>)}</div>}
  </section>
}
