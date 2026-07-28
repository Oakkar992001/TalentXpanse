import '../profile-readiness.css'

export default function ProfileReadinessCard({ profile }) {
  const checklist = profile?.profile_checklist || []
  const incomplete = checklist.filter((item) => !item.completed)
  const completion = profile?.profile_completeness || 0

  return <section className="profile-readiness">
    <div className="profile-readiness-heading">
      <div><p className="eyebrow">Profile readiness</p><h2>{completion}% complete</h2></div>
      <span aria-label={completion === 100 ? 'Profile complete' : `${completion}% complete`}>{completion === 100 ? '✓' : `${completion}%`}</span>
    </div>
    <div className="profile-readiness-track" aria-label={`${completion}% of recommended profile details completed`} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={completion}><i style={{ width: `${completion}%` }} /></div>
    {completion === 100
      ? <p>Your profile has all recommended details. Keep it current as your work changes.</p>
      : <><p>Complete these next to help clients understand your experience.</p><ul>{incomplete.slice(0, 4).map((item) => <li key={item.key}>{item.label}<small>+{item.weight}%</small></li>)}</ul></>}
  </section>
}
