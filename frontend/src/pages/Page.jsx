import { Link } from 'react-router-dom'
import { usePreferences } from '../contexts/PreferencesContext'
import '../public-pages.css'

const categories = [
  ['category.development', 'Development & IT'],
  ['category.design', 'Design & Creative'],
  ['category.marketing', 'Sales & Marketing'],
  ['category.writing', 'Writing & Translation'],
  ['category.support', 'Admin Support'],
  ['category.finance', 'Finance & Accounting'],
]

function HomePage() {
  const { t } = usePreferences()
  const copy = {
    eyebrow: t('home.eyebrow', 'Myanmar freelance marketplace'),
    title: t('home.title', 'Myanmar talent. Meaningful opportunity.'),
    body: t('home.body', 'TalentXpanse connects businesses with skilled Myanmar professionals through a clear, trustworthy project workflow.'),
    explore: t('nav.explore_marketplace', 'Explore the marketplace'),
    heading: t('home.heading', 'Work that matches your expertise'),
  }

  return <>
    <section className="home-hero">
      <div className="hero-copy">
        <p className="eyebrow">{copy.eyebrow}</p><h1>{copy.title}</h1><p>{copy.body}</p>
        <div className="hero-actions"><Link to="/jobs" className="button button-primary">{copy.explore}</Link><Link to="/how-it-works" className="button button-outline">{t('nav.how_it_works', 'Understand the workflow')}</Link></div>
        <p className="hero-assurance"><span>01</span> {t('home.assurance', 'Explore real opportunities and the project process before creating a workspace.')}</p>
      </div>
      <div className="hero-art" aria-label={t('home.workflow_label', 'A simple freelance project workflow')}>
        <div className="orb orb-one" /><div className="orb orb-two" />
        <div className="profile-float"><span className="float-icon">01</span><div><b>{t('home.discover_title', 'Discover the right fit')}</b><small>{t('home.discover_detail', 'Explore jobs and professional profiles.')}</small></div></div>
        <div className="project-float"><small>{t('home.workflow_label', 'PROJECT WORKFLOW')}</small><b>{t('home.plan_confidence', 'Plan with confidence')}</b><div className="workflow-pills"><span>{t('home.scope', 'Scope')}</span><span>{t('home.milestones', 'Milestones')}</span><span>{t('home.delivery', 'Delivery')}</span></div></div>
        <div className="hero-person" aria-hidden="true">TX</div>
      </div>
    </section>
    <section className="guest-workflow"><div><p className="eyebrow">{t('home.workflow_eyebrow', 'Built for practical collaboration')}</p><h2>{t('home.workflow_title', 'From first search to completed work.')}</h2></div><ol>
      <li><span>1</span><div><b>{t('home.find_fit', 'Find a fit')}</b><small>{t('home.find_fit_detail', 'Browse opportunities or discover talent by specialty.')}</small></div></li>
      <li><span>2</span><div><b>{t('home.agree_clearly', 'Agree clearly')}</b><small>{t('home.agree_clearly_detail', 'Use proposals, messages, and milestones to set expectations.')}</small></div></li>
      <li><span>3</span><div><b>{t('home.deliver_well', 'Deliver well')}</b><small>{t('home.deliver_well_detail', 'Keep each project organised and build a trusted work history.')}</small></div></li>
    </ol></section>
    <section className="category-section"><div className="section-heading"><div><p className="eyebrow">{copy.explore}</p><h2>{copy.heading}</h2></div><Link to="/jobs">{t('home.browse_jobs', 'Browse current jobs')} <span aria-hidden="true">-&gt;</span></Link></div><div className="category-grid">{categories.map(([key, label], index) => <Link key={key} to="/jobs" className="category-card"><span>{String(index + 1).padStart(2, '0')}</span>{t(key, label)}</Link>)}</div></section>
    <section className="trust-row"><div><span>01</span><b>{t('home.focused_profiles', 'Focused profiles')}</b><small>{t('home.focused_profiles_detail', 'Show skills, portfolio work, and a clear professional story.')}</small></div><div><span>02</span><b>{t('home.clear_delivery', 'Clear delivery')}</b><small>{t('home.clear_delivery_detail', 'Milestones and project updates make progress easy to follow.')}</small></div><div><span>03</span><b>{t('home.local_context', 'Local context')}</b><small>{t('home.local_context_detail', 'Use TalentXpanse in English or Myanmar as you prefer.')}</small></div></section>
  </>
}

