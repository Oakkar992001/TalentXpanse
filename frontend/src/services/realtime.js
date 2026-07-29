import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import api from './api'

let echo = null
let connection = null

const broadcastAuthEndpoint = () => `${String(api.defaults.baseURL).replace(/\/$/, '')}/broadcasting/auth`

async function connect() {
  if (echo) return echo
  if (connection) return connection

  connection = api.get('/realtime/config').then(({ data }) => {
    const config = data.data
    if (!config?.key || !config?.host || !config?.port) throw new Error('Realtime updates are not configured.')

    window.Pusher = Pusher
    echo = new Echo({
      broadcaster: 'reverb',
      key: config.key,
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: broadcastAuthEndpoint(),
      auth: { headers: { Authorization: `Bearer ${localStorage.getItem('tx-token') || ''}` } },
    })

    return echo
  }).catch((error) => {
    connection = null
    throw error
  })

  return connection
}

export async function subscribeToUserChannel(userId, event, listener) {
  const client = await connect()
  const channel = client.private(`marketplace.user.${userId}`)
  const eventName = `.${event}`
  channel.listen(eventName, listener)

  return () => channel.stopListening(eventName, listener)
}

export function disconnectRealtime() {
  echo?.disconnect()
  echo = null
  connection = null
}
