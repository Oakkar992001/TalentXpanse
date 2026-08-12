import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import api from '../services/api'
import { disconnectRealtime, subscribeToUserChannel } from '../services/realtime'
import { useAuth } from './AuthContext'
import { NotificationContext } from './notification-store'

const RECONCILIATION_INTERVAL_MS = 60000
const FALLBACK_INTERVAL_MS = 8000
const unreadTotal = (items) => items.filter((item) => !item.read_at).length

export function NotificationProvider({ children }) {
  const { user, errorMessage } = useAuth()
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const notificationsRef = useRef([])

  const replaceNotifications = useCallback((items) => {
    notificationsRef.current = items
    setNotifications(items)
    setUnreadCount(unreadTotal(items))
  }, [])

  const refresh = useCallback(async ({ silent = false } = {}) => {
    if (!user?.id) {
      replaceNotifications([])
      setLoading(false)
      return []
    }

    if (!silent) setLoading(true)
    try {
      const { data } = await api.get('/notifications')
      const items = data.data || []
      replaceNotifications(items)
      setError('')
      return items
    } catch (requestError) {
      if (!silent) setError(errorMessage(requestError))
      return []
    } finally {
      if (!silent) setLoading(false)
    }
  }, [errorMessage, replaceNotifications, user?.id])

  const receiveNotification = useCallback(({ notification }) => {
    if (!notification?.id) return
    const current = notificationsRef.current
    const index = current.findIndex((item) => item.id === notification.id)
    const items = index >= 0 ? current.map((item) => item.id === notification.id ? notification : item) : [notification, ...current].slice(0, 60)
    replaceNotifications(items)
  }, [replaceNotifications])

  useEffect(() => {
    if (!user?.id) {
      replaceNotifications([])
      disconnectRealtime()
      return undefined
    }

    let active = true
    let unsubscribe = null
    let fallback = null
    const sync = () => { void refresh({ silent: true }) }

    void refresh()
    const reconciliation = window.setInterval(sync, RECONCILIATION_INTERVAL_MS)
    const refreshWhenVisible = () => { if (!document.hidden) sync() }
    window.addEventListener('focus', sync)
    document.addEventListener('visibilitychange', refreshWhenVisible)

    subscribeToUserChannel(user.id, 'marketplace.notification.created', receiveNotification)
      .then((stopListening) => { if (active) unsubscribe = stopListening; else stopListening() })
      .catch(() => { if (active) fallback = window.setInterval(sync, FALLBACK_INTERVAL_MS) })

    return () => {
      active = false
      unsubscribe?.()
      window.clearInterval(reconciliation)
      if (fallback) window.clearInterval(fallback)
      window.removeEventListener('focus', sync)
      document.removeEventListener('visibilitychange', refreshWhenVisible)
    }
  }, [receiveNotification, refresh, replaceNotifications, user?.id])

  const markRead = useCallback(async (notificationId) => {
    const previous = notificationsRef.current.find((item) => item.id === notificationId)
    if (!previous || previous.read_at) return previous

    const optimistic = notificationsRef.current.map((item) => item.id === notificationId ? { ...item, read_at: new Date().toISOString() } : item)
    replaceNotifications(optimistic)

    try {
      const { data } = await api.patch(`/notifications/${notificationId}/read`)
      replaceNotifications(notificationsRef.current.map((item) => item.id === notificationId ? data.data : item))
      return data.data
    } catch (requestError) {
      replaceNotifications(notificationsRef.current.map((item) => item.id === notificationId ? previous : item))
      setError(errorMessage(requestError))
      throw requestError
    }
  }, [errorMessage, replaceNotifications])

  const markAllRead = useCallback(async () => {
    const previous = notificationsRef.current
    if (!unreadTotal(previous)) return

    replaceNotifications(previous.map((item) => item.read_at ? item : { ...item, read_at: new Date().toISOString() }))
    try {
      await api.patch('/notifications/read-all')
      setError('')
    } catch (requestError) {
      replaceNotifications(previous)
      setError(errorMessage(requestError))
      throw requestError
    }
  }, [errorMessage, replaceNotifications])

  const clearAll = useCallback(async () => {
    const previous = notificationsRef.current
    if (!previous.length) return

    replaceNotifications([])
    try {
      await api.delete('/notifications')
      setError('')
    } catch (requestError) {
      replaceNotifications(previous)
      setError(errorMessage(requestError))
      throw requestError
    }
  }, [errorMessage, replaceNotifications])

  const value = useMemo(() => ({ notifications, unreadCount, loading, error, refresh, markRead, markAllRead, clearAll }), [clearAll, error, loading, markAllRead, markRead, notifications, refresh, unreadCount])
  return <NotificationContext.Provider value={value}>{children}</NotificationContext.Provider>
}
