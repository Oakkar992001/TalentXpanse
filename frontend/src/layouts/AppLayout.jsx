import { Link, Navigate, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { usePreferences } from '../contexts/PreferencesContext'
import { useAuth } from '../contexts/AuthContext'
import api from '../services/api'
import AccountMenu from '../components/AccountMenu'
import GlobalSearch from '../components/GlobalSearch'
import '../app-polish.css'

const publicLinks = [['/jobs', 'Find freelance jobs'], ['/register?role=client', 'Hire freelancers'], ['/how-it-works', 'How it works']]
const clientLinks = [['Overview', '/dashboard?role=client'], ['Messages', '/messages'], ['Projects', '/projects']]
const freelancerLinks = [['Overview', '/dashboard?role=freelancer'], ['My profile', '/profile'], ['Messages', '/messages'], ['Projects', '/projects']]

function Icon({ name }) {
  const paths = {
    overview: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>,
    messages: <path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.8 8.8 0 0 1-3.7-.8L4 20l1.2-3.4A7.4 7.4 0 0 1 4 12a7.5 7.5 0 0 1 8-7.5 7.5 7.5 0 0 1 8 7Z" />,
    projects: <><path d="M3.5 6.5h6l1.7 2H20a1.5 1.5 0 0 1 1.5 1.5v8.5A1.5 1.5 0 0 1 20 20H4a1.5 1.5 0 0 1-1.5-1.5V8A1.5 1.5 0 0 1 4 6.5Z" /><path d="M2.5 10h19" /></>,
    settings: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.2 2.2-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-3.2v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-2.2-2.2.1-.1A1.7 1.7 0 0 0 6.6 15a1.7 1.7 0 0 0-1.5-1H5v-3.2h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.2-2.2.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2h3.2v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.2 2.2-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2V14H21a1.7 1.7 0 0 0-1.6 1Z" /></>,
    search: <><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></>,
    bell: <><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M9.5 21h5" /></>,
    sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></>,
    moon: <path d="M20.5 15.4A8.5 8.5 0 0 1 8.6 3.5 8.5 8.5 0 1 0 20.5 15.4Z" />,
  }
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name] || paths.overview}</svg>
}

function ThemeToggle() { const { theme, toggleTheme } = usePreferences(); const isLight = theme === 'light'; return <button className="theme-toggle theme-icon" onClick={toggleTheme} title={`Theme: ${isLight ? 'Light' : 'Dark'}. Click to switch.`} aria-label={`Theme: ${isLight ? 'Light' : 'Dark'}. Click to switch.`}><Icon name={isLight ? 'sun' : 'moon'} /></button> }
function LanguageToggle() { const { language, toggleLanguage } = usePreferences(); const isEnglish = language === 'en'; return <button className="language-toggle preference-choice" onClick={toggleLanguage} title={`Language: ${isEnglish ? 'English' : 'Myanmar'}. Click to switch.`} aria-label={`Language: ${isEnglish ? 'English' : 'Myanmar'}. Click to switch.`}><span className={isEnglish ? 'active' : ''}>English</span><i>/</i><span className={!isEnglish ? 'active myanmar' : 'myanmar'}>မြန်မာ</span></button> }
function UnreadBadge({ endpoint }) { const { user } = useAuth(); const [count, setCount] = useState(0); useEffect(() => { if (user) api.get(endpoint).then(({ data }) => setCount(data.data.unread_count ?? data.data.unread_messages ?? 0)).catch(() => setCount(0)) }, [endpoint, user]); return count > 0 ? <b>{count}</b> : null }

function PublicHeader() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const signOut = async () => { if (!window.confirm('Log out of TalentXpanse on this device?')) return; await logout(); navigate('/') }
  return <header className="site-header"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><nav>{publicLinks.map(([to, label]) => <NavLink key={label} to={to}>{label}</NavLink>)}</nav><div className="header-actions"><LanguageToggle /><ThemeToggle />{user ? <><Link to={`/dashboard?role=${user.active_role || user.roles[0]}`} className="text-link">Open workspace</Link><button className="text-link" onClick={signOut}>Log out</button></> : <><Link to="/login" className="text-link">Log in</Link><Link to="/register" className="button button-primary button-small">Sign up</Link></>}</div></header>
}

function DashboardShell() {
  const [collapsed, setCollapsed] = useState(false)
  const [searchOpen, setSearchOpen] = useState(false)
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { user } = useAuth()
  const requestedRole = params.get('role')
  const role = user?.roles?.includes(requestedRole) ? requestedRole : (user?.active_role || user?.roles?.[0] || 'freelancer')
  const links = role === 'client' ? clientLinks : freelancerLinks
  const title = pathname === '/messages' ? 'Messages' : pathname === '/notifications' ? 'Notifications' : pathname.startsWith('/settings') ? 'Settings' : pathname.startsWith('/projects') ? 'Projects' : pathname === '/profile' ? 'My profile' : pathname.startsWith('/search/jobs/') ? 'Job details' : pathname.startsWith('/search/freelancers/') ? 'Freelancer profile' : pathname.startsWith('/search') ? 'Search marketplace' : pathname === '/jobs' ? 'Find jobs' : role === 'client' ? 'Client workspace' : 'Freelancer workspace'
  const nav = (_label, target) => navigate(target)

  return <div className={`dashboard-shell ${collapsed ? 'sidebar-collapsed' : ''}`}><aside className="dashboard-sidebar"><div className="sidebar-top"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><button className="sidebar-collapse" onClick={() => setCollapsed((value) => !value)} aria-label="Collapse sidebar">☰</button></div><nav className="dashboard-nav">{links.map(([label, target]) => <button key={label} className={pathname === target.split('?')[0] && (label !== 'Overview' || pathname === '/dashboard') ? 'active' : ''} onClick={() => nav(label, target)}><span><Icon name={label === 'Messages' ? 'messages' : label === 'Projects' ? 'projects' : label === 'My profile' ? 'settings' : 'overview'} /></span><em>{label}</em></button>)}</nav><div className="sidebar-bottom"><AccountMenu /><div className="sidebar-preferences"><div className="preference-row"><small>Language</small><LanguageToggle /></div><div className="preference-row"><small>Theme</small><ThemeToggle /></div></div></div></aside><main className="dashboard-main"><header className="dashboard-topbar"><div><h2>{title}</h2><p>{role === 'client' ? 'Manage hiring and delivery in one place.' : 'Discover work and keep projects moving.'}</p></div><div className="topbar-actions"><button className="topbar-search" onClick={() => setSearchOpen(true)}><span><Icon name="search" /></span> Search</button><Link className="topbar-icon" to="/notifications" aria-label="Notifications"><Icon name="bell" /><UnreadBadge endpoint="/notifications/summary" /></Link></div></header><Outlet context={{ role }} /></main><GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} /></div>
}

export default function AppLayout() {
  const location = useLocation()
  const { user, loading } = useAuth()
  const privatePath = ['/dashboard', '/profile', '/workspace-setup', '/messages', '/notifications'].includes(location.pathname) || location.pathname.startsWith('/search') || location.pathname.startsWith('/settings') || location.pathname.startsWith('/projects')
  if (privatePath && loading) return <main className="simple-page"><p>Loading your workspace…</p></main>
  if (privatePath && !user) return <Navigate to={`/login?next=${encodeURIComponent(`${location.pathname}${location.search}`)}`} replace />
  if (privatePath) return <DashboardShell />
  return <div className="app-shell"><PublicHeader /><main><Outlet /></main></div>
}
