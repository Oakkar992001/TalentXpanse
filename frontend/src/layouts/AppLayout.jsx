import { Link, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { usePreferences } from '../contexts/PreferencesContext'
import { useAuth } from '../contexts/AuthContext'
import api from '../services/api'

const navLinks = [['/', 'Find work'], ['/jobs', 'Hire talent'], ['/about', 'How it works']]
const clientLinks = [['◈', 'Overview'], ['▣', 'My jobs'], ['⌕', 'Find talent'], ['◌', 'Messages'], ['▤', 'Payments']]
const freelancerLinks = [['◈', 'Home'], ['⌕', 'Find work'], ['▣', 'Proposals'], ['◯', 'My profile'], ['◌', 'Messages'], ['▤', 'Earnings']]

function ThemeToggle() {
  const { theme, toggleTheme } = usePreferences()
  return <button className="theme-toggle" onClick={toggleTheme} aria-label="Toggle color theme"><span>{theme === 'dark' ? '☾' : '☀'}</span><i /></button>
}

function LanguageToggle() {
  const { language, toggleLanguage } = usePreferences()
  return <button className="language-toggle" onClick={toggleLanguage}><span>◎</span>{language === 'en' ? 'English' : 'မြန်မာ'}<b>⌄</b></button>
}

function UserAvatar({ user }) {
  const initials = user?.name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'
  return <span className="sidebar-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
}

function MessageIndicator() {
  const { user } = useAuth()
  const [unread, setUnread] = useState(0)
  useEffect(() => { if (user) api.get('/conversations/summary').then(({ data }) => setUnread(data.data.unread_messages)).catch(() => setUnread(0)) }, [user?.id])
  return <Link className="message-indicator" to="/messages">Messages {unread > 0 && <b>{unread}</b>}</Link>
}

function NotificationIndicator() {
  const { user } = useAuth()
  const [unread, setUnread] = useState(0)
  useEffect(() => { if (user) api.get('/notifications/summary').then(({ data }) => setUnread(data.data.unread_count)).catch(() => setUnread(0)) }, [user?.id])
  return <Link className="message-indicator" to="/notifications">Updates {unread > 0 && <b>{unread}</b>}</Link>
}

function PublicHeader() {
  const { user, logout } = useAuth()
  return <header className="site-header"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><nav>{navLinks.map(([to, label]) => <NavLink key={to} to={to}>{label}</NavLink>)}</nav><div className="header-actions"><LanguageToggle /><ThemeToggle />{user ? <><Link to={`/dashboard?role=${user.roles[0]}`} className="text-link">{user.name}</Link><button className="text-link" onClick={logout}>Log out</button></> : <><Link to="/login" className="text-link">Log in</Link><Link to="/register" className="button button-primary button-small">Sign up</Link></>}</div></header>
}

function DashboardShell() {
  const [collapsed, setCollapsed] = useState(false)
  const [workspaceOpen, setWorkspaceOpen] = useState(false)
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { user } = useAuth()
  const requestedRole = params.get('role')
  const role = user?.roles?.includes(requestedRole) ? requestedRole : (user?.roles?.[0] || 'freelancer')
  const links = role === 'client' ? clientLinks : freelancerLinks
  const selectWorkspace = (nextRole) => {
    setWorkspaceOpen(false)
    if (user?.roles?.includes(nextRole)) setParams({ role: nextRole })
    else navigate(`/workspace-setup?role=${nextRole}`)
  }
  const changeRole = () => selectWorkspace(role === 'client' ? 'freelancer' : 'client')
  const navigateLink = (label, index) => {
    if (index === 0 || label === 'My jobs' || label === 'Proposals') navigate(`/dashboard?role=${role}`)
    if (label === 'My profile') navigate('/profile')
    if (label === 'Find work') navigate('/jobs')
    if (label === 'Messages') navigate('/messages')
  }

  return <div className={`dashboard-shell ${collapsed ? 'sidebar-collapsed' : ''}`}>
    <aside className="dashboard-sidebar">
      <div className="sidebar-top"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><button className="sidebar-collapse" onClick={() => setCollapsed(value => !value)} aria-label="Collapse sidebar">☰</button></div>
      <button className="role-switch" onClick={changeRole}><span>{role === 'client' ? '⌘' : '✦'}</span><em>{role === 'client' ? 'Client workspace' : 'Freelancer workspace'}</em><b>⌄</b></button>
      <nav className="dashboard-nav">{links.map(([icon, label], index) => <button key={label} className={index === 0 || (label === 'My profile' && pathname === '/profile') ? 'active' : ''} onClick={() => navigateLink(label, index)}><span>{icon}</span><em>{label}</em></button>)}</nav>
      <div className="workspace-selector"><button className="workspace-trigger" onClick={() => setWorkspaceOpen((value) => !value)}><span>{role === 'client' ? 'C' : 'F'}</span><div><b>{role === 'client' ? 'Client' : 'Freelancer'}</b><small>Workspace</small></div><i>⌄</i></button>{workspaceOpen && <div className="workspace-menu"><button className={role === 'freelancer' ? 'selected' : ''} onClick={() => selectWorkspace('freelancer')}><b>Freelancer</b><small>{user?.roles?.includes('freelancer') ? 'Work, proposals, and credits' : 'Add freelancer workspace'}</small></button><button className={role === 'client' ? 'selected' : ''} onClick={() => selectWorkspace('client')}><b>Client</b><small>{user?.roles?.includes('client') ? 'Jobs and hiring' : 'Add client workspace'}</small></button></div>}</div>
      <div className="sidebar-bottom"><Link className="message-indicator" to="/projects">Projects</Link><MessageIndicator /><NotificationIndicator /><Link className="sidebar-user" to={role === 'client' ? '/workspace-setup?role=client' : '/profile'}><UserAvatar user={user} /><em>{user?.name}</em></Link><LanguageToggle /><ThemeToggle /></div>
    </aside>
    <main className="dashboard-main"><Outlet context={{ role }} /></main>
  </div>
}

export default function AppLayout() {
  const { pathname } = useLocation()
  if (pathname === '/dashboard' || pathname === '/profile' || pathname === '/workspace-setup' || pathname === '/messages' || pathname === '/notifications' || pathname.startsWith('/projects')) return <DashboardShell />
  return <div className="app-shell"><PublicHeader /><main><Outlet /></main></div>
}
