# TalentXpanse open beta

This is a safe public-test deployment, not a payment launch. It deploys the existing Laravel API, React app, roles, proposals, projects, live polling, admin panel, and private NRC review workflow. `MARKETPLACE_PAYMENTS_ENABLED` stays `false`.

## Hosting layout

| Part | Service | Why |
| --- | --- | --- |
| React app | Cloudflare Pages | Free HTTPS URL and automatic Git deployments |
| Laravel API, queue, scheduler | Render web service | Runs the existing Docker image and background workers |
| Database and uploads | Supabase | Free Postgres database plus private/public S3-compatible storage |
| Uploads | Supabase Storage | Private sensitive documents plus separate public profile photos |
| Email | Laravel log during beta | Avoids an unverified sender domain; configure Resend later |

The Render container starts Laravel, Nginx, the database queue worker, and the scheduler. The frontend falls back to its existing polling when Reverb is disabled, so messages, notifications, and project updates still refresh during the beta without a separate WebSocket host.

## Before deployment

Create accounts for Cloudflare, Supabase, and Render using the project owner's email. Do not share passwords, API keys, database URLs, or the administrator password in Git, chat, or frontend environment variables.

Create a 32-byte Laravel key locally, then keep it only in Render:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

For a beta with friends, use a real domain before relying on email verification or password recovery. A `pages.dev` address is not a valid sender domain. This deployment keeps mail in Laravel's log, so public reset/verification email delivery is intentionally not considered ready until an email provider and owned domain are configured.

## 1. Create persistent storage first

In Supabase, create a free project, then open **Storage** and create two buckets:

| Bucket | Access | Used for |
| --- | --- | --- |
| `talentxpanse-private` | Keep private | NRC images, CVs, proposal attachments, chat attachments, milestone files |
| `talentxpanse-public` | Public read only | Profile photos |

Set the public bucket's MIME types to `image/jpeg,image/png,image/webp` and its size limit to 5 MB. Keep the private bucket private; its access is enforced by Laravel before every download.

In **Storage > Configuration > S3**, enable the S3 protocol and generate a server-side access key and secret. Save the secret immediately: it is displayed only once. Use the endpoint and region shown there. The key bypasses Supabase RLS, so store it only in Render—never in React or a public repository.

Set `MARKETPLACE_PUBLIC_AWS_URL` to `https://PROJECT_REF.supabase.co/storage/v1/object/public/talentxpanse-public`. Never construct a public URL for `talentxpanse-private`.

The application authorizes private document downloads through Laravel. Admin identity-document previews remain restricted to signed-in administrators and are not made public by Supabase.

## 2. Use the Supabase database

The Supabase project created for storage also provides the beta Postgres database. In **Connect > Direct > Session pooler**, copy the session-pooler connection details into Render as `DB_URL`; it is the compatible choice for an IPv4-hosted API service. Set `DB_CONNECTION=pgsql` and keep `DB_SSLMODE=require`.

Never use a Render free Postgres database for the beta's long-lived data. Supabase keeps the database independent from the free API instance. Its free project may pause after a period of inactivity, so this is suitable for an invite-only beta, not a production SLA.

## 3. Deploy the Laravel API on Render

1. Push this project to the existing GitHub repository.
2. In Render, choose **New > Blueprint** and select the repository. The root [`render.yaml`](../render.yaml) creates the `talentxpanse-api` Docker web service in Singapore.
3. Render prompts for each secret marked `sync: false`. Use [`backend/.env.open-beta.example`](../backend/.env.open-beta.example) as the field checklist. Do not upload that file with real values.
4. Set `APP_URL` to the API's real `https://...onrender.com` URL, then set `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS` to the exact Cloudflare Pages URL. Do not include a trailing slash.
5. Set both private and public Supabase S3 variables. Use the generated S3 access key and secret for both disk entries, the endpoint shown by Supabase, and the public URL of only the public bucket.
6. Leave `MAIL_MAILER=log` for this beta. Once an owned domain is available, configure Resend and a verified `MAIL_FROM_ADDRESS` before enabling public reset/verification email delivery.
7. Set a strong, unique `OPEN_BETA_ADMIN_EMAIL` and `OPEN_BETA_ADMIN_PASSWORD` (at least 12 characters). The first startup creates the administrator and runs migrations automatically.
8. After the service becomes healthy, remove `OPEN_BETA_ADMIN_PASSWORD` from Render. Future deploys preserve the account and never reset its password.

