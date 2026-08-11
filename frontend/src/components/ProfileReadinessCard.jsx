import { usePreferences } from '../contexts/PreferencesContext'
import '../profile-readiness.css'

export default function ProfileReadinessCard({ profile }) {
  const { t } = usePreferences()
  const checklist = profile?.profile_checklist || []
  const incomplete = checklist.filter((item) => !item.completed)
  const completion = profile?.profile_completeness || 0
  const completionText = t('profile.complete_percent', `${completion}% complete`, { percent: completion })

  return <section className="profile-readiness">
    <div className="profile-readiness-heading">
      <div><p className="eyebrow">{t('profile.readiness', 'Profile readiness')}</p><h2>{completionText}</h2></div>
      <span aria-label={completion === 100 ? t('profile.complete', 'Profile complete') : completionText}>{completion === 100 ? '✓' : `${completion}%`}</span>
    </div>
    <div className="profile-readiness-track" aria-label={t('profile.progress', `${completion}% of recommended profile details completed`, { percent: completion })} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={completion}><i style={{ width: `${completion}%` }} /></div>
    {completion === 100
      ? <p>{t('profile.ready_detail', 'Your profile has all recommended details. Keep it current as your work changes.')}</p>
      : <><p>{t('profile.next_detail', 'Complete these next to help clients understand your experience.')}</p><ul>{incomplete.slice(0, 4).map((item) => <li key={item.key}>{item.label}<small>+{item.weight}%</small></li>)}</ul></>}
  </section>
}
