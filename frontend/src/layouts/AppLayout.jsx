import { Link, Navigate, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useState } from 'react'
import { usePreferences } from '../contexts/PreferencesContext'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import AccountMenu from '../components/AccountMenu'
import GlobalSearch from '../components/GlobalSearch'
import NotificationMenu from '../components/NotificationMenu'
import BetaFeedbackButton from '../components/BetaFeedbackButton'
import '../app-polish.css'

const publicLinks = [['/jobs', 'nav.explore_marketplace', 'Explore marketplace'], ['/how-it-works', 'nav.how_it_works', 'How it works'], ['/help', 'nav.help', 'Help'], ['/about', 'nav.why_talentxpanse', 'Why TalentXpanse']]
const clientLinks = [['Homepage', '/dashboard?role=client', 'nav.homepage'], ['Jobs', '/work?role=client', 'nav.jobs'], ['My profile', '/workspace-setup?role=client', 'nav.profile'], ['Messages', '/messages', 'nav.messages'], ['Projects', '/projects', 'nav.projects']]
const freelancerLinks = [['Homepage', '/dashboard?role=freelancer', 'nav.homepage'], ['Jobs', '/search?scope=jobs', 'nav.jobs'], ['My profile', '/profile', 'nav.profile'], ['Messages', '/messages', 'nav.messages'], ['Projects', '/projects', 'nav.projects']]

function Icon({ name }) {
  const paths = {
    overview: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>,
    messages: <path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.8 8.8 0 0 1-3.7-.8L4 20l1.2-3.4A7.4 7.4 0 0 1 4 12a7.5 7.5 0 0 1 8-7.5 7.5 7.5 0 0 1 8 7Z" />,
    projects: <><path d="M3.5 6.5h6l1.7 2H20a1.5 1.5 0 0 1 1.5 1.5v8.5A1.5 1.5 0 0 1 20 20H4a1.5 1.5 0 0 1-1.5-1.5V8A1.5 1.5 0 0 1 4 6.5Z" /><path d="M2.5 10h19" /></>,
    jobs: <><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2" /></>,
    settings: <><circle cx="12" cy="8" r="3.5" /><path d="M4.5 21a7.5 7.5 0 0 1 15 0" /></>,
    search: <><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></>,
    sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></>,
    moon: <path d="M20.5 15.4A8.5 8.5 0 0 1 8.6 3.5 8.5 8.5 0 1 0 20.5 15.4Z" />,
  }
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name] || paths.overview}</svg>
}

function ThemeToggle() {
  const { theme, t, toggleTheme } = usePreferences()
  const isLight = theme === 'light'
  const label = `${t('theme.light', 'Light')} / ${t('theme.dark', 'Dark')}`
  return <button className="theme-toggle theme-icon" onClick={toggleTheme} title={`${label}. Click to switch.`} aria-label={`${label}. Click to switch.`}><Icon name={isLight ? 'sun' : 'moon'} /></button>
}

function LanguageToggle({ compact = false }) {
  const { language, t, toggleLanguage } = usePreferences()
  const isEnglish = language === 'en'
  const activeLanguage = isEnglish ? t('common.english', 'English') : t('common.myanmar', 'Myanmar')
  return <button className={`language-toggle preference-choice ${compact ? 'preference-choice-compact' : ''}`} onClick={toggleLanguage} title={`${t('language.label', 'Language')}: ${activeLanguage}.`} aria-label={`${t('language.label', 'Language')}: ${activeLanguage}.`}>{compact ? <span className="active">{isEnglish ? 'EN' : 'မြန်'}</span> : <><span className={isEnglish ? 'active' : ''}>{t('common.english', 'English')}</span><i>/</i><span className={!isEnglish ? 'active myanmar' : 'myanmar'}>{t('common.myanmar', 'Myanmar')}</span></>}</button>
}

function PublicHeader() {
  const { user, logout } = useAuth()
  const { t } = usePreferences()
  const navigate = useNavigate()
  const confirm = useConfirmation()
  const signOut = async () => {
    if (!await confirm({ title: 'Log out of TalentXpanse?', message: 'You will need to sign in again to access your workspace on this device.', confirmLabel: 'Log out' })) return
    await logout()
    navigate('/')
  }
  return <header className="site-header"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><nav>{publicLinks.map(([to, key, label]) => <NavLink key={key} to={to}>{t(key, label)}</NavLink>)}</nav><div className="header-actions"><LanguageToggle /><ThemeToggle />{user ? <><Link to={`/dashboard?role=${user.active_role || user.roles[0]}`} className="text-link">{t('nav.open_workspace', 'Open workspace')}</Link><button className="text-link" onClick={signOut}>{t('nav.logout', 'Log out')}</button></> : <><Link to="/login" className="text-link">{t('nav.login', 'Log in')}</Link><Link to="/register" className="button button-primary button-small">{t('nav.signup', 'Sign up')}</Link></>}</div></header>
}