The API is ready only when `https://YOUR_API.onrender.com/api/health` returns `"status":"ok"`. Check the stricter guard from a Render shell or a future paid host with:

```bash
php artisan marketplace:operations-check --strict
```

Do not invite users to upload NRC images until this health endpoint is healthy with Supabase Storage configured.

## 4. Deploy the React frontend on Cloudflare Pages

1. In **Workers & Pages**, create a Pages project from the same GitHub repository.
2. Set **Root directory** to `frontend`.
3. Set **Build command** to `npm ci && npm run build` and **Build output directory** to `dist`.
4. Add these Pages build variables:

```text
VITE_API_URL=https://YOUR_API.onrender.com/api
VITE_GOOGLE_CLIENT_ID=YOUR_GOOGLE_WEB_CLIENT_ID
```

5. In the existing Google Cloud OAuth web client, add the Pages URL under **Authorized JavaScript origins**. Add a custom domain there too if you introduce one. Do not put a Google client secret in the frontend.
6. Deploy, copy the `https://YOUR_PROJECT.pages.dev` URL, and update the Render `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS` values to match it exactly.

`frontend/public/_redirects` preserves React routes after a browser refresh. `frontend/public/_headers` adds baseline browser hardening without blocking Google sign-in.

## 5. Open-beta acceptance test

Use a normal client account, a normal freelancer account, and the separate administrator account. Test this sequence on desktop and mobile:

1. Register/login and check the session timeout.
2. Complete profiles, upload a profile photo, and submit an NRC request.
3. Log in at `/admin/login`, review both private NRC images, then approve or reject the request.
4. Client posts a job; freelancer searches, attaches an optional CV, and submits one proposal.
5. Client hires the freelancer; both exchange messages, create a milestone, submit work, approve it, and complete the project.
6. Verify notifications route to the correct job, conversation, or project. Wait briefly for polling rather than refreshing manually.
7. Confirm all money-facing screens state that payments are unavailable. Do not take payment details, promise escrow, or release money in this beta.

Record defects with the affected URL, role, device size, and a screenshot. Reset test data only after exporting anything needed for review.

## Open-beta limits and safety rules

- Render's free service can sleep when idle, so the first request may be slow. This is acceptable for a small demo, not a production SLA.
- The Render filesystem is disposable. All user uploads must use Supabase Storage; the database must use Neon.
- The existing realtime fallback is polling. It is reliable for beta use but is not instant WebSocket delivery.
- No real Myanmar payment provider, card, wallet, or escrow transfer is enabled. The current ledger is internal preparation only.
- Keep the beta invitation-only initially. Use real but minimal test data; never ask testers to upload bank details or identity documents unless your privacy process, private storage, and admin reviewers are ready.
- Take a Supabase database backup/export before schema changes. Back up private Supabase data separately if you decide to retain it.

## Provider references

- [Cloudflare Pages Vite deployment](https://developers.cloudflare.com/pages/framework-guides/deploy-a-vite3-project/)
- [Render Blueprint specification](https://render.com/docs/blueprint-spec)
- [Supabase connection options](https://supabase.com/docs/guides/platform/connect-to-your-database)
- [Supabase Storage buckets](https://supabase.com/docs/guides/storage/buckets/fundamentals)
- [Supabase S3 authentication](https://supabase.com/docs/guides/storage/s3/authentication)
- [Resend Laravel mailer](https://resend.com/laravel)
