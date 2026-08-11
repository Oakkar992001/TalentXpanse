import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'
import TrustSummary from '../components/TrustSummary'
import '../trust.css'

export default function WorkspaceSetupScreen() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { user, addRole, errorMessage } = useAuth()
  const { t } = usePreferences()
  const role = params.get('role') === 'client' ? 'client' : 'freelancer'
  const [form, setForm] = useState({ company_name: '', company_description: '', website: '', industry: '', location: '', title: '', freelancer_location: '' })
  const [trustSummary, setTrustSummary] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const exists = user?.roles?.includes(role)
  const isClient = role === 'client'

  useEffect(() => {
    if (!exists) return
    const endpoint = isClient ? '/client-profile' : '/freelancer-profile'
    api.get(endpoint).then(({ data }) => {
      const profile = isClient ? data.data.client_profile || {} : data.data.freelancer_profile || {}
      setTrustSummary(data.data.trust_summary || null)
      if (isClient) setForm((value) => ({ ...value, company_name: profile.company_name || '', company_description: profile.company_description || '', website: profile.website || '', industry: profile.industry || '', location: profile.location || '' }))
      else setForm((value) => ({ ...value, title: profile.title || '', freelancer_location: profile.location || '' }))
    }).catch((requestError) => setError(errorMessage(requestError)))
  }, [errorMessage, exists, isClient])

  const submit = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      if (!exists) await addRole(role)
      const response = isClient
        ? await api.put('/client-profile', { company_name: form.company_name.trim() || null, company_description: form.company_description.trim() || null, website: form.website.trim() || null, industry: form.industry.trim() || null, location: form.location.trim() || null })
        : await api.put('/freelancer-profile', { title: form.title.trim() || null, location: form.freelancer_location.trim() || null })
      setTrustSummary(response.data.data.trust_summary || null)
      navigate(isClient ? '/dashboard?role=client' : '/profile')
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  if (!user) return null

  return <section className="workspace-setup"><div className="workspace-setup-card">
    <p className="eyebrow">{exists ? t('setup.profile_details', 'Profile details') : t('setup.add_workspace', 'Add a workspace')}</p>
    <h1>{isClient ? t('setup.client_title', 'Tell freelancers who they will work with.') : t('setup.freelancer_title', 'Set up your freelancer workspace.')}</h1>
    <p>{isClient ? t('setup.client_intro', 'You can hire as an individual or as a company. Company details are optional, but a complete profile helps freelancers make informed decisions.') : t('setup.freelancer_intro', 'This is separate from your client workspace. You can complete your full portfolio next.')}</p>
    {isClient && <TrustSummary summary={trustSummary} />}
    {error && <p className="form-notice" role="alert">{error}</p>}
    <form onSubmit={submit}>
      {isClient ? <>
        <label>{t('setup.company_name', 'Company or business name')} <small>{t('common.optional', 'Optional')}</small><input value={form.company_name} onChange={(event) => setForm({ ...form, company_name: event.target.value })} placeholder={t('setup.company_name_hint', 'Leave blank if you are hiring as an individual')} /></label>
        <label>{t('setup.industry', 'Industry')} <small>{t('common.optional', 'Optional')}</small><input value={form.industry} onChange={(event) => setForm({ ...form, industry: event.target.value })} placeholder={t('setup.industry_hint', 'e.g. E-commerce, Financial services')} /></label>
        <label>{t('profile.location', 'Location')} <small>{t('common.optional', 'Optional')}</small><input value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} placeholder={t('profile.location_hint', 'Yangon, Myanmar')} /></label>
        <label>{t('setup.website', 'Website')} <small>{t('common.optional', 'Optional')}</small><input type="url" value={form.website} onChange={(event) => setForm({ ...form, website: event.target.value })} placeholder="https://example.com" /></label>
        <label>{t('setup.about_company', 'About you or your company')} <small>{t('common.optional', 'Optional')}</small><textarea value={form.company_description} onChange={(event) => setForm({ ...form, company_description: event.target.value })} placeholder={t('setup.about_company_hint', 'What are you building, and what kind of help do you need?')} /></label>
      </> : <>
        <label>{t('profile.title', 'Professional title')}<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('setup.freelancer_title_hint', 'e.g. Product Designer')} /></label>
        <label>{t('profile.location', 'Location')}<input value={form.freelancer_location} onChange={(event) => setForm({ ...form, freelancer_location: event.target.value })} placeholder={t('profile.location_hint', 'Yangon, Myanmar')} /></label>
      </>}
      <button disabled={busy} className="button button-primary">{busy ? t('common.saving', 'Saving...') : exists ? t('setup.save_profile', 'Save profile details') : t('setup.create_workspace', `Create ${isClient ? 'client' : 'freelancer'} workspace`, { role: isClient ? t('common.client', 'client') : t('common.freelancer', 'freelancer') })}</button>
    </form>
  </div></section>
}
