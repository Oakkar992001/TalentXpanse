import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

export default function SettingsSidebar() {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)
  const links = [['/settings', 'My Information'], ['/settings/account', 'Account Settings'], ['/settings/security', 'Password & Security'], ['/settings/notifications', 'Notification Settings']]
  if (user?.roles?.includes('freelancer')) links.push(['/settings/credits', 'Membership & Credits'])
  const active = links.find(([to]) => pathname === to)?.[1] || 'Settings'

  return <aside className={`settings-sidebar ${open ? 'open' : ''}`}><button className="settings-mobile-toggle" onClick={() => setOpen((value) => !value)} aria-expanded={open}>Settings: {active}<span>⌄</span></button><nav>{links.map(([to, label]) => <Link key={to} to={to} className={pathname === to ? 'active' : ''} onClick={() => setOpen(false)}>{label}</Link>)}</nav></aside>
}
