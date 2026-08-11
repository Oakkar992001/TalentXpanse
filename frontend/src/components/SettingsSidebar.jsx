import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { usePreferences } from '../contexts/PreferencesContext'

export default function SettingsSidebar() {
  const { user } = useAuth()
  const { t } = usePreferences()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)
  const links = [
    ['/settings', t('settings.my_information', 'My Information')],
    ['/settings/account', t('settings.account', 'Account Settings')],
    ['/settings/security', t('settings.security', 'Password & Security')],
    ['/settings/verification', t('settings.verification', 'Identity & Verification')],
    ['/settings/notifications', t('settings.notifications', 'Notification Settings')],
  ]

  if (user?.roles?.includes('freelancer')) links.push(['/settings/credits', t('settings.credits', 'Membership & Credits')])
  if (user?.roles?.some((role) => ['client', 'freelancer'].includes(role))) links.push(['/settings/reliability', t('settings.reliability', 'Marketplace Reliability')])

  const active = links.find(([to]) => pathname === to)?.[1] || t('settings.title', 'Settings')

  return <aside className={`settings-sidebar ${open ? 'open' : ''}`}>
    <button className="settings-mobile-toggle" onClick={() => setOpen((value) => !value)} aria-expanded={open} aria-controls="settings-navigation">{t('settings.title', 'Settings')}: {active}<span aria-hidden="true">⌄</span></button>
    <nav id="settings-navigation">{links.map(([to, label]) => <Link key={to} to={to} className={pathname === to ? 'active' : ''} onClick={() => setOpen(false)}>{label}</Link>)}</nav>
  </aside>
}
