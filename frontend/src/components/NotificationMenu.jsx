import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useNotifications } from '../hooks/useNotifications'
import { usePreferences } from '../contexts/PreferencesContext'
import '../notification-menu.css'

const iconFor = (type) => {
  if (type.includes('message')) return '✉'
  if (type.includes('milestone')) return '✓'
  if (type.includes('review')) return '★'
  return '●'
}

export default function NotificationMenu() {
  const { notifications, unreadCount, loading, markRead } = useNotifications()
  const { t } = usePreferences()
  const navigate = useNavigate()
  const menu = useRef(null)
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const closeOnOutsideClick = (event) => { if (!menu.current?.contains(event.target)) setOpen(false) }
    const closeOnEscape = (event) => { if (event.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', closeOnOutsideClick)
    document.addEventListener('keydown', closeOnEscape)
    return () => {
      document.removeEventListener('mousedown', closeOnOutsideClick)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [])

  const openNotification = (notification) => {
    setOpen(false)
    if (!notification.read_at) void markRead(notification.id).catch(() => {})
    navigate(notification.url || '/dashboard')
  }

  const viewAll = () => {
    setOpen(false)
    navigate('/notifications')
  }

  return <div className="notification-menu" ref={menu}>
    <button type="button" className="topbar-icon notification-menu-trigger" aria-label={t('notifications.title', 'Notifications')} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
      <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M9.5 21h5" /></svg>
      {unreadCount > 0 && <b>{unreadCount > 99 ? '99+' : unreadCount}</b>}
    </button>
    {open && <section className="notification-popover" role="menu" aria-label={t('notifications.title', 'Notifications')}>
      <header><div><b>{t('notifications.title', 'Notifications')}</b><small>{unreadCount ? t('notifications.unread', `${unreadCount} unread`, { count: unreadCount }) : t('notifications.caught_up', 'You are all caught up')}</small></div><button type="button" onClick={viewAll}>{t('notifications.view_all', 'View all')}</button></header>
      <div className="notification-popover-list">
        {loading && !notifications.length ? <p>{t('notifications.checking', 'Checking for updates...')}</p> : notifications.slice(0, 5).map((notification) => <button type="button" className={`notification-popover-item ${notification.read_at ? 'read' : 'unread'}`} key={notification.id} onClick={() => openNotification(notification)}>
          <span>{iconFor(notification.type)}</span><div><b>{notification.title}</b>{notification.body && <small>{notification.body}</small>}</div>{!notification.read_at && <i />}
        </button>)}
        {!loading && !notifications.length && <p>{t('notifications.empty', 'You have no notifications yet.')}</p>}
      </div>
    </section>}
  </div>
}
