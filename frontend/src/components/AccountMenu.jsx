import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import '../account-menu.css'

const roleLabel = (role) => role === 'client' ? 'Client' : 'Freelancer'

export default function AccountMenu({ placement = 'bottom' }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const confirm = useConfirmation()
  const menu = useRef(null)
  const [open, setOpen] = useState(false)
  const initials = user?.name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'
  const activeRole = user?.active_role || user?.roles?.[0]

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
    if (!await confirm({ title: 'Log out of TalentXpanse?', message: 'You will need to sign in again to access your workspace on this device.', confirmLabel: 'Log out' })) return
    await logout()
    navigate('/')
  }

  return <div className={`account-menu ${placement === 'top' ? 'account-menu-top' : ''}`} ref={menu}>
    <button className="account-trigger" aria-label={`Open account menu for ${user?.name || 'your account'}`} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
      <span className="sidebar-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
      <em>{user?.name}</em>
      <i aria-hidden="true">⌄</i>
    </button>
    {open && <section className="account-popover" role="menu">
      <header>
        <span>{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span>
        <div><b>{user?.name}</b><small>{user?.email}</small><em>{roleLabel(activeRole)}</em></div>
      </header>
      <div className="account-links">
        <button onClick={() => go('/search?scope=saved')}>Saved items</button>
        <button onClick={() => go('/settings')}>Settings</button>
      </div>
      <button className="account-logout" onClick={signOut}>Log out</button>
    </section>}
  </div>
}
