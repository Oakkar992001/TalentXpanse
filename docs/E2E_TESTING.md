# End-to-end testing

Run browser tests from `frontend`:

```bash
npm run test:e2e
```

The suite starts a temporary Laravel API on port `8001` and Vite on port `5175`. It creates and removes `backend/database/talentxpanse-e2e.sqlite`, so it never changes the normal local marketplace database. The Laravel `/up` endpoint starts the API before the test database is migrated; the browser journey runs only after that migration finishes.

The first journey runs for desktop and mobile viewports. It covers account registration, job posting, search, proposal-credit usage, proposal chat, hiring, milestones, delivery, approval, completion, reviews, and the logout confirmation dialog.

On Windows, the configuration uses installed Google Chrome for local execution. In CI, install Playwright Chromium before running the suite:

```bash
npx playwright install --with-deps chromium
npm run test:e2e
```

## Staging validation

Do not point this at production. Staging must use a separate database, mail sandbox, storage bucket, and Reverb credentials. The test creates temporary user accounts and marketplace data, so use a staging database that can be reset after each release.

Set these environment variables, then run the guarded staging command:

```bash
export TALENTXPANSE_STAGING_URL=https://staging.example.com
export TALENTXPANSE_STAGING_API_URL=https://api-staging.example.com/api
export TALENTXPANSE_STAGING_E2E_CONFIRM=YES
npm run test:e2e:staging
```

In PowerShell, replace `export NAME=value` with `$env:NAME = 'value'`. The command refuses to run without HTTPS URLs and the explicit confirmation value.
