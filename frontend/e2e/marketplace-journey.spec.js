import { expect, test } from '@playwright/test'

const password = 'E2E-secure-password'

async function register(page, { name, email, role }) {
  await page.goto('/register')
  await page.locator('.role-cards').getByRole('button', { name: role === 'client' ? /Hire talent/ : /Find work/ }).click()
  await page.getByLabel('Full name').fill(name)
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByLabel('Confirm password').fill(password)
  await page.getByLabel(/I agree to the Terms of Use/).check()
  await page.getByLabel(/I agree to the Privacy Policy/).check()
  await page.getByRole('button', { name: role === 'client' ? 'Continue as Hire talent' : 'Continue as Find work' }).click()
  await expect(page).toHaveURL(new RegExp(`/dashboard\\?role=${role}`))
}

async function login(page, { email, role }) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page).toHaveURL(new RegExp(`/dashboard\\?role=${role}`))
}

test.describe.configure({ mode: 'serial' })

test('client and freelancer complete the marketplace journey', async ({ browser }, testInfo) => {
  const suffix = `${testInfo.project.name}-${Date.now()}`
  const clientDetails = { name: `Client ${testInfo.project.name}`, email: `client-${suffix}@e2e.talentxpanse.test`, role: 'client' }
  const freelancerDetails = { name: `Freelancer ${testInfo.project.name}`, email: `freelancer-${suffix}@e2e.talentxpanse.test`, role: 'freelancer' }
  const jobTitle = `E2E ${testInfo.project.name} Laravel marketplace ${suffix}`
  let clientContext = await browser.newContext()
  let freelancerContext = await browser.newContext()
  let clientPage = await clientContext.newPage()
  let freelancerPage = await freelancerContext.newPage()

  try {
    await register(clientPage, clientDetails)
    await clientPage.goto('/dashboard?role=client')
    await expect(clientPage.getByRole('heading', { name: new RegExp(`Welcome back, ${clientDetails.name.split(' ')[0]}`) })).toBeVisible()
    await clientPage.locator('.workspace-header-actions').getByRole('button', { name: 'Post a job' }).click()
    await clientPage.getByLabel('Job title').fill(jobTitle)
    await clientPage.getByLabel('Project description').fill('Build a responsive Laravel and React marketplace workflow with clear milestones, helpful communication, and a documented handover.')
    await clientPage.getByLabel('Budget from (MMK)').fill('500000')
    await clientPage.getByLabel('Budget to (MMK)').fill('600000')
    await clientPage.getByLabel('Skills').fill('Laravel, React, MySQL')
    const jobResponse = clientPage.waitForResponse((response) => response.url().endsWith('/api/jobs') && response.request().method() === 'POST' && response.status() === 201)
    await clientPage.getByRole('button', { name: 'Publish job' }).click()
    const job = await (await jobResponse).json()
    const jobId = job.data.id
    await expect(clientPage.getByText(jobTitle)).toBeVisible()

    await register(freelancerPage, freelancerDetails)
    await freelancerPage.goto(`/search?scope=jobs&q=${encodeURIComponent(jobTitle)}`)
    const jobCard = freelancerPage.locator('.discovery-result-card').filter({ hasText: jobTitle })
    await expect(jobCard).toBeVisible({ timeout: 30_000 })
    await jobCard.click()
    await expect(freelancerPage).toHaveURL(new RegExp(`/search/jobs/${jobId}$`))
    await freelancerPage.getByLabel('Your proposal').fill('I can build this Laravel and React marketplace workflow with responsive screens, practical API validation, clear milestone delivery notes, and a reliable handover for your team.')
    await freelancerPage.getByLabel('Your bid (MMK)').fill('550000')
    await freelancerPage.getByLabel('Delivery days').fill('21')
    const proposalResponse = freelancerPage.waitForResponse((response) => response.url().endsWith(`/api/jobs/${jobId}/proposals`) && response.request().method() === 'POST' && response.status() === 201)
    await freelancerPage.getByRole('button', { name: /Submit proposal/ }).click()
    await proposalResponse
    await expect(freelancerPage.getByText('Proposal sent with 4 Proposal Credits used.')).toBeVisible()

    await clientPage.goto('/messages')
    const startChat = clientPage.locator('.startable-list button').filter({ hasText: freelancerDetails.name })
    await expect(startChat).toHaveCount(1)
    await startChat.click()
    await expect(clientPage.locator('.chat-compose textarea[aria-label="Message"]')).toBeVisible()
    await clientPage.locator('.chat-compose textarea[aria-label="Message"]').fill('Thanks for applying. I would like to discuss the delivery plan before we start.')
    await clientPage.getByRole('button', { name: 'Send' }).click()
    await expect(clientPage.getByText('Thanks for applying. I would like to discuss the delivery plan before we start.')).toBeVisible()
    await freelancerPage.goto('/messages')
    await expect(freelancerPage.getByText('Thanks for applying. I would like to discuss the delivery plan before we start.')).toBeVisible()
    await freelancerPage.locator('.chat-compose textarea[aria-label="Message"]').fill('I can start this week and will share progress at every milestone.')
    await freelancerPage.getByRole('button', { name: 'Send' }).click()
    await Promise.all([clientContext.close(), freelancerContext.close()])
    clientContext = await browser.newContext()
    freelancerContext = await browser.newContext()
    clientPage = await clientContext.newPage()
    freelancerPage = await freelancerContext.newPage()
    await login(clientPage, clientDetails)
    await login(freelancerPage, freelancerDetails)

    await clientPage.goto(`/search/jobs/${jobId}`)
    await expect(clientPage.getByRole('heading', { name: jobTitle })).toBeVisible()
    await expect(clientPage.locator('.client-proposal').filter({ hasText: freelancerDetails.name })).toBeVisible()
    const hireResponse = clientPage.waitForResponse((response) => response.url().includes('/api/proposals/') && response.request().method() === 'PATCH' && response.status() === 200)
    await clientPage.getByRole('button', { name: 'Hire' }).click()
    await hireResponse
    await expect(clientPage.getByText('Freelancer hired. This job is now in progress.')).toBeVisible()

    await clientPage.goto('/projects')
    const projectCard = clientPage.locator('.project-card').filter({ hasText: jobTitle })
    await expect(projectCard).toBeVisible()
    await projectCard.click()
    await expect(clientPage).toHaveURL(/\/projects\/\d+$/)
    const projectPath = new URL(clientPage.url()).pathname
    await clientPage.getByPlaceholder('Milestone title').fill('Marketplace workflow and handover')
    await clientPage.getByPlaceholder('Amount (MMK)').fill('550000')
    await clientPage.getByPlaceholder('Deliverables and acceptance criteria').fill('Deliver the responsive workflow, test notes, and a concise source-code handover.')
    await clientPage.getByRole('button', { name: 'Add milestone' }).click()
    await expect(clientPage.getByText('Milestone created.')).toBeVisible()

    await freelancerPage.goto(projectPath)
    await freelancerPage.getByRole('button', { name: 'Start' }).click()
    await expect(freelancerPage.getByText('Milestone started.')).toBeVisible()
    await freelancerPage.getByRole('button', { name: 'Submit delivery' }).click()
    await freelancerPage.getByLabel('Delivery notes').fill('The responsive marketplace workflow, test notes, and handover are ready for review.')
    await freelancerPage.locator('.delivery-form').getByRole('button', { name: 'Submit delivery' }).click()
    await expect(freelancerPage.getByText('Delivery version 1 was submitted for review.')).toBeVisible()

    await clientPage.goto(projectPath)
    await clientPage.getByRole('button', { name: 'Approve' }).click()
    await expect(clientPage.getByText('Milestone approved.')).toBeVisible()

    await freelancerPage.goto(projectPath)
    await freelancerPage.getByRole('button', { name: 'Mark work ready for completion' }).click()
    await freelancerPage.getByRole('button', { name: 'Mark work ready' }).click()
    await expect(freelancerPage.getByText('You marked the work ready.')).toBeVisible()

    await clientPage.goto(projectPath)
    await clientPage.getByRole('button', { name: 'Complete project' }).click()
    await clientPage.getByRole('button', { name: 'Confirm completion' }).click()
    await expect(clientPage.getByText('Project completed. You can now leave a review.')).toBeVisible()
    await clientPage.getByLabel('Review').fill('Clear progress updates, reliable delivery, and a thoughtful handover.')
    await clientPage.getByRole('button', { name: 'Submit review' }).click()
    await expect(clientPage.getByText('Your 5-star review was submitted.')).toBeVisible()

    await freelancerPage.goto(projectPath)
    await freelancerPage.getByLabel('Review').fill('Well prepared project requirements and practical feedback throughout delivery.')
    await freelancerPage.getByRole('button', { name: 'Submit review' }).click()
    await expect(freelancerPage.getByText('Reviews are now visible to both people.')).toBeVisible()

    await clientPage.getByRole('button', { name: `Open account menu for ${clientDetails.name}` }).click()
    await clientPage.getByRole('button', { name: 'Log out' }).click()
    await expect(clientPage.getByRole('alertdialog')).toContainText('Log out of TalentXpanse?')
    await clientPage.getByRole('button', { name: 'Cancel' }).click()
    await expect(clientPage.getByRole('alertdialog')).toHaveCount(0)
    await clientPage.getByRole('button', { name: 'Log out' }).click()
    await clientPage.getByRole('button', { name: 'Log out' }).last().click()
    await expect(clientPage).toHaveURL(/\/login/)
  } finally {
    await Promise.all([clientContext.close(), freelancerContext.close()])
  }
})
