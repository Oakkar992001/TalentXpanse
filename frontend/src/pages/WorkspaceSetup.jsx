import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import TrustSummary from '../components/TrustSummary'
import '../trust.css'

export default function WorkspaceSetupScreen() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { user, addRole, errorMessage } = useAuth()
  const role = params.get('role') === 'client' ? 'client' : 'freelancer'
  const [form, setForm] = useState({ company_name: '', company_description: '', website: '', industry: '', location: '', title: '', freelancer_location: '' })
  const [trustSummary, setTrustSummary] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const exists = user?.roles?.includes(role)

  useEffect(() => {
    if (!exists) return
    const endpoint = role === 'client' ? '/client-profile' : '/freelancer-profile'
    api.get(endpoint).then(({ data }) => {
      const profile = role === 'client' ? data.data.client_profile || {} : data.data.freelancer_profile || {}
      setTrustSummary(data.data.trust_summary || null)
      if (role === 'client') setForm((value) => ({ ...value, company_name: profile.company_name || '', company_description: profile.company_description || '', website: profile.website || '', industry: profile.industry || '', location: profile.location || '' }))
      else setForm((value) => ({ ...value, title: profile.title || '', freelancer_location: profile.location || '' }))
    }).catch((requestError) => setError(errorMessage(requestError)))
  }, [exists, role])

  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError('')
    try {
      if (!exists) await addRole(role)
      const response = role === 'client'
        ? await api.put('/client-profile', { company_name: form.company_name, company_description: form.company_description || null, website: form.website || null, industry: form.industry || null, location: form.location || null })
        : await api.put('/freelancer-profile', { title: form.title || null, location: form.freelancer_location || null })
      setTrustSummary(response.data.data.trust_summary || null)
      navigate(role === 'freelancer' ? '/profile' : '/dashboard?role=client')
    } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) }
  }

  if (!user) return null
  const isClient = role === 'client'
  return <section className="workspace-setup"><div className="workspace-setup-card"><p className="eyebrow">{exists ? 'Workspace settings' : 'Add a workspace'}</p><h1>{isClient ? 'Tell freelancers about your company.' : 'Set up your freelancer workspace.'}</h1><p>{isClient ? 'A clear company profile and completed-project record help freelancers decide who they want to work with.' : 'This is separate from your client/company workspace. You can complete your full portfolio next.'}</p>{isClient && <TrustSummary summary={trustSummary} />}{error && <p className="form-notice">{error}</p>}<form onSubmit={submit}>{isClient ? <><label>Company name<input required value={form.company_name} onChange={(event) => setForm({ ...form, company_name: event.target.value })} placeholder="Your company or business name" /></label><label>Industry<input value={form.industry} onChange={(event) => setForm({ ...form, industry: event.target.value })} placeholder="e.g. E-commerce, Financial services" /></label><label>Company location<input value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} placeholder="Yangon, Myanmar" /></label><label>Website <small>Optional</small><input type="url" value={form.website} onChange={(event) => setForm({ ...form, website: event.target.value })} placeholder="https://example.com" /></label><label>About the company<textarea value={form.company_description} onChange={(event) => setForm({ ...form, company_description: event.target.value })} placeholder="What does your company build or offer?" /></label></> : <><label>Professional title<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="e.g. Product Designer" /></label><label>Location<input value={form.freelancer_location} onChange={(event) => setForm({ ...form, freelancer_location: event.target.value })} placeholder="Yangon, Myanmar" /></label></>}<button disabled={busy} className="button button-primary">{busy ? 'Saving...' : exists ? 'Save workspace' : `Create ${isClient ? 'client' : 'freelancer'} workspace`}</button></form></div></section>
}
