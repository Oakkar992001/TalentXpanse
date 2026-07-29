import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../app-polish.css'

export default function GlobalSearch({ open, onClose }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [results, setResults] = useState({ jobs: [], talent: [] })
  const [loading, setLoading] = useState(false)

  useEffect(() => { if (open) setQuery('') }, [open])
  useEffect(() => {
    if (!open) return undefined
    const closeOnEscape = (event) => { if (event.key === 'Escape') onClose() }
    document.addEventListener('keydown', closeOnEscape)
    return () => document.removeEventListener('keydown', closeOnEscape)
  }, [onClose, open])
  useEffect(() => {
    if (!open || !user) return undefined
    const timer = setTimeout(async () => {
      setLoading(true)
      try {
        const { data } = await api.get('/search', { params: { q: query, scope: 'all' } })
        setResults(data.data)
      } finally { setLoading(false) }
    }, 180)
    return () => clearTimeout(timer)
  }, [query, open, user])

  if (!open) return null
  const go = (url) => { onClose(); navigate(url) }
  const openResults = () => go(`/search${query.trim() ? `?q=${encodeURIComponent(query.trim())}` : ''}`)
  const empty = !results.jobs.length && !results.talent.length

  return <div className="search-overlay" role="dialog" aria-modal="true" aria-label="Search marketplace">
    <button className="search-backdrop" aria-label="Close search" onClick={onClose} />
    <section className="global-search">
      <form className="search-input" onSubmit={(event) => { event.preventDefault(); openResults() }}>
        <span aria-hidden="true">⌕</span>
        <input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search jobs, skills, or freelancers" />
        <button type="button" onClick={onClose}>Close</button>
      </form>
      <p className="search-hint">Search live jobs and available freelancers.</p>
      {loading ? <p className="search-empty">Searching...</p> : <div className="search-results">
        {results.jobs.length > 0 && <section><h3>Jobs</h3>{results.jobs.map((job) => <button key={job.id} onClick={() => go(`/search/jobs/${job.id}`)}><span>▣</span><div><b>{job.title}</b><small>{job.client?.client_profile?.company_name || job.client?.name} • Ks {Number(job.budget_min || 0).toLocaleString()}</small></div></button>)}</section>}
        {results.talent.length > 0 && <section><h3>Freelancers</h3>{results.talent.map((profile) => <button key={profile.id} onClick={() => go(`/search/freelancers/${profile.user_id}`)}><span>✦</span><div><b>{profile.user?.name}</b><small>{profile.title || 'Freelancer'} • {profile.location || 'Myanmar'} • {profile.user?.trust_summary?.average_rating ? `★ ${profile.user.trust_summary.average_rating}` : 'New'}</small></div></button>)}</section>}
        {empty && <p className="search-empty">{query ? 'No matching jobs or freelancers found.' : 'Start typing to search the marketplace.'}</p>}
        {!empty && <button className="search-view-all" onClick={openResults}>View all results</button>}
      </div>}
    </section>
  </div>
}
