import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import api from '../services/api'

const AuthContext = createContext(null)

function messageFrom(error) {
  const errors = error.response?.data?.errors
  if (errors) return Object.values(errors).flat()[0]
  return error.response?.data?.message || 'Something went wrong. Please try again.'
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!localStorage.getItem('tx-token')) { setLoading(false); return }
    api.get('/auth/user').then(({ data }) => setUser(data.user)).catch(() => localStorage.removeItem('tx-token')).finally(() => setLoading(false))
  }, [])

  const finishAuth = ({ token, user: authenticatedUser }) => {
    localStorage.setItem('tx-token', token)
    setUser(authenticatedUser)
    return authenticatedUser
  }

  const value = useMemo(() => ({
    user, loading,
    login: async (payload) => finishAuth((await api.post('/auth/login', payload)).data),
    register: async (payload) => finishAuth((await api.post('/auth/register', payload)).data),
    googleLogin: async (payload) => finishAuth((await api.post('/auth/google', payload)).data),
    addRole: async (role) => {
      const { data } = await api.post('/auth/roles', { role })
      setUser(data.user)
      return data.user
    },
    logout: async () => {
      try { await api.post('/auth/logout') } finally { localStorage.removeItem('tx-token'); setUser(null) }
    },
    errorMessage: messageFrom,
  }), [user, loading])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export const useAuth = () => useContext(AuthContext)
