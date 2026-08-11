# TalentXpanse production runbook

For the current free public test deployment, use [OPEN_BETA.md](OPEN_BETA.md). This runbook remains the path for a managed production environment with a custom domain, dedicated workers, WebSockets, backups, and a reviewed payment integration.

## What is ready

- Laravel queues use the database connection and queue jobs after database commits when `QUEUE_AFTER_COMMIT=true`.
- Email verification, password reset, and marketplace activity emails are queued.
- Reverb, the queue worker, and the scheduler have Supervisor templates in `backend/deploy/supervisor`.
- The escrow ledger is internal only. `MARKETPLACE_PAYMENTS_ENABLED` must remain `false` until a reviewed Myanmar payment gateway adapter and webhooks are live.

## First deployment

1. Copy `backend/.env.production.example` to the server as `.env`; generate a unique `APP_KEY` and fill database, mail, domain, and Reverb values. Never commit this file.
2. Install production dependencies and build the frontend:

   ```bash
   cd backend
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize
   cd ../frontend
   npm ci
   npm run build
   ```

3. Copy `frontend/.env.production.example` to `frontend/.env.production` and set its real API URL before building. Do not put backend secrets in Vite variables.
4. Set writable ownership for `backend/storage` and `backend/bootstrap/cache` only.
5. Install the Nginx and Supervisor templates, replacing `example.com`, `/var/www/talentxpanse`, the PHP-FPM socket, and the Linux service user.
6. Enable TLS certificates before exposing the API. Set `APP_URL`, `FRONTEND_URL`, `REVERB_HOST`, `REVERB_ALLOWED_ORIGINS`, and the frontend `VITE_API_URL` to HTTPS origins. Keep Reverb listening on `127.0.0.1:8080`; the Nginx template proxies `/app/` and `/apps/` securely through `api.example.com`.
7. Verify `/up` for process liveness, `/api/health` for runtime readiness, and `php artisan marketplace:operations-check --strict` before promoting the release. A readiness failure returns HTTP 503 without exposing connection details.

## Operations

- Check workers with `supervisorctl status`; use `php artisan queue:failed` and `php artisan queue:retry all` only after investigating the failed job and its logs.
- The scheduler runs saved-search alerts daily and offer expiry hourly. Keep a single scheduler process.
- Reverb must be supervised separately from PHP-FPM so real-time messages and notifications keep working after restarts.
- Send test email verification and password-reset messages after SMTP is configured. Do not enable marketplace email updates until those tests succeed.
- Keep `APP_DEBUG=false`, use a daily/stderr log stack, and route critical log alerts to a monitored service via `LOG_SLACK_WEBHOOK_URL` or equivalent.

## Backups and restore drills

- Use encrypted, automated database backups with at least daily snapshots and a defined retention policy. Keep backups outside the application server. The supplied MySQL backup script and restore-drill process are documented in [BACKUPS.md](BACKUPS.md).
- Back up user-upload storage separately. Test a full database and uploads restore in a non-production environment at least quarterly.
- Record each restore drill date, source backup, duration, and result. A backup is not considered reliable until a restore has succeeded.

## Payment activation gate

Before setting `MARKETPLACE_PAYMENTS_ENABLED=true`, complete all of the following:

1. Implement and review one provider adapter, signed webhook verification, idempotent event handling, and reconciliation.
2. Confirm the provider is licensed and suitable for the intended Myanmar payment flow and that the legal, tax, refund, and user-agreement requirements are approved.
3. Verify funding, release, refund, and dispute outcomes in a sandbox. The internal ledger records balanced entries, but it never substitutes for a real provider settlement.
4. Set `MARKETPLACE_PAYMENT_PROVIDER` to the reviewed adapter identifier and keep it separate from provider secrets.

See [PAYMENTS.md](PAYMENTS.md) for the provider-evaluation questions, integration sequence, and non-negotiable safety rules.

## Staging before production

Use a separate staging environment for every release. It must have its own database, uploads, SMTP inbox, Reverb credentials, and disabled payments. Follow [STAGING.md](STAGING.md), including the guarded desktop/mobile browser journey, before promoting a release.
