import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import { usePreferences } from '../contexts/PreferencesContext'
import '../workspace-discovery.css'

const money = (amount) => `Ks ${Number(amount || 0).toLocaleString()}`
const categories = ['Development & IT', 'Design & Creative', 'Writing & Translation', 'Sales & Marketing', 'Admin & Support']
const scopes = ['all', 'jobs', 'talent', 'saved']
const filterKeys = ['q', 'category', 'skill', 'budget_type', 'experience_level', 'min_budget', 'max_budget', 'location', 'min_rate', 'max_rate', 'availability', 'sort']

function ResultCard({ job, profile, canSave, saved, saving, onToggleSave }) {
  const navigate = useNavigate()
  const { t } = usePreferences()
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
    <p>{isJob ? job.category : profile.location || t('search.myanmar', 'Myanmar')}</p>
    <h3>{isJob ? job.title : profile.user?.name}</h3>
    <small>{isJob ? `${job.client?.client_profile?.company_name || job.client?.name} | ${job.duration || t('dashboard.flexible', 'Flexible')}` : profile.title || t('common.freelancer', 'Freelancer')}</small>
    {!isJob && profile.user?.reliability_summary?.tier_label && <span className="search-reliability-tier">{t('search.reliability', `${profile.user.reliability_summary.tier_label} reliability`, { tier: profile.user.reliability_summary.tier_label })}</span>}
    <div>{item.skills?.map((skill) => <span key={skill}>{skill}</span>)}</div>
    <b>{isJob ? job.budget_type === 'hourly' ? `${money(job.budget_min)}/hr` : `${money(job.budget_min)} - ${money(job.budget_max)}` : profile.user?.trust_summary?.average_rating ? `★ ${profile.user.trust_summary.average_rating}` : t('search.new_talent', 'New on TalentXpanse')}</b>
    <footer><Link to={detailPath}>{t('search.view_item', `View ${isJob ? 'opportunity' : 'profile'}`, { item: isJob ? t('search.opportunity', 'opportunity') : t('nav.profile', 'profile') })}</Link>{canSave && <button type="button" className={`save-result-button ${saved ? 'saved' : ''}`} disabled={saving} onClick={() => onToggleSave(saveKind, id, item)}>{saved ? t('search.saved', 'Saved') : t('common.save', 'Save')}</button>}</footer>
  </article>
}

function Pager({ pagination, onPage }) {
  const { t } = usePreferences()
  if (!pagination || pagination.last_page < 2) return null
  return <nav className="search-pager" aria-label={t('search.pages', 'Search result pages')}><button disabled={pagination.current_page === 1} onClick={() => onPage(pagination.current_page - 1)}>{t('common.previous', 'Previous')}</button><span>{t('search.page_of', `Page ${pagination.current_page} of ${pagination.last_page}`, { page: pagination.current_page, total: pagination.last_page })}</span><button disabled={pagination.current_page === pagination.last_page} onClick={() => onPage(pagination.current_page + 1)}>{t('common.next', 'Next')}</button></nav>
}

