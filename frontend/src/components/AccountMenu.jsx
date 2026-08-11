import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import { usePreferences } from '../contexts/PreferencesContext'
import '../account-menu.css'

export default function AccountMenu({ placement = 'bottom', compact = false }) {
  const { user, logout } = useAuth()
  const { t } = usePreferences()
  const navigate = useNavigate()
  const confirm = useConfirmation()
  const menu = useRef(null)
  const [open, setOpen] = useState(false)
  const initials = user?.name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'
  const activeRole = user?.active_role || user?.roles?.[0]
  const roleLabel = activeRole === 'client' ? t('common.client', 'Client') : t('common.freelancer', 'Freelancer')

  useEffect(() => {
    const close = (event) => { if (!menu.current?.contains(event.target)) setOpen(false) }
    const closeOnEscape = (event) => { if (event.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', closeOnEscape)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [])

  const go = (path) => {
    setOpen(false)
    navigate(path)
  }

  const signOut = async () => {
    if (!await confirm({ title: t('menu.logout_title', 'Log out of TalentXpanse?'), message: t('menu.logout_message', 'You will need to sign in again to access your workspace on this device.'), confirmLabel: t('nav.logout', 'Log out') })) return
    await logout()
    navigate('/')
  }

  return <div className={`account-menu ${placement === 'top' ? 'account-menu-top' : ''} ${compact ? 'account-menu-compact' : ''}`} ref={menu}>
    <button className="account-trigger" aria-label={t('menu.open_account', `Open account menu for ${user?.name || 'your account'}`, { name: user?.name || t('menu.your_account', 'your account') })} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
      <span className="sidebar-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
      <em>{user?.name}</em>
      <i aria-hidden="true">⌄</i>
    </button>
    {open && <section className="account-popover" role="menu">
      {!compact && <header>
        <span>{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
        <div><b>{user?.name}</b><small>{user?.email}</small><em>{roleLabel}</em></div>
      </header>}
      <div className="account-links">
        <button onClick={() => go('/search?scope=saved')}>{t('menu.saved_items', 'Saved items')}</button>
        <button onClick={() => go('/settings')}>{t('nav.settings', 'Settings')}</button>
      </div>
      <button className="account-logout" onClick={signOut}>{t('nav.logout', 'Log out')}</button>
    </section>}
  </div>
}
