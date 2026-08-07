import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import MarketplaceSaveButton from '../components/MarketplaceSaveButton'
import MarketplaceReportButton from '../components/MarketplaceReportButton'
import '../public-profile.css'

export default function FreelancerPublicProfile() {
  const { id } = useParams()
  const { pathname } = useLocation()
  const { user, errorMessage } = useAuth()
  const [profile, setProfile] = useState(null)
  const [jobs, setJobs] = useState([])
  const [error, setError] = useState('')
  const [inviteOpen, setInviteOpen] = useState(false)
  const [invite, setInvite] = useState({ job_id: '', message: '' })
  const [inviteError, setInviteError] = useState('')
  const [inviteNotice, setInviteNotice] = useState('')
  const [inviting, setInviting] = useState(false)
  const canInvite = Boolean(user?.roles?.includes('client') && Number(user.id) !== Number(id))

  useEffect(() => {
    api.get(`/freelancers/${id}`).then(({ data }) => setProfile(data.data)).catch(() => setError('This freelancer profile is unavailable.'))
  }, [id])

  useEffect(() => {
    if (!canInvite) return undefined
    let active = true
    api.get('/jobs/mine').then(({ data }) => {
      if (!active) return
      const openJobs = data.data.filter((job) => job.status === 'open')
      setJobs(openJobs)
      if (openJobs.length === 1) setInvite((current) => ({ ...current, job_id: String(openJobs[0].id) }))
    }).catch(() => {})
    return () => { active = false }
  }, [canInvite])

  const sendInvite = async (event) => {
    event.preventDefault()
    setInviting(true)
    setInviteError('')
    setInviteNotice('')
    try {
      await api.post(`/jobs/${invite.job_id}/invites`, { freelancer_id: Number(id), message: invite.message.trim() || null })
      setInviteNotice('Invitation sent. The freelancer can accept it and decide whether to submit a proposal.')
      setInviteOpen(false)
      setInvite({ job_id: invite.job_id, message: '' })
    } catch (requestError) {
      setInviteError(errorMessage(requestError))
    } finally {
      setInviting(false)
    }
  }

  const searchPath = pathname.startsWith('/search/') ? '/search' : '/jobs'
  if (error) return <section className="simple-page"><h1>Profile unavailable</h1><p>{error}</p><Link className="button button-primary" to={searchPath}>Browse marketplace</Link></section>
  if (!profile) return <section className="simple-page"><p>Loading freelancer profile...</p></section>

  const freelancer = profile.freelancer_profile || {}
  return <section className="public-profile">
    <div className="marketplace-detail-actions"><Link to={searchPath}>Back to search</Link><div>{freelancer.id && <MarketplaceSaveButton kind="talent" targetId={freelancer.id} />} {freelancer.id && <MarketplaceReportButton targetType="freelancer" targetId={freelancer.id} />}</div></div>
    <header><span className="public-avatar">{profile.profile_photo_url ? <img src={profile.profile_photo_url} alt="" /> : profile.name?.slice(0, 1)}</span><div><p className="eyebrow">Available freelancer</p><h1>{profile.name}</h1><h2>{freelancer.title || 'Freelancer'}</h2><p>{freelancer.location || 'Myanmar'} | {freelancer.hourly_rate ? `Ks ${Number(freelancer.hourly_rate).toLocaleString()} / hr` : 'Rate shared in proposal'}</p></div></header>
    <section className="public-profile-grid"><article><h2>About</h2><p>{freelancer.bio || 'This freelancer has not added an introduction yet.'}</p><div className="public-skills">{freelancer.skills?.map((skill) => <span key={skill}>{skill}</span>)}</div></article><aside><b>{profile.trust_summary?.average_rating ? `★ ${profile.trust_summary.average_rating}` : 'New'}</b><small>{profile.trust_summary?.review_count || 0} visible reviews</small><b>{profile.trust_summary?.completed_projects_count || 0}</b><small>completed projects</small><b>{profile.reliability?.tier_label || 'New'}</b><small>TalentXpanse reliability</small></aside></section>
    {canInvite && <section className="profile-invite"><div><p className="eyebrow">Client workspace</p><h2>Invite this freelancer to apply</h2><p>An invitation starts a professional conversation. The freelancer chooses whether to accept and submit a proposal.</p></div>{jobs.length ? <button className="button button-primary" onClick={() => setInviteOpen((value) => !value)}>{inviteOpen ? 'Close invitation' : 'Invite to apply'}</button> : <Link className="button button-outline" to="/dashboard?role=client&postJob=1">Post an open job first</Link>}</section>}
    {canInvite && inviteOpen && <form className="profile-invite-form" onSubmit={sendInvite}><label>Job<select required value={invite.job_id} onChange={(event) => setInvite({ ...invite, job_id: event.target.value })}><option value="">Choose an open job</option>{jobs.map((job) => <option key={job.id} value={job.id}>{job.title}</option>)}</select></label><label>Personal message <small>Optional</small><textarea maxLength="2000" value={invite.message} onChange={(event) => setInvite({ ...invite, message: event.target.value })} placeholder="Briefly explain why this opportunity could be a good fit." /></label>{inviteError && <p className="form-notice" role="alert">{inviteError}</p>}<button disabled={inviting || !invite.job_id} className="button button-primary">{inviting ? 'Sending invitation...' : 'Send invitation'}</button></form>}
    {inviteNotice && <p className="form-notice" role="status">{inviteNotice}</p>}
    {profile.portfolio_items?.length > 0 && <section className="public-portfolio"><h2>Portfolio</h2><div>{profile.portfolio_items.map((item) => <article key={item.id}><b>{item.title}</b><p>{item.description}</p>{item.project_url && <a href={item.project_url} target="_blank" rel="noreferrer">View project</a>}</article>)}</div></section>}
  </section>
}
