# Real email delivery gate

TalentXpanse must not rely on password reset, email verification, or urgent marketplace email alerts until a verified sender domain and real SMTP provider are configured.

## Required setup

1. Choose a transactional provider that can send from a TalentXpanse-owned domain (for example Resend, Postmark, Amazon SES, or a managed SMTP provider).
2. Add and verify the provider's SPF and DKIM records, plus DMARC for the domain.
3. Set production secrets only in the host environment:

   ```text
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.provider.example
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=hello@your-domain.example
   MAIL_FROM_NAME=TalentXpanse
   MARKETPLACE_EMAIL_NOTIFICATIONS_ENABLED=true
   ```

4. Deploy to staging first. Send and receive a verification link, password reset link, a project notification, and a queued saved-search notification. Confirm links use the real HTTPS frontend URL.
5. Check provider suppression/bounce events and queue failures before enabling the same configuration in production.

Never place SMTP credentials in `.env.example`, Git, Cloudflare Pages variables, screenshots, or chat. Until this checklist passes, keep `MAIL_MAILER=log` and present email-dependent features as beta-limited.
