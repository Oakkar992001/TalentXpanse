import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useNotifications } from '../hooks/useNotifications'
import { usePreferences } from '../contexts/PreferencesContext'
import '../notifications.css'

const notificationIcon = (type) => {
  if (type.includes('message')) return '✉'
  if (type.includes('milestone')) return '✓'
  if (type.includes('review')) return '★'
  return '●'
}

export default function NotificationsScreen() {
  const { user } = useAuth()
  const { notifications, unreadCount, loading, error, markRead, markAllRead } = useNotifications()
  const { t, formatDate } = usePreferences()
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const open = (notification) => {
    if (!notification.read_at) void markRead(notification.id).catch(() => {})
    navigate(notification.url || '/dashboard')
  }
  const markAll = async () => {
    setBusy(true)
    try { await markAllRead() } finally { setBusy(false) }
  }
  if (!user) return <section className="simple-page"><h1>{t('notifications.waiting', 'Your notifications are waiting.')}</h1><Link className="button button-primary" to="/login">{t('nav.login', 'Log in')}</Link></section>

  return <section className="notifications-page"><header><div><p className="eyebrow">{t('notifications.center', 'Notification center')}</p><h1>{t('notifications.heading', 'Stay on top of your work.')}</h1><p>{t('notifications.detail', 'Proposals, messages, projects, and review updates appear here.')}</p><span className="notification-live-status"><i />{t('notifications.live', 'Live updates on')}</span></div>{unreadCount > 0 && <button disabled={busy} className="button button-outline" onClick={markAll}>{busy ? t('notifications.updating', 'Updating...') : t('notifications.mark_all', 'Mark all read')}</button>}</header>
    {error && <p className="form-notice" role="alert">{error}</p>}<section className="notifications-list" aria-live="polite">{notifications.map((notification) => <button className={`notification-item ${notification.read_at ? 'read' : 'unread'}`} key={notification.id} onClick={() => open(notification)}><span className="notification-icon">{notificationIcon(notification.type)}</span><div><b>{notification.title}</b>{notification.body && <p>{notification.body}</p>}<small>{formatDate(notification.created_at, { dateStyle: 'medium', timeStyle: 'short' })}</small></div>{!notification.read_at && <i />}</button>)}{loading && !notifications.length ? <p className="empty-notifications">{t('notifications.checking', 'Checking for updates...')}</p> : !notifications.length && <p className="empty-notifications">{t('notifications.empty_full', 'Nothing new yet. Your marketplace activity will appear here.')}</p>}</section></section>
}
