import path from 'node:path'
import { fileURLToPath } from 'node:url'

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const backendRoot = path.resolve(frontendRoot, '../backend')
const apiPort = 8001
const appPort = 5175
export const externalTarget = process.env.TALENTXPANSE_E2E_TARGET === 'staging' || process.argv.includes('--target=staging')

const trimTrailingSlash = (url) => url?.replace(/\/$/, '')
const stagingAppUrl = trimTrailingSlash(process.env.TALENTXPANSE_STAGING_URL)
const stagingApiUrl = trimTrailingSlash(process.env.TALENTXPANSE_STAGING_API_URL)

export const e2ePaths = {
  frontendRoot,
  backendRoot,
  database: path.join(backendRoot, 'database', 'talentxpanse-e2e.sqlite'),
  configCache: path.join(backendRoot, 'bootstrap', 'cache', 'e2e-config.php'),
}

export const e2eUrls = {
  app: externalTarget ? stagingAppUrl : `http://127.0.0.1:${appPort}`,
  api: externalTarget ? stagingApiUrl : `http://127.0.0.1:${apiPort}/api`,
  health: externalTarget ? null : `http://127.0.0.1:${apiPort}/up`,
}

export const backendEnvironment = {
  ...process.env,
  APP_ENV: 'e2e',
  APP_DEBUG: 'false',
  APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
  APP_URL: `http://127.0.0.1:${apiPort}`,
  FRONTEND_URL: e2eUrls.app,
  APP_CONFIG_CACHE: e2ePaths.configCache,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: e2ePaths.database,
  DB_FOREIGN_KEYS: 'true',
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'array',
  // Mirror production: notifications are placed on the database queue and do
  // not delay the registration response while an email is being prepared.
  QUEUE_CONNECTION: 'database',
  MAIL_MAILER: 'array',
  BROADCAST_CONNECTION: 'null',
  MARKETPLACE_EMAIL_NOTIFICATIONS_ENABLED: 'false',
}

export const frontendEnvironment = {
  ...process.env,
  VITE_API_URL: e2eUrls.api,
}