function HowItWorks() {
  const { t } = usePreferences()
  return <section className="how-page"><header><p className="eyebrow">{t('how.eyebrow', 'How TalentXpanse works')}</p><h1>{t('how.title', 'A clear process for better freelance work.')}</h1><p>{t('how.intro', 'Start with the role you need today. If you later need the other workspace, you can add it to the same account.')}</p></header><div className="how-grid"><article><p className="eyebrow">{t('how.for_freelancers', 'For freelancers')}</p><h2>{t('how.freelancer_title', 'Find and deliver great work.')}</h2><ol><li>{t('how.freelancer_step_1', 'Create a focused profile, portfolio, and optional CV.')}</li><li>{t('how.freelancer_step_2', 'Search jobs and spend Proposal Credits only on work you want.')}</li><li>{t('how.freelancer_step_3', 'Message clients, agree milestones, submit work, and build your review history.')}</li></ol><Link className="button button-primary" to="/register?role=freelancer">{t('how.create_freelancer', 'Create a freelancer account')}</Link></article><article><p className="eyebrow">{t('how.for_clients', 'For clients')}</p><h2>{t('how.client_title', 'Hire with confidence.')}</h2><ol><li>{t('how.client_step_1', 'Create a company workspace and post a clear job.')}</li><li>{t('how.client_step_2', 'Review proposals, portfolios, and trust history before you hire.')}</li><li>{t('how.client_step_3', 'Manage milestones, approve delivery, and leave a fair review.')}</li></ol><Link className="button button-outline" to="/register?role=client">{t('how.create_client', 'Create a client account')}</Link></article></div><section className="how-note"><b>{t('how.payment_notice', 'Payments are not yet live.')}</b><span>{t('how.payment_detail', 'TalentXpanse currently supports project delivery tracking. Secure payment collection and payouts will be added through a compliant payment partner later.')}</span></section><div className="guest-inline-actions"><Link to="/jobs">{t('how.browse_work', 'Browse open work')}</Link><Link to="/login">{t('how.already_account', 'Already have an account? Log in')}</Link></div></section>
}

function AboutPage() {
  const { t } = usePreferences()
  return <section className="guest-info-page"><header><p className="eyebrow">{t('about.eyebrow', 'About TalentXpanse')}</p><h1>{t('about.title', 'Built for ambitious work with a clear process.')}</h1><p>{t('about.intro', 'TalentXpanse is a Myanmar-first freelance marketplace designed around clarity, trust, and real project delivery.')}</p></header><div className="guest-info-grid"><article><span>01</span><h2>{t('about.expectations', 'Clear expectations')}</h2><p>{t('about.expectations_detail', 'People should understand the work, the proposal, and the next step before they commit.')}</p></article><article><span>02</span><h2>{t('about.practical', 'Practical workflow')}</h2><p>{t('about.practical_detail', 'Profiles, proposals, messages, milestones, and reviews support the full working relationship.')}</p></article><article><span>03</span><h2>{t('about.context', 'Local context')}</h2><p>{t('about.context_detail', 'Myanmar talent deserves a professional place to build experience and work with businesses near and far.')}</p></article></div><div className="guest-page-actions"><Link className="button button-primary" to="/jobs">{t('about.explore', 'Explore opportunities')}</Link><Link className="button button-outline" to="/how-it-works">{t('about.see_how', 'See how it works')}</Link></div></section>
}