export default function SearchResultsScreen() {
  const { user, errorMessage } = useAuth()
  const { t } = usePreferences()
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

  if (!user) return <section className="simple-page"><h1>{t('search.marketplace', 'Search the marketplace')}</h1><p>{t('search.signin_intro', 'Log in to search jobs and freelancers.')}</p><Link className="button button-primary" to="/login">{t('nav.login', 'Log in')}</Link></section>

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
    if (!await confirm({ title: t('search.delete_title', `Delete “${savedSearch.name}”?`, { name: savedSearch.name }), message: t('search.delete_detail', 'This removes the saved filters and turns off its alerts. You can create it again later.'), confirmLabel: t('search.delete_search', 'Delete search') })) return
    setSaving(`search-${savedSearch.id}`)
    try {
      await api.delete(`/marketplace-saved-searches/${savedSearch.id}`)
      setSavedSearches((current) => current.filter((item) => item.id !== savedSearch.id))
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setSaving(null) }
  }

  const pagination = results.pagination?.[scope]
  const activeResults = scope === 'jobs' ? results.jobs : scope === 'talent' ? results.talent : scope === 'saved' ? [...savedItems.jobs, ...savedItems.talent] : [...results.jobs, ...results.talent]
  const cardProps = (kind, item) => ({ [kind]: item, canSave: kind === 'job' ? canSaveJobs : canSaveTalent, saved: (kind === 'job' ? savedItems.job_ids : savedItems.talent_ids).includes(item.id), saving: saving === `${kind === 'job' ? 'job' : 'talent'}-${item.id}`, onToggleSave: toggleSave })
  const scopeTitle = (value) => t(`search.scope_${value}`, { all: 'All', jobs: 'Jobs', talent: 'Freelancers', saved: 'Saved' }[value])

  return <section className="workspace-discovery universal-search-page"><header><div><p className="eyebrow">{t('search.marketplace_label', 'Marketplace search')}</p><h1>{t('search.find_right', 'Find the right work or person.')}</h1><p>{t('search.find_intro', 'Search live jobs and freelancer profiles from one place.')}</p></div></header>
    <div className="search-tabs" role="tablist">{scopes.map((value) => <button key={value} role="tab" aria-selected={scope === value} className={scope === value ? 'active' : ''} onClick={() => changeScope(value)}>{scopeTitle(value)}{value === 'saved' && (savedItems.job_ids.length + savedItems.talent_ids.length > 0) ? ` (${savedItems.job_ids.length + savedItems.talent_ids.length})` : ''}</button>)}</div>
    {scope !== 'saved' && <form key={params.toString()} className="marketplace-search-form" onSubmit={submit}><div className="discovery-search"><input name="q" defaultValue={query} placeholder={t('search.query_hint', 'Search jobs, skills, or freelancers')} /><button className="button button-primary">{t('common.search', 'Search')}</button></div>{scope === 'jobs' && <div className="search-filters"><select name="category" defaultValue={params.get('category') || ''}><option value="">{t('search.all_categories', 'All categories')}</option>{categories.map((category) => <option key={category}>{category}</option>)}</select><input name="skill" defaultValue={params.get('skill') || ''} placeholder={t('search.exact_laravel', 'Skill')} /><select name="budget_type" defaultValue={params.get('budget_type') || ''}><option value="">{t('search.any_project', 'Any project type')}</option><option value="fixed">{t('search.fixed_price', 'Fixed price')}</option><option value="hourly">{t('search.hourly', 'Hourly')}</option></select><select name="experience_level" defaultValue={params.get('experience_level') || ''}><option value="">{t('search.any_experience', 'Any experience level')}</option><option value="entry">{t('search.entry', 'Entry level')}</option><option value="intermediate">{t('search.intermediate', 'Intermediate')}</option><option value="expert">{t('search.expert', 'Expert')}</option></select><input name="min_budget" type="number" min="0" defaultValue={params.get('min_budget') || ''} placeholder={t('search.min_budget', 'Min budget (MMK)')} /><input name="max_budget" type="number" min="0" defaultValue={params.get('max_budget') || ''} placeholder={t('search.max_budget', 'Max budget (MMK)')} /><select name="sort" defaultValue={params.get('sort') || 'newest'} aria-label={t('search.sort_jobs', 'Sort jobs')}><option value="newest">{t('search.newest_jobs', 'Newest jobs')}</option><option value="budget_high">{t('search.highest_budget', 'Highest budget')}</option><option value="budget_low">{t('search.lowest_budget', 'Lowest budget')}</option></select></div>}{scope === 'talent' && <div className="search-filters"><input name="skill" defaultValue={params.get('skill') || ''} placeholder={t('search.exact_react', 'Skill')} /><input name="location" defaultValue={params.get('location') || ''} placeholder={t('profile.location', 'Location')} /><input name="min_rate" type="number" min="0" defaultValue={params.get('min_rate') || ''} placeholder={t('search.min_rate', 'Min hourly rate (MMK)')} /><input name="max_rate" type="number" min="0" defaultValue={params.get('max_rate') || ''} placeholder={t('search.max_rate', 'Max hourly rate (MMK)')} /><select name="availability" defaultValue={params.get('availability') || ''}><option value="">{t('search.all_availability', 'All availability')}</option><option value="available">{t('search.available_now', 'Available now')}</option></select><select name="sort" defaultValue={params.get('sort') || 'newest'} aria-label={t('search.sort_freelancers', 'Sort freelancers')}><option value="newest">{t('search.newest_profiles', 'Newest profiles')}</option><option value="rate_high">{t('search.highest_rate', 'Highest hourly rate')}</option><option value="rate_low">{t('search.lowest_rate', 'Lowest hourly rate')}</option></select></div>}</form>}
    {canSaveSearch && <section className="saved-search-tools"><div><b>{t('search.save_this', 'Save this search')}</b><p>{t('search.save_detail', 'Keep these filters and choose whether you want matching-job alerts.')}</p></div><button className="button button-outline" onClick={() => setSaveSearchOpen((value) => !value)}>{saveSearchOpen ? t('common.cancel', 'Cancel') : t('search.save_search', 'Save search')}</button>{saveSearchOpen && <form onSubmit={saveSearch}><label>{t('search.name_search', 'Name this search')}<input required minLength="2" maxLength="100" value={searchName} onChange={(event) => setSearchName(event.target.value)} placeholder={scope === 'jobs' ? t('search.jobs_name_hint', 'Name this search') : t('search.talent_name_hint', 'Name this search')} /></label><label className="saved-search-alert"><input type="checkbox" checked={alertsEnabled} onChange={(event) => setAlertsEnabled(event.target.checked)} /> {t('search.daily_alert', 'Send a daily TalentXpanse alert when new jobs match')}</label><button disabled={savingSearch || !searchName.trim()} className="button button-primary">{savingSearch ? t('common.saving', 'Saving...') : t('search.save_search', 'Save search')}</button></form>}</section>}
    {savedSearches.length > 0 && <section className="saved-search-list"><div><p className="eyebrow">{t('search.your_saved', 'Your saved searches')}</p><h2>{t('search.return_work', 'Return to the work you care about.')}</h2></div><div>{savedSearches.map((savedSearch) => <article key={savedSearch.id}><button onClick={() => runSavedSearch(savedSearch)}><b>{savedSearch.name}</b><small>{savedSearch.scope === 'jobs' ? t('search.job_search', 'Job search') : t('search.talent_search', 'Freelancer search')}{savedSearch.filters?.q ? ` | ${savedSearch.filters.q}` : ''}</small></button><label><input type="checkbox" checked={savedSearch.alerts_enabled} disabled={saving === `search-${savedSearch.id}`} onChange={(event) => updateSavedSearch(savedSearch, { alerts_enabled: event.target.checked })} /> {t('search.alerts', 'Alerts')}</label><button className="saved-search-delete" disabled={saving === `search-${savedSearch.id}`} onClick={() => deleteSavedSearch(savedSearch)} aria-label={t('search.delete_named', `Delete ${savedSearch.name}`, { name: savedSearch.name })}>{t('common.remove', 'Remove')}</button></article>)}</div></section>}
    {error && <p className="form-notice">{error}</p>}{loading ? <p className="discovery-empty">{t('search.searching', 'Searching marketplace...')}</p> : <><>{scope === 'all' && <div className="search-all-results"><section className="search-result-section"><div className="result-heading"><h2>{scopeTitle('jobs')}</h2><button onClick={() => changeScope('jobs')}>{t('search.see_all_jobs', 'See all jobs')}</button></div><div className="discovery-grid">{results.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section><section className="search-result-section"><div className="result-heading"><h2>{scopeTitle('talent')}</h2><button onClick={() => changeScope('talent')}>{t('search.see_all_freelancers', 'See all freelancers')}</button></div><div className="discovery-grid talent-grid">{results.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section></div>}{scope === 'jobs' && <section className="search-result-section"><h2>{scopeTitle('jobs')}</h2><div className="discovery-grid">{results.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section>}{scope === 'talent' && <section className="search-result-section"><h2>{scopeTitle('talent')}</h2><div className="discovery-grid talent-grid">{results.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section>}{scope === 'saved' && <div className="search-all-results"><section className="search-result-section"><h2>{t('search.saved_jobs', 'Saved jobs')}</h2><div className="discovery-grid">{savedItems.jobs.map((job) => <ResultCard key={job.id} {...cardProps('job', job)} />)}</div></section><section className="search-result-section"><h2>{t('search.saved_freelancers', 'Saved freelancers')}</h2><div className="discovery-grid talent-grid">{savedItems.talent.map((profile) => <ResultCard key={profile.id} {...cardProps('profile', profile)} />)}</div></section></div>}{!activeResults.length && <p className="discovery-empty">{scope === 'saved' ? t('search.nothing_saved', 'Nothing saved yet. Save a job or freelancer from search results to return to it later.') : query ? t('search.no_results', `No results for "${query}". Try a different skill or keyword.`, { query }) : t('search.start_search', 'Start with a skill, role, company, or project type.')}</p>}<Pager pagination={pagination} onPage={(page) => updateParams({ page: String(page) })} /></></>}</section>
}
