import { Link, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useState } from 'react'
import { usePreferences } from '../contexts/PreferencesContext'
import { useAuth } from '../contexts/AuthContext'

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

function PublicHeader() {
  const { user, logout } = useAuth()
  return <header className="site-header"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><nav>{navLinks.map(([to, label]) => <NavLink key={to} to={to}>{label}</NavLink>)}</nav><div className="header-actions"><LanguageToggle /><ThemeToggle />{user ? <><Link to={`/dashboard?role=${user.roles[0]}`} className="text-link">{user.name}</Link><button className="text-link" onClick={logout}>Log out</button></> : <><Link to="/login" className="text-link">Log in</Link><Link to="/register" className="button button-primary button-small">Sign up</Link></>}</div></header>
}

function DashboardShell() {
  const [collapsed, setCollapsed] = useState(false)
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const { user, addRole } = useAuth()
  const requestedRole = params.get('role')
  const role = user?.roles?.includes(requestedRole) ? requestedRole : (user?.roles?.[0] || 'freelancer')
  const links = role === 'client' ? clientLinks : freelancerLinks
  const changeRole = async () => {
    const nextRole = role === 'client' ? 'freelancer' : 'client'
    if (!user?.roles?.includes(nextRole)) await addRole(nextRole)
    setParams({ role: nextRole })
  }

  return <div className={`dashboard-shell ${collapsed ? 'sidebar-collapsed' : ''}`}>
    <aside className="dashboard-sidebar">
      <div className="sidebar-top"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><button className="sidebar-collapse" onClick={() => setCollapsed(value => !value)} aria-label="Collapse sidebar">☰</button></div>
      <button className="role-switch" onClick={changeRole}><span>{role === 'client' ? '⌘' : '✦'}</span><em>{role === 'client' ? 'Client workspace' : 'Freelancer workspace'}</em><b>⌄</b></button>
      <nav className="dashboard-nav">{links.map(([icon, label], index) => <button key={label} className={index === 0 ? 'active' : ''} onClick={() => index === 0 ? navigate(`/dashboard?role=${role}`) : null}><span>{icon}</span><em>{label}</em></button>)}</nav>
      <div className="sidebar-bottom"><LanguageToggle /><ThemeToggle /></div>
    </aside>
    <main className="dashboard-main"><Outlet context={{ role }} /></main>
  </div>
}

export default function AppLayout() {
  const { pathname } = useLocation()
  if (pathname === '/dashboard') return <DashboardShell />
  return <div className="app-shell"><PublicHeader /><main><Outlet /></main></div>
}
