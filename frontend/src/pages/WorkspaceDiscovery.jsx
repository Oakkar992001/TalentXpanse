import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import '../workspace-discovery.css'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const categories = ['Development & IT', 'Design & Creative', 'Writing & Translation', 'Sales & Marketing', 'Admin & Support']
const scopes = [['all', 'All'], ['jobs', 'Jobs'], ['talent', 'Freelancers'], ['saved', 'Saved']]
const filterKeys = ['q', 'category', 'skill', 'budget_type', 'experience_level', 'min_budget', 'max_budget', 'location', 'min_rate', 'max_rate', 'availability', 'sort']

function ResultCard({ job, profile, canSave, saved, saving, onToggleSave }) {
  const navigate = useNavigate()
  const isJob = Boolean(job)
  const item = job || profile
  const saveKind = isJob ? 'job' : 'talent'
  const id = isJob ? job.id : profile.id
  const detailPath = isJob ? `/search/jobs/${job.id}` : `/search/freelancers/${profile.user_id}`
  const openDetail = () => navigate(detailPath)
  const openOnKeyboard = (event) => {
    if (event.target.closest('a, button')) return
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      openDetail()
    }
  }

  return <article className="discovery-result-card" role="link" tabIndex={0} onClick={(event) => { if (!event.target.closest('a, button')) openDetail() }} onKeyDown={openOnKeyboard}>
    {!isJob && <span className="talent-avatar">{profile.user?.profile_photo_url ? <img src={profile.user.profile_photo_url} alt="" /> : profile.user?.name?.slice(0, 1)}</span>}
    <p>{isJob ? job.category : profile.location || 'Myanmar'}</p>
    <h3>{isJob ? job.title : profile.user?.name}</h3>
    <small>{isJob ? `${job.client?.client_profile?.company_name || job.client?.name} | ${job.duration || 'Flexible'}` : profile.title || 'Freelancer'}</small>
    {!isJob && profile.user?.reliability_summary?.tier_label && <span className="search-reliability-tier">{profile.user.reliability_summary.tier_label} reliability</span>}
    <div>{item.skills?.map((skill) => <span key={skill}>{skill}</span>)}</div>
    <b>{isJob ? job.budget_type === 'hourly' ? `${money(job.budget_min)}/hr` : `${money(job.budget_min)} - ${money(job.budget_max)}` : profile.user?.trust_summary?.average_rating ? `★ ${profile.user.trust_summary.average_rating}` : 'New on TalentXpanse'}</b>
    <footer><Link to={detailPath}>View {isJob ? 'opportunity' : 'profile'}</Link>{canSave && <button type="button" className={`save-result-button ${saved ? 'saved' : ''}`} disabled={saving} onClick={() => onToggleSave(saveKind, id, item)}>{saved ? 'Saved' : 'Save'}</button>}</footer>
  </article>
}

function Pager({ pagination, onPage }) {
  if (!pagination || pagination.last_page < 2) return null
  return <nav className="search-pager" aria-label="Search result pages"><button disabled={pagination.current_page === 1} onClick={() => onPage(pagination.current_page - 1)}>Previous</button><span>Page {pagination.current_page} of {pagination.last_page}</span><button disabled={pagination.current_page === pagination.last_page} onClick={() => onPage(pagination.current_page + 1)}>Next</button></nav>
}

