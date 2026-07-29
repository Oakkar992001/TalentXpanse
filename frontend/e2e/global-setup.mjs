import { mkdir, rm, writeFile } from 'node:fs/promises'
import { spawnSync } from 'node:child_process'
import { dirname } from 'node:path'
import { backendEnvironment, e2ePaths, externalTarget } from './environment.mjs'

export default async function globalSetup() {
  if (externalTarget) return

  await mkdir(dirname(e2ePaths.database), { recursive: true })
  await rm(e2ePaths.configCache, { force: true })
  await rm(e2ePaths.database, { force: true })
  await writeFile(e2ePaths.database, '')

  const migration = spawnSync('php', ['artisan', 'migrate', '--force', '--no-interaction'], {
    cwd: e2ePaths.backendRoot,
    env: backendEnvironment,
    encoding: 'utf8',
  })

  if (migration.status !== 0) {
    throw new Error(`Unable to prepare the E2E database.\n${migration.stdout}\n${migration.stderr}`)
  }
}
