import './ProposalCreditsSettings.css'

const formatDate = (value) => value ? new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : 'No expiry'

const formatTransaction = (transaction) => {
  if (transaction.type === 'credit_expired') return 'Expired'
  if (transaction.type === 'proposal_submission') return 'Proposal sent'
  if (transaction.type === 'proposal_refund') return 'Cancellation refund'
  if (transaction.type === 'monthly_grant') return 'Monthly grant'
  return transaction.description || 'Credit update'
}

const expiresSoon = (value) => value && new Date(value).getTime() - Date.now() < 14 * 24 * 60 * 60 * 1000

export default function ProposalCreditsSettings({ credits }) {
  if (!credits) return <section className="settings-card credits-settings"><p className="settings-loading">Loading your credit balance…</p></section>

  const isPremium = credits.membership_tier === 'premium'
  const earliestExpiry = credits.earliest_expiry

  return <div className="proposal-credits-settings">
    <section className="settings-card credit-overview-card">
      <div className="credit-overview-heading">
        <div><p className="eyebrow">Freelancer workspace</p><h2>Proposal Credits</h2><p>Use credits to send proposals. We always use credits that expire first.</p></div>
        <span className={`credit-plan-badge ${isPremium ? 'premium' : 'free'}`}>{credits.membership_label} plan</span>
      </div>
      <div className="credit-balance-display"><strong>{credits.balance}</strong><span>available now</span></div>
      <div className="credit-policy-stats">
        <article><b>{credits.monthly_allowance}</b><span>monthly credits</span></article>
        <article><b>{credits.balance_cap}</b><span>{isPremium ? 'Premium credit cap' : 'Free credit cap'}</span></article>
        <article><b>{credits.credit_expiry_days} days</b><span>monthly credit expiry</span></article>
        <article><b>{formatDate(credits.next_grant_on)}</b><span>next grant</span></article>
      </div>
      {earliestExpiry && <aside className={`credit-expiry-alert ${expiresSoon(earliestExpiry.expires_at) ? 'soon' : ''}`}>
        <span aria-hidden="true">◷</span><p><b>{earliestExpiry.credits} credit{earliestExpiry.credits === 1 ? '' : 's'} expire {formatDate(earliestExpiry.expires_at)}.</b> Apply to the most suitable opportunities before then.</p>
      </aside>}
    </section>

    <section className="settings-card credit-grants-card">
      <div className="settings-card-heading"><div><p className="eyebrow">Available balance</p><h2>Credit expiry</h2></div><span className="credit-oldest-note">Oldest expiry is used first</span></div>
      {credits.grants?.length ? <div className="credit-grant-list">
        {credits.grants.map((grant) => <article key={grant.id}>
          <div><b>{grant.label}</b><small>Granted {formatDate(grant.granted_at)}</small></div>
          <div><b>{grant.remaining_amount} left</b><small>{grant.expires_at ? `Expires ${formatDate(grant.expires_at)}` : 'Does not expire'}</small></div>
        </article>)}
      </div> : <p className="credit-empty-state">You do not have any available credits yet. Your next grant will be added automatically.</p>}
    </section>

    <section className="settings-card credit-ledger-card">
      <div className="settings-card-heading"><div><p className="eyebrow">Account history</p><h2>Recent credit activity</h2></div></div>
      {credits.transactions?.length ? <div className="credit-transaction-list">
        {credits.transactions.map((transaction) => <article key={transaction.id}>
          <div><b>{formatTransaction(transaction)}</b><small>{transaction.description || formatDate(transaction.created_at)}</small></div>
          <div><strong className={transaction.amount > 0 ? 'credit-positive' : 'credit-negative'}>{transaction.amount > 0 ? '+' : ''}{transaction.amount}</strong><small>{transaction.balance_after} after</small></div>
        </article>)}
      </div> : <p className="credit-empty-state">Your grants and proposal activity will appear here.</p>}
    </section>

    {!isPremium && <section className="settings-card credit-premium-card">
      <div><p className="eyebrow">Coming with billing</p><h2>Premium credits</h2><p>Premium will include 60 credits each month, a 180-credit cap, and 180-day expiry. Purchased credit packs will remain valid for 12 months.</p></div>
      <span>Premium checkout is not available yet</span>
    </section>}
  </div>
}
