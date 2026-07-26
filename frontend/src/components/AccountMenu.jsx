import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import '../account-menu.css'

const roleLabel = (role) => role === 'client' ? 'Client' : 'Freelancer'

export default function AccountMenu({ placement = 'bottom' }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const menu = useRef(null)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const close = (event) => { if (!menu.current?.contains(event.target)) setOpen(false) }
    const escape = (event) => { if (event.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', close); document.addEventListener('keydown', escape)
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', escape) }
  }, [])

  const go = (path) => { setOpen(false); navigate(path) }
  const signOut = async () => { if (!window.confirm('Log out of TalentXpanse on this device?')) return; await logout(); navigate('/') }
  const initials = user?.name?.split(' ').map((part) => part[0]).slice(0, 2).join('') || 'TX'
  const activeRole = user?.active_role || user?.roles?.[0]

  return <div className={`account-menu ${placement === 'top' ? 'account-menu-top' : ''}`} ref={menu}><button className="account-trigger" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}><span className="sidebar-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span><em>{user?.name}</em><i>⌄</i></button>{open && <section className="account-popover" role="menu"><header><span>{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials}</span><div><b>{user?.name}</b><small>{user?.email}</small><em>{roleLabel(activeRole)}</em></div></header><div className="account-links"><button onClick={() => go(activeRole === 'freelancer' ? '/profile' : '/workspace-setup?role=client')}>View profile</button><button onClick={() => go('/settings')}>Settings</button></div><button className="account-logout" onClick={signOut}>Log out</button></section>}</div>
}
