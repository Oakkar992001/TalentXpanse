import axios from 'axios'

export const SESSION_EXPIRED_EVENT = 'talentxpanse:session-expired'

const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api' })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('tx-token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers['Accept-Language'] = localStorage.getItem('tx-language') === 'my' ? 'my-MM,my;q=0.9,en;q=0.8' : 'en'
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== 'undefined' && localStorage.getItem('tx-token')) {
      window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT))
    }
    return Promise.reject(error)
  },
)

export default api
