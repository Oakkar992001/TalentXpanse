import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import { usePreferences } from '../contexts/PreferencesContext'
import ProfileReadinessCard from '../components/ProfileReadinessCard'
import TrustSummary from '../components/TrustSummary'
import '../trust.css'

export default function ProfileScreen() {
  const { user, errorMessage, refreshUser } = useAuth()
  const { t } = usePreferences()
  const confirm = useConfirmation()
  const [profile, setProfile] = useState(null)
  const [form, setForm] = useState({ title: '', experience_level: '', bio: '', hourly_rate: '', location: '', skills: '', availability: true })
  const [portfolioForm, setPortfolioForm] = useState({ title: '', description: '', project_url: '' })
  const [editingPortfolioId, setEditingPortfolioId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const [portfolioBusy, setPortfolioBusy] = useState(false)
  const [resumeBusy, setResumeBusy] = useState(false)

  const load = useCallback(async () => {
    try {
      const { data } = await api.get('/freelancer-profile')
      setProfile(data.data)
      const value = data.data.freelancer_profile || {}
      setForm({
        title: value.title || '',
        experience_level: value.experience_level || '',
        bio: value.bio || '',
        hourly_rate: value.hourly_rate || '',
        location: value.location || '',
        skills: (value.skills || []).join(', '),
        availability: Boolean(value.availability),
      })
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }, [errorMessage])

  const hasFreelancerRole = user?.roles?.includes('freelancer')

  useEffect(() => {
    if (hasFreelancerRole) load()
  }, [hasFreelancerRole, load, user?.id])

  if (!user) return <section className="simple-page"><h1>{t('profile.build_profile', 'Build your freelancer profile.')}</h1><p>{t('profile.signin_intro', 'Sign in to show clients your skills and work samples.')}</p><Link className="button button-primary" to="/login">{t('nav.login', 'Log in')}</Link></section>

  if (!hasFreelancerRole) {
    return <section className="simple-page"><p className="eyebrow">{t('nav.freelancer_profile', 'Freelancer profile')}</p><h1>{t('profile.ready_work', 'Ready to show your work?')}</h1><p>{t('profile.add_workspace_intro', 'Add a Freelancer workspace from Settings. You will keep the same sign-in and be guided through the profile setup.')}</p><Link className="button button-primary" to="/settings/account">{t('profile.add_workspace', 'Add Freelancer workspace')}</Link></section>
  }

  const saveProfile = async (event) => {
    event.preventDefault()
    setBusy(true)
    setError('')
    setMessage('')
    try {
      await api.put('/freelancer-profile', {
        ...form,
        hourly_rate: form.hourly_rate ? Number(form.hourly_rate) : null,
        skills: form.skills.split(',').map((skill) => skill.trim()).filter(Boolean),
      })
      await load()
      setMessage(t('profile.saved', 'Profile saved.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setBusy(false)
    }
  }

  const addPortfolioItem = async (event) => {
    event.preventDefault()
    setPortfolioBusy(true)
    setError('')
    setMessage('')
    try {
      if (editingPortfolioId) await api.patch(`/portfolio-items/${editingPortfolioId}`, portfolioForm)
      else await api.post('/portfolio-items', portfolioForm)
      setPortfolioForm({ title: '', description: '', project_url: '' })
      setEditingPortfolioId(null)
      await load()
      setMessage(editingPortfolioId ? t('profile.sample_updated', 'Work sample updated.') : t('profile.sample_added', 'Work sample added.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setPortfolioBusy(false)
    }
  }

  const editPortfolioItem = (item) => {
    setEditingPortfolioId(item.id)
    setPortfolioForm({ title: item.title || '', description: item.description || '', project_url: item.project_url || '' })
    setMessage('')
    setError('')
  }

  const cancelPortfolioEdit = () => {
    setEditingPortfolioId(null)
    setPortfolioForm({ title: '', description: '', project_url: '' })
  }

  const removePortfolioItem = async (itemId) => {
    if (!await confirm({ title: t('profile.remove_sample_title', 'Remove this work sample?'), message: t('profile.remove_sample_detail', 'Clients will no longer see this sample, and it cannot be restored after removal.'), confirmLabel: t('profile.remove_sample', 'Remove sample') })) return
    try {
      await api.delete(`/portfolio-items/${itemId}`)
      if (editingPortfolioId === itemId) cancelPortfolioEdit()
      await load()
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  const uploadResume = async (event) => {
    const resume = event.target.files?.[0]
    event.target.value = ''
    if (!resume) return
    setError('')
    setResumeBusy(true)
    const data = new FormData()
    data.append('resume', resume)
    try {
      await api.post('/freelancer-resume', data)
      await load()
      setMessage(t('profile.cv_uploaded', 'CV uploaded. You can choose it when sending a proposal.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setResumeBusy(false)
    }
  }

  const removeResume = async () => {
    if (!await confirm({ title: t('profile.remove_cv_title', 'Remove saved CV?'), message: t('profile.remove_cv_detail', 'This removes the CV from your profile. CVs already attached to submitted proposals will remain available to those clients.'), confirmLabel: t('profile.remove_cv', 'Remove CV'), tone: 'danger' })) return
    setResumeBusy(true)
    setError('')
    try {
      await api.delete('/freelancer-resume')
      await load()
      setMessage(t('profile.cv_removed', 'CV removed from your profile.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    } finally {
      setResumeBusy(false)
    }
  }

  const uploadPhoto = async (event) => {
    const photo = event.target.files?.[0]
    if (!photo) return
    setError('')
    const data = new FormData()
    data.append('photo', photo)
    try {
      await api.post('/profile-photo', data)
      await refreshUser()
      await load()
      setMessage(t('profile.photo_updated', 'Profile photo updated.'))
    } catch (requestError) {
      setError(errorMessage(requestError))
    }
  }

  return <section className="profile-page">
    <header>
      <p className="eyebrow">{t('nav.freelancer_profile', 'Freelancer profile')}</p>
      <h1>{t('profile.show_best', 'Show clients your best work.')}</h1>
      <p>{t('profile.focused_intro', 'Keep your profile focused. Add only work you would be happy to discuss in a proposal.')}</p>
    </header>
    {error && <p className="form-notice">{error}</p>}
    {message && <p className="form-notice">{message}</p>}
    <TrustSummary summary={profile?.trust_summary} />

    <div className="profile-layout">
      <form className="profile-card" onSubmit={saveProfile}>
        <div className="profile-card-title"><h2>{t('profile.professional_details', 'Your professional details')}</h2><span>{t('profile.complete_percent', `${profile?.profile_completeness || 0}% complete`, { percent: profile?.profile_completeness || 0 })}</span></div>
        <div className="photo-upload">
          <span>{profile?.profile_photo_url ? <img src={profile.profile_photo_url} alt={t('profile.photo_alt', 'Your profile')} /> : user.name?.slice(0, 1)}</span>
          <div><b>{t('profile.photo', 'Profile photo')}</b><small>{t('profile.photo_hint', 'JPG, PNG, or WebP · max 5 MB')}</small><label className="file-upload">{t('profile.change_photo', 'Change photo')}<input type="file" accept="image/jpeg,image/png,image/webp" onChange={uploadPhoto} /></label></div>
        </div>
        <label>{t('profile.title', 'Professional title')}<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('profile.title_hint', 'Enter your professional title')} /></label>
        <fieldset className="profile-experience"><legend>{t('profile.experience_level', 'Experience level')} <small>{t('profile.experience_hint', 'Optional · helps clients understand your level')}</small></legend><div>{[
          ['entry', t('profile.experience_entry', 'Entry'), t('profile.experience_entry_detail', 'Building professional experience')],
          ['intermediate', t('profile.experience_intermediate', 'Intermediate'), t('profile.experience_intermediate_detail', 'Comfortable delivering independently')],
          ['expert', t('profile.experience_expert', 'Expert'), t('profile.experience_expert_detail', 'Deep specialist experience')],
        ].map(([value, label, detail]) => <label key={value} className={form.experience_level === value ? 'selected' : ''}><input type="radio" name="experience_level" value={value} checked={form.experience_level === value} onChange={(event) => setForm({ ...form, experience_level: event.target.value })} /><span><b>{label}</b><small>{detail}</small></span></label>)}</div></fieldset>
        <label>{t('profile.about', 'About you')}<textarea value={form.bio} onChange={(event) => setForm({ ...form, bio: event.target.value })} placeholder={t('profile.about_hint', 'Briefly describe your strengths, experience, and the work you enjoy.')} /></label>
        <div className="profile-fields">
          <label>{t('profile.hourly_rate', 'Hourly rate (MMK)')}<input type="number" min="0" value={form.hourly_rate} onChange={(event) => setForm({ ...form, hourly_rate: event.target.value })} /></label>
          <label>{t('profile.location', 'Location')}<input value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} placeholder={t('profile.location_hint', 'Yangon, Myanmar')} /></label>
        </div>
        <label>{t('profile.skills', 'Skills')} <small>{t('dashboard.separate_skills', 'Separate skills with commas')}</small><input value={form.skills} onChange={(event) => setForm({ ...form, skills: event.target.value })} placeholder="Laravel, React, MySQL" /></label>
        <label className="availability"><input type="checkbox" checked={form.availability} onChange={(event) => setForm({ ...form, availability: event.target.checked })} /> {t('profile.available', 'Available for new work')}</label>
        <section className="profile-cv-card" aria-labelledby="profile-cv-heading"><div className="profile-cv-heading"><div><h3 id="profile-cv-heading">CV</h3><p>{t('profile.cv_private', 'Your CV is private until you choose to attach it to a proposal.')}</p></div><span>{t('profile.cv_hint', 'PDF · max 10 MB')}</span></div>{profile?.freelancer_resume ? <div className="profile-cv-file"><b>PDF</b><span>{profile.freelancer_resume.original_name}</span></div> : <p className="empty-profile">{t('profile.cv_empty_optional', 'No CV uploaded yet. You can still submit proposals without one.')}</p>}<div className="profile-cv-actions"><label className="file-upload">{resumeBusy ? t('profile.cv_uploading', 'Uploading...') : profile?.freelancer_resume ? t('profile.replace_cv', 'Replace PDF') : t('profile.upload_cv', 'Upload PDF')}<input disabled={resumeBusy} type="file" accept="application/pdf" onChange={uploadResume} /></label>{profile?.freelancer_resume && <button type="button" disabled={resumeBusy} onClick={removeResume}>{t('profile.remove_cv', 'Remove CV')}</button>}</div></section>
        <button disabled={busy} className="button button-primary">{busy ? t('common.saving', 'Saving...') : t('profile.save', 'Save profile')}</button>
      </form>

      <aside className="profile-side">
        <ProfileReadinessCard profile={profile} />
        <section className="profile-card profile-preview-card"><h2>{t('profile.public_profile', 'Public profile')}</h2><p className="side-copy">{t('profile.public_profile_detail', 'Preview the professional details clients can see before inviting you to apply.')}</p><Link className="button button-outline" to={`/search/freelancers/${user.id}`}>{t('profile.view_public_profile', 'View public profile')}</Link></section>
      </aside>
    </div>

    <section className="portfolio-section">
      <div className="portfolio-section-title"><div><p className="eyebrow">{t('profile.portfolio', 'Portfolio')}</p><h2>{t('profile.work_samples', 'Work samples')}</h2></div><span>{t('profile.portfolio_hint', 'Select up to three when you send a proposal.')}</span></div>
      <div className="portfolio-grid">
        {profile?.portfolio_items?.map((item) => <article className="portfolio-item" key={item.id}>
          <div className="portfolio-art">{item.title.slice(0, 1)}</div>
          <div><h3>{item.title}</h3><p>{item.description || t('profile.no_description', 'No description added yet.')}</p>{item.project_url && <a href={item.project_url} target="_blank" rel="noreferrer">{t('profile.view_project', 'View project →')}</a>}</div>
          <div className="portfolio-item-actions"><button type="button" onClick={() => editPortfolioItem(item)}>{t('common.edit', 'Edit')}</button><button type="button" onClick={() => removePortfolioItem(item.id)}>{t('common.remove', 'Remove')}</button></div>
        </article>)}
      </div>
      <form className="portfolio-add" onSubmit={addPortfolioItem}>
        <h3>{editingPortfolioId ? t('profile.edit_sample', 'Edit work sample') : t('profile.add_sample', 'Add a work sample')}</h3>
        <div><input required value={portfolioForm.title} onChange={(event) => setPortfolioForm({ ...portfolioForm, title: event.target.value })} placeholder={t('profile.project_title', 'Project title')} /><input value={portfolioForm.project_url} onChange={(event) => setPortfolioForm({ ...portfolioForm, project_url: event.target.value })} placeholder={t('profile.project_link', 'Project link (optional)')} /></div>
        <textarea value={portfolioForm.description} onChange={(event) => setPortfolioForm({ ...portfolioForm, description: event.target.value })} placeholder={t('profile.contribution_hint', 'What did you contribute and what was the result?')} />
        <div className="portfolio-form-actions"><button disabled={portfolioBusy} className="button button-outline">{portfolioBusy ? t('common.saving', 'Saving...') : editingPortfolioId ? t('profile.save_sample', 'Save work sample') : t('profile.add_sample_action', '+ Add work sample')}</button>{editingPortfolioId && <button type="button" className="button button-plain" onClick={cancelPortfolioEdit}>{t('common.cancel', 'Cancel')}</button>}</div>
      </form>
    </section>
  </section>
}
