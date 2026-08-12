import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useNotifications } from '../hooks/useNotifications'
import { usePreferences } from '../contexts/PreferencesContext'
import { useConfirmation } from '../contexts/ConfirmContext'
import '../notification-menu.css'

const iconFor = (type) => {
  if (type.includes('message')) return '✉'
  if (type.includes('milestone')) return '✓'
  if (type.includes('review')) return '★'
  return '●'
}

function MarkAllReadIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="m4 7 2.2 2.2L10 5.5" /><path d="m4 14 2.2 2.2L10 12.5" /><path d="M13 7h7M13 14h7" /></svg>
}

function ClearAllIcon() {
  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h16M10 11v6M14 11v6M9 7l1-3h4l1 3M6 7l1 13h10l1-13" /></svg>
}

export default function NotificationMenu() {
  const { notifications, unreadCount, loading, markRead, markAllRead, clearAll } = useNotifications()
  const { t } = usePreferences()
  const confirm = useConfirmation()
  const navigate = useNavigate()
  const menu = useRef(null)
  const [open, setOpen] = useState(false)
  const [markingAll, setMarkingAll] = useState(false)
  const [clearingAll, setClearingAll] = useState(false)

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

  const markEverythingRead = async () => {
    setMarkingAll(true)
    try { await markAllRead() } catch {} finally { setMarkingAll(false) }
  }

  const clearEverything = async () => {
    if (!notifications.length || !await confirm({ title: 'Clear all notifications?', message: 'This removes every notification from your account. This cannot be undone.', confirmLabel: 'Clear all' })) return
    setClearingAll(true)
    try { await clearAll() } catch {} finally { setClearingAll(false) }
  }

  return <div className="notification-menu" ref={menu}>
    <button type="button" className="topbar-icon notification-menu-trigger" aria-label={t('notifications.title', 'Notifications')} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
      <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M9.5 21h5" /></svg>
      {unreadCount > 0 && <b>{unreadCount > 99 ? '99+' : unreadCount}</b>}
    </button>
    {open && <section className="notification-popover" role="menu" aria-label={t('notifications.title', 'Notifications')}>
      <header><div><b>{t('notifications.title', 'Notifications')}</b><small>{unreadCount ? t('notifications.unread', `${unreadCount} unread`, { count: unreadCount }) : t('notifications.caught_up', 'You are all caught up')}</small></div><div className="notification-popover-actions"><button type="button" className="notification-mark-all" disabled={markingAll || !unreadCount} onClick={markEverythingRead} title={t('notifications.mark_all', 'Mark all read')} aria-label={t('notifications.mark_all', 'Mark all read')}><MarkAllReadIcon /></button><button type="button" className="notification-clear-all" disabled={clearingAll || !notifications.length} onClick={clearEverything} title={t('notifications.clear_all', 'Clear all notifications')} aria-label={t('notifications.clear_all', 'Clear all notifications')}><ClearAllIcon /></button><button type="button" onClick={viewAll}>{t('notifications.view_all', 'View all')}</button></div></header>
      <div className="notification-popover-list">
        {loading && !notifications.length ? <p>{t('notifications.checking', 'Checking for updates...')}</p> : notifications.slice(0, 5).map((notification) => <button type="button" className={`notification-popover-item ${notification.read_at ? 'read' : 'unread'}`} key={notification.id} onClick={() => openNotification(notification)}>
          <span>{iconFor(notification.type)}</span><div><b>{notification.title}</b>{notification.body && <small>{notification.body}</small>}</div>{!notification.read_at && <i />}
        </button>)}
        {!loading && !notifications.length && <p>{t('notifications.empty', 'You have no notifications yet.')}</p>}
      </div>
    </section>}
  </div>
}
