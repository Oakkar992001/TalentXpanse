import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { SESSION_EXPIRED_EVENT } from '../services/api'

const AuthContext = createContext(null)
const tokenKey = 'tx-token'
const tokenExpiryKey = 'tx-token-expires-at'

function messageFrom(error) {
  const errors = error.response?.data?.errors
  if (errors) return Object.values(errors).flat()[0]
  return error.response?.data?.message || 'Something went wrong. Please try again.'
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const [sessionExpiresAt, setSessionExpiresAt] = useState(() => localStorage.getItem(tokenExpiryKey))
  const [sessionExpired, setSessionExpired] = useState(false)

  const clearAuthentication = useCallback(() => {
    localStorage.removeItem(tokenKey)
    localStorage.removeItem(tokenExpiryKey)
    setUser(null)
    setSessionExpiresAt(null)
  }, [])

  const expireSession = useCallback(() => {
    clearAuthentication()
    setSessionExpired(true)
  }, [clearAuthentication])

  useEffect(() => {
    const token = localStorage.getItem(tokenKey)
    const expiresAt = localStorage.getItem(tokenExpiryKey)
    if (!token) { setLoading(false); return }
    if (expiresAt && Date.parse(expiresAt) <= Date.now()) {
      expireSession()
      setLoading(false)
      return
    }
    api.get('/auth/user').then(({ data }) => setUser(data.user)).catch((error) => {
      if (error.response?.status === 401) expireSession()
      else clearAuthentication()
    }).finally(() => setLoading(false))
  }, [clearAuthentication, expireSession])

  useEffect(() => {
    window.addEventListener(SESSION_EXPIRED_EVENT, expireSession)
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, expireSession)
  }, [expireSession])

  useEffect(() => {
    if (!user || !sessionExpiresAt) return undefined
    const delay = Date.parse(sessionExpiresAt) - Date.now()
    if (delay <= 0) {
      expireSession()
      return undefined
    }
    const timeout = window.setTimeout(expireSession, delay)
    return () => window.clearTimeout(timeout)
  }, [expireSession, sessionExpiresAt, user])

  const finishAuth = ({ token, expires_at: expiresAt, user: authenticatedUser }) => {
    localStorage.setItem(tokenKey, token)
    if (expiresAt) localStorage.setItem(tokenExpiryKey, expiresAt)
    else localStorage.removeItem(tokenExpiryKey)
    setUser(authenticatedUser)
    setSessionExpiresAt(expiresAt || null)
    setSessionExpired(false)
    return authenticatedUser
  }

  const value = useMemo(() => ({
    user, loading, sessionExpired,
    refreshUser: async () => {
      const { data } = await api.get('/auth/user')
      setUser(data.user)
      return data.user
    },
    login: async (payload) => finishAuth((await api.post('/auth/login', payload)).data),
    adminLogin: async (payload) => finishAuth((await api.post('/admin/auth/login', payload)).data),
    register: async (payload) => finishAuth((await api.post('/auth/register', payload)).data),
    googleLogin: async (payload) => finishAuth((await api.post('/auth/google', payload)).data),
    addRole: async (role) => {
      const { data } = await api.post('/auth/roles', { role })
      setUser(data.user)
      return data.user
    },
    switchRole: async (role) => {
      const { data } = await api.patch('/auth/active-role', { role })
      setUser(data.user)
      return data.user
    },
    logout: async () => {
      try { await api.post('/auth/logout') } finally { clearAuthentication(); setSessionExpired(false) }
    },
    errorMessage: messageFrom,
  }), [clearAuthentication, loading, sessionExpired, user])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export const useAuth = () => useContext(AuthContext)
