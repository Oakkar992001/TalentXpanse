import { spawn } from 'node:child_process'
import { once } from 'node:events'
import path from 'node:path'
import globalSetup from './global-setup.mjs'
import globalTeardown from './global-teardown.mjs'
import { backendEnvironment, e2ePaths, e2eUrls, frontendEnvironment } from './environment.mjs'

const isWindows = process.platform === 'win32'
const isStagingRun = process.argv.includes('--target=staging')
const playwrightArguments = process.argv.slice(2).filter((argument) => argument !== '--target=staging')
let backend
let frontend
let closing = false

const waitFor = async (url, label) => {
  const deadline = Date.now() + 120_000
  let lastError
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url)
      if (response.ok) return
      lastError = new Error(`${label} returned ${response.status}`)
    } catch (error) {
      lastError = error
    }
    await new Promise((resolve) => setTimeout(resolve, 250))
  }
  throw new Error(`${label} did not start. ${lastError?.message || ''}`)
}

const start = (command, args, options) => {
  const child = spawn(command, args, { ...options, stdio: 'inherit' })
  child.once('error', (error) => console.error(error))
  return child
}

const stop = async (child) => {
  if (!child || child.exitCode !== null) return
  if (isWindows) {
    const taskkill = spawn('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' })
    await once(taskkill, 'close')
    return
  }
  child.kill('SIGTERM')
  await once(child, 'close')
}

const cleanup = async (exitCode) => {
  if (closing) return
  closing = true
  await Promise.all([stop(frontend), stop(backend)])
  await globalTeardown()
  process.exit(exitCode)
}

const assertStagingTarget = () => {
  if (process.env.TALENTXPANSE_STAGING_E2E_CONFIRM !== 'YES') {
    throw new Error('Set TALENTXPANSE_STAGING_E2E_CONFIRM=YES before running browser tests against staging.')
  }

  for (const [label, url] of Object.entries({ TALENTXPANSE_STAGING_URL: e2eUrls.app, TALENTXPANSE_STAGING_API_URL: e2eUrls.api })) {
    if (!url || !url.startsWith('https://')) {
      throw new Error(`${label} must be an HTTPS URL for a staging browser test.`)
    }
  }
}

process.once('SIGINT', () => { void cleanup(130) })
process.once('SIGTERM', () => { void cleanup(143) })

try {
  if (isStagingRun) {
    assertStagingTarget()
    const playwright = start(process.execPath, [path.join(e2ePaths.frontendRoot, 'node_modules', '@playwright', 'test', 'cli.js'), 'test', ...playwrightArguments], {
      cwd: e2ePaths.frontendRoot,
      env: { ...process.env, TALENTXPANSE_E2E_MANAGED: '1', TALENTXPANSE_E2E_TARGET: 'staging' },
    })
    const [exitCode] = await once(playwright, 'close')
    await cleanup(exitCode || 0)
  }

  await globalSetup()
  backend = start('php', ['-S', '127.0.0.1:8001', '-t', 'public'], { cwd: e2ePaths.backendRoot, env: backendEnvironment })
  await waitFor(e2eUrls.health, 'Laravel E2E server')
  frontend = start(process.execPath, [path.join(e2ePaths.frontendRoot, 'node_modules', 'vite', 'bin', 'vite.js'), '--host', '127.0.0.1', '--port', '5175', '--strictPort'], { cwd: e2ePaths.frontendRoot, env: frontendEnvironment })
  await waitFor(e2eUrls.app, 'Vite E2E server')
  const playwright = start(process.execPath, [path.join(e2ePaths.frontendRoot, 'node_modules', '@playwright', 'test', 'cli.js'), 'test', ...playwrightArguments], {
    cwd: e2ePaths.frontendRoot,
    env: { ...process.env, TALENTXPANSE_E2E_MANAGED: '1' },
  })
  const [exitCode] = await once(playwright, 'close')
  await cleanup(exitCode || 0)
} catch (error) {
  console.error(error)
  await cleanup(1)
}