function DashboardShell() {
  const [collapsed, setCollapsed] = useState(false)
  const [searchOpen, setSearchOpen] = useState(false)
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { user } = useAuth()
  const { t } = usePreferences()
  const requestedRole = params.get('role')
  const role = user?.roles?.includes(requestedRole) ? requestedRole : (user?.active_role || user?.roles?.[0] || 'freelancer')
  const links = role === 'client' ? clientLinks : freelancerLinks
  const defaultTitle = role === 'client' ? t('workspace.client', 'Client workspace') : t('workspace.freelancer', 'Freelancer workspace')
  const title = pathname === '/messages' ? t('nav.messages', 'Messages') : pathname === '/notifications' ? t('nav.notifications', 'Notifications') : pathname.startsWith('/settings') ? t('nav.settings', 'Settings') : pathname.startsWith('/projects') ? t('nav.projects', 'Projects') : pathname.startsWith('/manage') ? t('nav.proposal_manager', 'Proposal manager') : pathname === '/work' ? t('nav.my_work', 'My work') : pathname === '/profile' ? t('nav.profile', 'My profile') : pathname.startsWith('/search/jobs/') ? t('nav.job_details', 'Job details') : pathname.startsWith('/search/freelancers/') ? t('nav.freelancer_profile', 'Freelancer profile') : pathname.startsWith('/search') ? t('nav.marketplace_search', 'Search marketplace') : pathname === '/jobs' ? t('nav.find_jobs', 'Find jobs') : defaultTitle
  const subtitle = role === 'client' ? t('workspace.client_subtitle', 'Manage hiring and delivery in one place.') : t('workspace.freelancer_subtitle', 'Discover work and keep projects moving.')
  const nav = (target) => navigate(target)

  return <div className={`dashboard-shell ${collapsed ? 'sidebar-collapsed' : ''}`}>
    <aside className="dashboard-sidebar">
      <div className="sidebar-top"><Link className="brand" to="/">Talent<span>Xpanse</span></Link><button className="sidebar-collapse" onClick={() => setCollapsed((value) => !value)} aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>☰</button></div>
      <nav className="dashboard-nav" aria-label="Workspace navigation">{links.map(([label, target, key]) => {
        const translatedLabel = t(key, label)
        return <button key={label} aria-label={translatedLabel} className={pathname === target.split('?')[0] && (label !== 'Homepage' || pathname === '/dashboard') ? 'active' : ''} onClick={() => nav(target)}><span><Icon name={label === 'Messages' ? 'messages' : label === 'Projects' ? 'projects' : label === 'Jobs' ? 'jobs' : label === 'My profile' ? 'settings' : 'overview'} /></span><em>{translatedLabel}</em></button>
      })}</nav>
      <div className="sidebar-bottom"><AccountMenu compact={!collapsed} /><div className="sidebar-preferences"><div className="preference-row"><small>{t('language.label', 'Language')}</small><LanguageToggle compact={collapsed} /></div><div className="preference-row"><small>{t('theme.label', 'Theme')}</small><ThemeToggle /></div></div></div>
    </aside>
    <a className="skip-link" href="#workspace-content">Skip to workspace content</a><main id="workspace-content" className="dashboard-main" tabIndex="-1">
      <header className="dashboard-topbar"><div><h2>{title}</h2><p>{subtitle}</p></div><div className="topbar-actions"><button className="topbar-search" onClick={() => setSearchOpen(true)}><span><Icon name="search" /></span> {t('nav.search', 'Search')}</button><NotificationMenu /><BetaFeedbackButton /><div className="mobile-preferences"><LanguageToggle /><ThemeToggle /></div><div className="mobile-account"><AccountMenu placement="top" /></div></div></header>
      <Outlet context={{ role }} />
    </main>
    <GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} />
  </div>
}

export default function AppLayout() {
  const location = useLocation()
  const { user, loading } = useAuth()
  const privatePath = ['/dashboard', '/profile', '/workspace-setup', '/messages', '/notifications', '/work'].includes(location.pathname) || location.pathname.startsWith('/search') || location.pathname.startsWith('/settings') || location.pathname.startsWith('/projects') || location.pathname.startsWith('/manage')
  if (privatePath && loading) return <main className="simple-page"><p>Loading your workspace...</p></main>
  if (privatePath && !user) return <Navigate to={`/login?next=${encodeURIComponent(`${location.pathname}${location.search}`)}`} replace />
  if (privatePath) return <DashboardShell />
  return <div className="app-shell"><a className="skip-link" href="#main-content">Skip to main content</a><PublicHeader /><main id="main-content" tabIndex="-1"><Outlet /></main></div>
}