export default function SearchResultsScreen() {
  const { user, errorMessage } = useAuth()
  const confirm = useConfirmation()
  const [params, setParams] = useSearchParams()
  const [results, setResults] = useState({ jobs: [], talent: [], pagination: {} })
  const [savedItems, setSavedItems] = useState({ job_ids: [], talent_ids: [], jobs: [], talent: [] })
  const [savedSearches, setSavedSearches] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(null)
  const [saveSearchOpen, setSaveSearchOpen] = useState(false)
  const [searchName, setSearchName] = useState('')
  const [alertsEnabled, setAlertsEnabled] = useState(true)
  const [savingSearch, setSavingSearch] = useState(false)
  const scope = params.get('scope') || 'all'
  const query = params.get('q') || ''
  const searchParamString = params.toString()
  const requestParams = useMemo(() => Object.fromEntries(new URLSearchParams(searchParamString).entries()), [searchParamString])
  const userId = user?.id
  const canSaveJobs = user?.roles?.includes('freelancer')
  const canSaveTalent = user?.roles?.includes('client')
  const canSaveSearch = ['jobs', 'talent'].includes(scope)

  const loadSaved = useCallback(async () => {
    const { data } = await api.get('/marketplace-saves')
    setSavedItems(data.data)
  }, [])
  const loadSavedSearches = useCallback(async () => {
    const { data } = await api.get('/marketplace-saved-searches')
    setSavedSearches(data.data)
  }, [])

  useEffect(() => {
    if (!user?.id) return undefined
    loadSaved().catch(() => {})
    loadSavedSearches().catch(() => {})
    return undefined
  }, [user?.id, loadSaved, loadSavedSearches])

  useEffect(() => {
    if (!userId) return undefined
    if (scope === 'saved') { setLoading(false); return undefined }
    let active = true
    setLoading(true)
    setError('')
    api.get('/search', { params: requestParams })
      .then(({ data }) => { if (active) setResults(data.data) })
      .catch((requestError) => { if (active) setError(errorMessage(requestError)) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [requestParams, scope, userId, errorMessage])

  if (!user) return <section className="simple-page"><h1>Search the marketplace</h1><p>Log in to search jobs and freelancers.</p><Link className="button button-primary" to="/login">Log in</Link></section>

  const updateParams = (updates) => {
    const next = new URLSearchParams(params)
    Object.entries(updates).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key))
    setParams(next)
  }
  const changeScope = (nextScope) => setParams(query ? { q: query, scope: nextScope } : { scope: nextScope })
  const submit = (event) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const next = { scope, q: data.get('q')?.trim() || '' }
    filterKeys.slice(1).forEach((key) => { if (data.get(key)) next[key] = data.get(key) })
    setParams(next)
  }
  const toggleSave = async (kind, id, item) => {
    const idsKey = kind === 'job' ? 'job_ids' : 'talent_ids'
    const itemsKey = kind === 'job' ? 'jobs' : 'talent'
    const isSaved = savedItems[idsKey].includes(id)
    setSaving(`${kind}-${id}`)
    setError('')
    try {
      await api[isSaved ? 'delete' : 'put'](`/${kind === 'job' ? 'saved-jobs' : 'saved-talent'}/${id}`)
      setSavedItems((current) => ({ ...current, [idsKey]: isSaved ? current[idsKey].filter((savedId) => savedId !== id) : [...current[idsKey], id], [itemsKey]: isSaved ? current[itemsKey].filter((savedItem) => savedItem.id !== id) : [...current[itemsKey], item] }))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setSaving(null) }
  }
  const saveSearch = async (event) => {
    event.preventDefault()
    setSavingSearch(true)
    setError('')
    try {
      const filters = Object.fromEntries([...params.entries()].filter(([key]) => filterKeys.includes(key)))
      const { data } = await api.post('/marketplace-saved-searches', { name: searchName.trim(), scope, filters, alerts_enabled: alertsEnabled })
      setSavedSearches((current) => [data.data, ...current])
      setSaveSearchOpen(false)
      setSearchName('')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setSavingSearch(false) }
  }
  const runSavedSearch = (savedSearch) => {
    setSaveSearchOpen(false)
    setParams({ scope: savedSearch.scope, ...(savedSearch.filters || {}) })
  }
  const updateSavedSearch = async (savedSearch, updates) => {
    setSaving(`search-${savedSearch.id}`)
    try {
      const { data } = await api.patch(`/marketplace-saved-searches/${savedSearch.id}`, updates)
      setSavedSearches((current) => current.map((item) => item.id === savedSearch.id ? data.data : item))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setSaving(null) }
  }
  const deleteSavedSearch = async (savedSearch) => {
    if (!await confirm({ title: `Delete “${savedSearch.name}”?`, message: 'This removes the saved filters and turns off its alerts. You can create it again later.', confirmLabel: 'Delete search' })) return
    setSaving(`search-${savedSearch.id}`)
    try {
      await api.delete(`/marketplace-saved-searches/${savedSearch.id}`)
      setSavedSearches((current) => current.filter((item) => item.id !== savedSearch.id))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setSaving(null) }
  }

  const pagination = results.pagination?.[scope]
  const activeResults = scope === 'jobs' ? results.jobs : scope === 'talent' ? results.talent : scope === 'saved' ? [...savedItems.jobs, ...savedItems.talent] : [...results.jobs, ...results.talent]
  const cardProps = (kind, item) => ({ [kind]: item, canSave: kind === 'job' ? canSaveJobs : canSaveTalent, saved: (kind === 'job' ? savedItems.job_ids : savedItems.talent_ids).includes(item.id), saving: saving === `${kind === 'job' ? 'job' : 'talent'}-${item.id}`, onToggleSave: toggleSave })

  return <section className="workspace-discovery universal-search-page"><header><div><p className="eyebrow">Marketplace search</p><h1>Find the right work or person.</h1><p>Search live jobs and freelancer profiles from one place.</p></div></header>
    <div className="search-tabs" role="tablist">{scopes.map(([value, label]) => <button key={value} role="tab" aria-selected={scope === value} className={scope === value ? 'active' : ''} onClick={() => changeScope(value)}>{label}{value === 'saved' && (savedItems.job_ids.length + savedItems.talent_ids.length > 0) ? ` (${savedItems.job_ids.length + savedItems.talent_ids.length})` : ''}</button>)}</div>
    {scope !== 'saved' && <form key={params.toString()} className="marketplace-search-form" onSubmit={submit}><div className="discovery-search"><input name="q" defaultValue={query} placeholder="Try programming, Laravel, or graphic design" /><button className="button button-primary">Search</button></div>{scope === 'jobs' && <div className="search-filters"><select name="category" defaultValue={params.get('category') || ''}><option value="">All categories</option>{categories.map((category) => <option key={category}>{category}</option>)}</select><input name="skill" defaultValue={params.get('skill') || ''} placeholder="Exact skill (e.g. Laravel)" /><select name="budget_type" defaultValue={params.get('budget_type') || ''}><option value="">Any project type</option><option value="fixed">Fixed price</option><option value="hourly">Hourly</option></select><select name="experience_level" defaultValue={params.get('experience_level') || ''}><option value="">Any experience level</option><option value="entry">Entry level</option><option value="intermediate">Intermediate</option><option value="expert">Expert</option></select><input name="min_budget" type="number" min="0" defaultValue={params.get('min_budget') || ''} placeholder="Min budget (MMK)" /><input name="max_budget" type="number" min="0" defaultValue={params.get('max_budget') || ''} placeholder="Max budget (MMK)" /><select name="sort" defaultValue={params.get('sort') || 'newest'} aria-label="Sort jobs"><option value="newest">Newest jobs</option><option value="budget_high">Highest budget</option><option value="budget_low">Lowest budget</option></select></div>}{scope === 'talent' && <div className="search-filters"><input name="skill" defaultValue={params.get('skill') || ''} placeholder="Exact skill (e.g. React)" /><input name="location" defaultValue={params.get('location') || ''} placeholder="Location" /><input name="min_rate" type="number" min="0" defaultValue={params.get('min_rate') || ''} placeholder="Min hourly rate (MMK)" /><input name="max_rate" type="number" min="0" defaultValue={params.get('max_rate') || ''} placeholder="Max hourly rate (MMK)" /><select name="availability" defaultValue={params.get('availability') || ''}><option value="">All availability</option><option value="available">Available now</option></select><select name="sort" defaultValue={params.get('sort') || 'newest'} aria-label="Sort freelancers"><option value="newest">Newest profiles</option><option value="rate_high">Highest hourly rate</option><option value="rate_low">Lowest hourly rate</option></select></div>}</form>}
    {canSaveSearch && <section className="saved-search-tools"><div><b>Save this search</b><p>Keep these filters and choose whether you want matching-job alerts.</p></div><button className="button button-outline" onClick={() => setSaveSearchOpen((value) => !value)}>{saveSearchOpen ? 'Cancel' : 'Save search'}</button>{saveSearchOpen && <form onSubmit={saveSearch}><label>Name this search<input required minLength="2" maxLength="100" value={searchName} onChange={(event) => setSearchName(event.target.value)} placeholder={scope === 'jobs' ? 'e.g. Laravel projects' : 'e.g. Available React developers'} /></label><label className="saved-search-alert"><input type="checkbox" checked={alertsEnabled} onChange={(event) => setAlertsEnabled(event.target.checked)} /> Send a daily TalentXpanse alert when new jobs match</label><button disabled={savingSearch || !searchName.trim()} className="button button-primary">{savingSearch ? 'Saving...' : 'Save search'}</button></form>}</section>}
    {savedSearches.length > 0 && <section className="saved-search-list"><div><p className="eyebrow">Your saved searches</p><h2>Return to the work you care about.</h2></div><div>{savedSearches.map((savedSearch) => <article key={savedSearch.id}><button onClick={() => runSavedSearch(savedSearch)}><b>{savedSearch.name}</b><small>{savedSearch.scope === 'jobs' ? 'Job search' : 'Freelancer search'}{savedSearch.filters?.q ? ` | ${savedSearch.filters.q}` : ''}</small></button><label><input type="checkbox" checked={savedSearch.alerts_enabled} disabled={saving === `search-${savedSearch.id}`} onChange={(event) => updateSavedSearch(savedSearch, { alerts_enabled: event.target.checked })} /> Alerts</label><button className="saved-search-delete" disabled={saving === `search-${savedSearch.id}`} onClick={() => deleteSavedSearch(savedSearch)} aria-label={`Delete ${savedSearch.name}`}>Remove</button></article>)}</div></section>}
    {error && <p className="form-notice">{error}</p>}{loading ? <p className="discovery-empty">Searching marketplace...</p> : <><>{scope === 'all' && <div className="search-all-results"><section className="search-result-section"><div className="result-heading"><h2>Jobs</h2><button onClick={() => changeScope('jobs')}>See all jobs</button></div><div className="discovery-grid">{results.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section><section className="search-result-section"><div className="result-heading"><h2>Freelancers</h2><button onClick={() => changeScope('talent')}>See all freelancers</button></div><div className="discovery-grid talent-grid">{results.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section></div>}{scope === 'jobs' && <section className="search-result-section"><h2>Jobs</h2><div className="discovery-grid">{results.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section>}{scope === 'talent' && <section className="search-result-section"><h2>Freelancers</h2><div className="discovery-grid talent-grid">{results.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section>}{scope === 'saved' && <div className="search-all-results"><section className="search-result-section"><h2>Saved jobs</h2><div className="discovery-grid">{savedItems.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section><section className="search-result-section"><h2>Saved freelancers</h2><div className="discovery-grid talent-grid">{savedItems.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section></div>}{!activeResults.length && <p className="discovery-empty">{scope === 'saved' ? 'Nothing saved yet. Save a job or freelancer from search results to return to it later.' : query ? `No results for "${query}". Try a different skill or keyword.` : 'Start with a skill, role, company, or project type.'}</p>}<Pager pagination={pagination} onPage={(page) => updateParams({ page: String(page) })} /></></>}</section>
}
