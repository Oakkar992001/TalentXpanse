import { useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

function BookmarkIcon({ filled = false }) {
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M6 4.75A1.75 1.75 0 0 1 7.75 3h8.5A1.75 1.75 0 0 1 18 4.75V21l-6-3.75L6 21V4.75Z" /></svg>
}

export default function MarketplaceSaveButton({ kind, targetId }) {
  const { user, errorMessage } = useAuth()
  const role = kind === 'job' ? 'freelancer' : 'client'
  const [saved, setSaved] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const supported = Boolean(user?.roles?.includes(role))

  useEffect(() => {
    if (!supported) return undefined
    let active = true
    api.get('/marketplace-saves').then(({ data }) => {
      const ids = kind === 'job' ? data.data.job_ids : data.data.talent_ids
      if (active) setSaved(ids.includes(targetId))
    }).catch(() => {})
    return () => { active = false }
  }, [kind, supported, targetId])

  if (!supported) return null
  const toggle = async () => {
    setBusy(true); setError('')
    try {
      await api[saved ? 'delete' : 'put'](`/${kind === 'job' ? 'saved-jobs' : 'saved-talent'}/${targetId}`)
      setSaved((value) => !value)
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  const subject = kind === 'job' ? 'job' : 'freelancer'
  const label = saved ? `Remove saved ${subject}` : `Save ${subject}`

  return <span className="marketplace-save-control"><button type="button" className={`marketplace-save-button ${saved ? 'saved' : ''}`} disabled={busy} onClick={toggle} aria-pressed={saved} aria-label={label} title={label}><BookmarkIcon filled={saved} /><span>{saved ? 'Saved' : 'Save'}</span></button>{error && <small>{error}</small>}</span>
}
