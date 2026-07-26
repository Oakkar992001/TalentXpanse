import { useEffect, useState } from 'react'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'

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

  return <span className="marketplace-save-control"><button type="button" className={saved ? 'saved' : ''} disabled={busy} onClick={toggle} aria-pressed={saved}>{saved ? 'Saved' : `Save ${kind === 'job' ? 'job' : 'freelancer'}`}</button>{error && <small>{error}</small>}</span>
}