function ContactPage() {
  const { t } = usePreferences()
  return <section className="guest-info-page contact-page"><header><p className="eyebrow">{t('contact.eyebrow', 'Contact and support')}</p><h1>{t('contact.title', 'Support that respects your work.')}</h1><p>{t('contact.intro', 'We are preparing a dedicated support experience alongside live payments and account-verification processes.')}</p></header><div className="contact-expectation"><div><b>{t('contact.today', 'What you can do today')}</b><p>{t('contact.today_detail', 'Create an account to explore the marketplace, build a profile, post a job, or follow an active project.')}</p></div><div><b>{t('contact.next', 'What comes next')}</b><p>{t('contact.next_detail', 'In-product support, payment assistance, and account-verification help will launch with the relevant live features.')}</p></div></div><div className="guest-page-actions"><Link className="button button-primary" to="/register">{t('contact.create_account', 'Create an account')}</Link><Link className="button button-outline" to="/how-it-works">{t('contact.learn_workflow', 'Learn the workflow')}</Link></div><div className="guest-inline-actions"><Link to="/terms">{t('policy.terms_title', 'Terms of use')}</Link><Link to="/privacy">{t('policy.privacy_title', 'Privacy')}</Link><Link to="/marketplace-rules">{t('policy.rules_title', 'Marketplace rules')}</Link></div></section>
}

function PolicyPage({ type }) {
  const { t } = usePreferences()
  const contents = {
    Terms: ['policy.terms_title', 'Terms of use', 'policy.terms_intro', 'Clear rules help TalentXpanse remain fair for clients and freelancers. Current version: 30 July 2026.', ['policy.terms_1', 'Use TalentXpanse honestly and keep account information accurate.'], ['policy.terms_2', 'Keep proposals, project scope, delivery, and payments on the platform when features are available.'], ['policy.terms_3', 'Do not post illegal, misleading, discriminatory, or abusive content.'], ['policy.terms_4', 'TalentXpanse may restrict content or accounts that break marketplace rules.']],
    Privacy: ['policy.privacy_title', 'Privacy', 'policy.privacy_intro', 'We collect only the data needed to operate a marketplace safely and improve the service. Current version: 30 July 2026.', ['policy.privacy_1', 'Your profile and portfolio details may be visible where you choose to make them public.'], ['policy.privacy_2', 'Private messages, delivery files, account records, and verification requests are handled as restricted marketplace data.'], ['policy.privacy_3', 'You can request help with your account data or account closure through support. Closure must preserve active-project, dispute, and legally required payment records.'], ['policy.privacy_4', 'Payment and identity data will receive additional safeguards before those services launch.']],
    MarketplaceRules: ['policy.rules_title', 'Marketplace rules', 'policy.rules_intro', 'Professional, documented collaboration protects both sides of a project. Current version: 30 July 2026.', ['policy.rules_1', 'Write clear job posts and proposals. Do not misrepresent skills, experience, work, or identity.'], ['policy.rules_2', 'Use milestones, documented revisions, scope changes, and support requests to resolve project issues.'], ['policy.rules_3', 'Do not ask another person to make an off-platform payment or share sensitive verification documents through chat.']],
  }[type]
  const [titleKey, titleFallback, introKey, introFallback, ...items] = contents
  return <section className="guest-info-page policy-page"><header><p className="eyebrow">{t('policy.eyebrow', 'TalentXpanse policies')}</p><h1>{t(titleKey, titleFallback)}</h1><p>{t(introKey, introFallback)}</p></header><ol className="policy-list">{items.map(([key, fallback], index) => <li key={key}><span>{String(index + 1).padStart(2, '0')}</span><p>{t(key, fallback)}</p></li>)}</ol><div className="guest-page-actions"><Link className="button button-primary" to="/how-it-works">{t('policy.see_marketplace', 'See how the marketplace works')}</Link><Link className="button button-outline" to="/contact">{t('policy.contact', 'Contact and support')}</Link></div></section>
}

export default function Page({ name }) {
  if (name === 'Home') return <HomePage />
  if (name === 'HowItWorks') return <HowItWorks />
  if (['Terms', 'Privacy', 'MarketplaceRules'].includes(name)) return <PolicyPage type={name} />
  return name === 'About' ? <AboutPage /> : <ContactPage />
}
