import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import api from '../services/api'
import MarketplaceSaveButton from '../components/MarketplaceSaveButton'
import MarketplaceReportButton from '../components/MarketplaceReportButton'
import '../public-profile.css'

export default function FreelancerPublicProfile() {
  const { id } = useParams()
  const { pathname } = useLocation()
  const [profile, setProfile] = useState(null)
  const [error, setError] = useState('')
  useEffect(() => { api.get(`/freelancers/${id}`).then(({ data }) => setProfile(data.data)).catch(() => setError('This freelancer profile is unavailable.')) }, [id])
  const searchPath = pathname.startsWith('/search/') ? '/search' : '/jobs'
  if (error) return <section className="simple-page"><h1>Profile unavailable</h1><p>{error}</p><Link className="button button-primary" to={searchPath}>Browse marketplace</Link></section>
  if (!profile) return <section className="simple-page"><p>Loading freelancer profile…</p></section>
  const freelancer = profile.freelancer_profile || {}
  return <section className="public-profile"><div className="marketplace-detail-actions"><Link to={searchPath}>← Back to search</Link><div>{freelancer.id && <MarketplaceSaveButton kind="talent" targetId={freelancer.id} />} {freelancer.id && <MarketplaceReportButton targetType="freelancer" targetId={freelancer.id} />}</div></div><header><span className="public-avatar">{profile.profile_photo_url ? <img src={profile.profile_photo_url} alt="" /> : profile.name?.slice(0, 1)}</span><div><p className="eyebrow">Available freelancer</p><h1>{profile.name}</h1><h2>{freelancer.title || 'Freelancer'}</h2><p>{freelancer.location || 'Myanmar'} · {freelancer.hourly_rate ? `Ks ${Number(freelancer.hourly_rate).toLocaleString()} / hr` : 'Rate shared in proposal'}</p></div></header><section className="public-profile-grid"><article><h2>About</h2><p>{freelancer.bio || 'This freelancer has not added an introduction yet.'}</p><div className="public-skills">{freelancer.skills?.map((skill) => <span key={skill}>{skill}</span>)}</div></article><aside><b>{profile.trust_summary?.average_rating ? `★ ${profile.trust_summary.average_rating}` : 'New'}</b><small>{profile.trust_summary?.review_count || 0} visible reviews</small><b>{profile.trust_summary?.completed_projects_count || 0}</b><small>completed projects</small></aside></section>{profile.portfolio_items?.length > 0 && <section className="public-portfolio"><h2>Portfolio</h2><div>{profile.portfolio_items.map((item) => <article key={item.id}><b>{item.title}</b><p>{item.description}</p>{item.project_url && <a href={item.project_url} target="_blank" rel="noreferrer">View project →</a>}</article>)}</div></section>}</section>
}
