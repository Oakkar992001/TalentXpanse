import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../services/api'
import { useAuth } from '../contexts/AuthContext'
import '../notifications.css'

const when = (date) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))

export default function NotificationsScreen() {
  const { user, errorMessage } = useAuth()
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const load = () => api.get('/notifications').then(({ data }) => setNotifications(data.data)).catch((requestError) => setError(errorMessage(requestError)))

  useEffect(() => { if (user) load() }, [user?.id])
  const open = async (notification) => { if (!notification.read_at) await api.patch(`/notifications/${notification.id}/read`); navigate(notification.url || '/dashboard') }
  const markAllRead = async () => { setBusy(true); try { await api.patch('/notifications/read-all'); await load() } catch (requestError) { setError(errorMessage(requestError)) } finally { setBusy(false) } }

  if (!user) return <section className="simple-page"><h1>Your notifications are waiting.</h1><Link className="button button-primary" to="/login">Log in</Link></section>
  const unread = notifications.filter((notification) => !notification.read_at).length
  return <section className="notifications-page"><header><div><p className="eyebrow">Notification center</p><h1>Stay on top of your work.</h1><p>Proposals, messages, projects, and review updates appear here.</p></div>{unread > 0 && <button disabled={busy} className="button button-outline" onClick={markAllRead}>Mark all read</button>}</header>{error && <p className="form-notice">{error}</p>}<section className="notifications-list">{notifications.map((notification) => <button className={`notification-item ${notification.read_at ? 'read' : 'unread'}`} key={notification.id} onClick={() => open(notification)}><span className="notification-icon">{notification.type.includes('message') ? '✉' : notification.type.includes('milestone') ? '✓' : notification.type.includes('review') ? '★' : '●'}</span><div><b>{notification.title}</b>{notification.body && <p>{notification.body}</p>}<small>{when(notification.created_at)}</small></div>{!notification.read_at && <i />}</button>)}{!notifications.length && <p className="empty-notifications">Nothing new yet. Your marketplace activity will appear here.</p>}</section></section>
}
