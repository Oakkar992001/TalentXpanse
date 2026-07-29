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
    <p className="eyebrow">{exists ? 'Profile details' : 'Add a workspace'}</p>
    <h1>{isClient ? 'Tell freelancers who they will work with.' : 'Set up your freelancer workspace.'}</h1>
    <p>{isClient ? 'You can hire as an individual or as a company. Company details are optional, but a complete profile helps freelancers make informed decisions.' : 'This is separate from your client workspace. You can complete your full portfolio next.'}</p>
    {isClient && <TrustSummary summary={trustSummary} />}
    {error && <p className="form-notice" role="alert">{error}</p>}
    <form onSubmit={submit}>
      {isClient ? <>
        <label>Company or business name <small>Optional</small><input value={form.company_name} onChange={(event) => setForm({ ...form, company_name: event.target.value })} placeholder="Leave blank if you are hiring as an individual" /></label>
        <label>Industry <small>Optional</small><input value={form.industry} onChange={(event) => setForm({ ...form, industry: event.target.value })} placeholder="e.g. E-commerce, Financial services" /></label>
        <label>Location <small>Optional</small><input value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} placeholder="Yangon, Myanmar" /></label>
        <label>Website <small>Optional</small><input type="url" value={form.website} onChange={(event) => setForm({ ...form, website: event.target.value })} placeholder="https://example.com" /></label>
        <label>About you or your company <small>Optional</small><textarea value={form.company_description} onChange={(event) => setForm({ ...form, company_description: event.target.value })} placeholder="What are you building, and what kind of help do you need?" /></label>
      </> : <>
        <label>Professional title<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="e.g. Product Designer" /></label>
        <label>Location<input value={form.freelancer_location} onChange={(event) => setForm({ ...form, freelancer_location: event.target.value })} placeholder="Yangon, Myanmar" /></label>
      </>}
      <button disabled={busy} className="button button-primary">{busy ? 'Saving...' : exists ? 'Save profile details' : `Create ${isClient ? 'client' : 'freelancer'} workspace`}</button>
    </form>
  </div></section>
}
