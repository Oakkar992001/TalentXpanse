# Payment-provider activation

TalentXpanse is intentionally payment-safe but not payment-live. The application records MMK milestone amounts, the 10% client platform fee, an immutable internal ledger, project holds, disputes, refunds, and release rules. It does not claim to hold, settle, or pay real money while `MARKETPLACE_PAYMENTS_ENABLED=false`.

## Recommended provider evaluation

Evaluate a provider that supports all of these marketplace requirements before writing an adapter:

- customer checkout for client funding;
- a provider-confirmed webhook for each funding event;
- payouts to freelancers or a documented approved alternative;
- reconciliation and transaction lookup;
- idempotent event handling and a sandbox;
- written approval for the intended marketplace/escrow-like flow.

Wave Business is the first provider to evaluate because its official Business API describes checkout, payout, reconciliation, and webhook capabilities. This is not an approval to activate it: TalentXpanse still needs a Wave Business account, confirmed commercial terms, sandbox access, and written confirmation that the marketplace flow is permitted.

KBZPay/MMQR should be evaluated as a second local acceptance option. It may be useful for client funding, but do not assume it supports freelancer payout or funds-holding without direct confirmation from KBZPay and legal review.

## Information required before implementation

Collect these through the chosen provider’s official merchant onboarding process:

1. A registered business/merchant account in the correct legal entity name.
2. Sandbox and production API credentials, stored only in the deployment secret manager.
3. API documentation for checkout, payout, status lookup, webhooks, retry rules, signing, and idempotency.
4. The public HTTPS webhook URL and permitted IP/signature verification requirements.
5. Written clarification on holding client funds, partial refunds, disputes, taxes, transaction limits, KYC, and payout eligibility.
6. A reconciliation report and settlement schedule that finance/admin users can check daily.

## Implementation order

1. Add one provider adapter behind the existing `MarketplaceEscrowService`; never let the browser call the provider with a secret key.
2. Create funding checkout on the server and save the provider reference before redirecting the client.
3. Accept only verified, idempotent provider webhooks. A confirmed funding event calls `recordFunding`; a verified payout/release event calls `recordRelease`; a confirmed refund calls `recordRefund`.
4. Keep provider metadata hidden from normal API responses. Reconcile provider events against internal payment records daily.
5. Exercise successful funding, duplicate webhook delivery, failed payment, release, partial refund, dispute hold, and recovery paths in sandbox.
6. Run the staging browser journey and an operations review. Only then set `MARKETPLACE_PAYMENT_PROVIDER` and `MARKETPLACE_PAYMENTS_ENABLED=true`.

## Non-negotiable safety rules

- Never use an admin button or browser request as proof that money moved.
- Never release an approved milestone until the provider confirms the client funding event.
- Do not pay a freelancer until the provider confirms the payout/release event.
- Keep payments disabled if a provider cannot support the required flow or if commercial/legal approval is unclear.
- Treat all provider webhooks as untrusted until signature verification and idempotency checks succeed.
