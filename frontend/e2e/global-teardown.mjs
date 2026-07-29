import { rm } from 'node:fs/promises'
import { e2ePaths, externalTarget } from './environment.mjs'

export default async function globalTeardown() {
  if (externalTarget) return

  await Promise.all([
    rm(e2ePaths.database, { force: true }),
    rm(e2ePaths.configCache, { force: true }),
  ])
}
