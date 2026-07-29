# Staging environment

Staging is the release candidate for TalentXpanse. It must not share a database, mail inbox, uploads bucket, Reverb credentials, or payment credentials with production.

## Create the environment

1. Create `staging.example.com` and `api-staging.example.com`, then enable TLS before the API is exposed.
2. Use the supplied [backend staging environment template](../backend/.env.staging.example) and [frontend staging environment template](../frontend/.env.staging.example). Generate a unique `APP_KEY` on the server; do not copy production secrets.
3. Create a separate MySQL database and user with access only to that database. Do not use the production user.
4. Configure a sandbox SMTP mailbox and send verification/password-reset messages to test accounts before enabling marketplace emails.
5. Install the existing Nginx and Supervisor templates, replacing the example domains and deployment path. Reverb, the queue worker, and the scheduler must all be running.

## Release checks

From the backend release directory:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan marketplace:operations-check --strict
```

Build the frontend only after `VITE_API_URL` points at the staging API:

```bash
cd ../frontend
npm ci
npm run build
```

Confirm `https://api-staging.example.com/up` is live and `https://api-staging.example.com/api/health` returns an `ok` status. The health response exposes only component status, never credentials or connection details.

## User acceptance journey

Run the guarded staging browser suite after each release. It covers both desktop and mobile client ↔ freelancer journeys:

```bash
export TALENTXPANSE_STAGING_URL=https://staging.example.com
export TALENTXPANSE_STAGING_API_URL=https://api-staging.example.com/api
export TALENTXPANSE_STAGING_E2E_CONFIRM=YES
cd frontend
npm run test:e2e:staging
```

The test creates accounts and marketplace records, so reset the staging database or use a dedicated test tenant after each run. Never run this command against production.

## GitHub Actions validation

The manual **Validate staging** workflow reads `STAGING_APP_URL` and `STAGING_API_URL` from the GitHub `staging` environment. Add those as environment variables after DNS and TLS work. The workflow runs only the guarded staging journey; it does not deploy and cannot change production.

## Promotion gate

Promote a release only after all of these are true:

- `marketplace:operations-check --strict` passes.
- Supervisor reports healthy queue, scheduler, and Reverb processes.
- Verification, password reset, and marketplace activity emails arrive in the staging mailbox.
- Desktop and mobile browser journeys pass.
- A database restore drill has passed recently.
- `MARKETPLACE_PAYMENTS_ENABLED` remains `false` unless the separate payment activation gate is complete.
