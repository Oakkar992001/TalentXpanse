import { useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import './onboarding-checklist.css'

export default function OnboardingChecklist({ onboarding, onRewardClaimed }) {
  const { errorMessage } = useAuth()
  const [expanded, setExpanded] = useState(true)
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  if (!onboarding || onboarding.progress === 100) return null

  const claim = async () => {
    setBusy(true); setError(''); setNotice('')
    try {
      const { data } = await api.post('/onboarding/reward')
      setNotice(data.message)
      onRewardClaimed?.()
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  return <section className="onboarding-checklist" aria-labelledby="onboarding-title">
    <button className="onboarding-summary" onClick={() => setExpanded((value) => !value)} aria-expanded={expanded}>
      <span className="onboarding-progress" style={{ '--progress': `${onboarding.progress || 0}%` }}><b>{onboarding.progress || 0}%</b></span>
      <span><small>Getting started</small><h2 id="onboarding-title">Build a stronger marketplace start</h2><p>{onboarding.completed} of {onboarding.total} helpful steps complete</p></span>
      <i aria-hidden="true">{expanded ? '−' : '+'}</i>
    </button>
    {expanded && <div className="onboarding-body">
      <div className="onboarding-items">{onboarding.items?.map((item) => <Link key={item.key} to={item.href} className={item.completed ? 'complete' : ''}><span aria-hidden="true">{item.completed ? '✓' : '○'}</span><div><b>{item.label}</b><small>{item.detail}</small></div><em>{item.completed ? 'Done' : 'Open'}</em></Link>)}</div>
      {onboarding.reward && <div className="onboarding-reward"><div><b>{onboarding.reward.eligible ? 'You earned a profile reward' : onboarding.reward.label}</b><p>{onboarding.reward.awarded_at ? 'Your bonus credits are already in your balance.' : 'This one-time bonus expires on the same schedule as free monthly credits.'}</p></div>{onboarding.reward.eligible && <button className="button button-primary" disabled={busy} onClick={claim}>{busy ? 'Claiming…' : `Claim ${onboarding.reward.amount} credits`}</button>}</div>}
      {notice && <p className="form-notice" role="status">{notice}</p>}{error && <p className="form-notice" role="alert">{error}</p>}
    </div>}
  </section>
}
