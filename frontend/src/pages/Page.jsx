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
  const steps = [
    ['01', 'how.step_one_title', 'Choose your starting point', 'how.step_one_detail', 'Create one account and begin as a freelancer or client. Add the other workspace later when you need it.'],
    ['02', 'how.step_two_title', 'Find the right fit', 'how.step_two_detail', 'Search real job posts or professional profiles, then focus only on work and people that fit your goals.'],
    ['03', 'how.step_three_title', 'Make a clear decision', 'how.step_three_detail', 'Use proposals, portfolios, CVs, messages, and formal offers to agree on the work before a project begins.'],
    ['04', 'how.step_four_title', 'Deliver with a shared record', 'how.step_four_detail', 'Manage milestones, delivery, revisions, project updates, and reviews in one organised workspace.'],
  ]
  const confidencePoints = [
    ['how.confidence_one_title', 'Professional profiles', 'how.confidence_one_detail', 'Skills, portfolio work, CVs, and experience give people useful context before they connect.'],
    ['how.confidence_two_title', 'Project clarity', 'how.confidence_two_detail', 'Milestones, delivery records, revision requests, and scope changes keep important decisions visible.'],
    ['how.confidence_three_title', 'Accountability tools', 'how.confidence_three_detail', 'Verification requests, reports, private reviews, and support paths help keep the marketplace professional.'],
  ]

  return <section className="how-page">
    <section className="how-hero">
      <div>
        <p className="eyebrow">{t('how.eyebrow', 'How TalentXpanse works')}</p>
        <h1>{t('how.title', 'From the first conversation to finished work, every step is clear.')}</h1>
        <p>{t('how.intro', 'TalentXpanse gives clients and freelancers one practical place to discover a fit, agree on the work, and keep a project moving forward.')}</p>
        <div className="how-hero-actions">
          <Link className="button button-primary" to="/register">{t('how.create_account', 'Create your account')}</Link>
          <Link className="button button-outline" to="/jobs">{t('how.explore_jobs', 'Explore the marketplace')}</Link>
        </div>
        <p className="how-account-note"><span aria-hidden="true">✓</span>{t('how.account_note', 'Start with one workspace. Add client or freelancer capabilities to the same sign-in whenever you are ready.')}</p>
      </div>
      <div className="how-journey-card" aria-label={t('how.journey_label', 'TalentXpanse project journey')}>
        <p>{t('how.journey_eyebrow', 'YOUR WORK JOURNEY')}</p>
        <ol>
          {steps.map(([number, titleKey, titleFallback]) => <li key={number}><span>{number}</span><b>{t(titleKey, titleFallback)}</b></li>)}
        </ol>
        <div><span>{t('how.journey_footer', 'A shared workflow for serious work.')}</span><b>TX</b></div>
      </div>
    </section>

    <section className="how-flow-section">
      <div className="how-section-heading"><p className="eyebrow">{t('how.flow_eyebrow', 'The full workflow')}</p><h2>{t('how.flow_title', 'A simple path, without guessing what happens next.')}</h2><p>{t('how.flow_intro', 'Each stage gives both people the information and controls they need before moving forward.')}</p></div>
      <ol className="how-steps">
        {steps.map(([number, titleKey, titleFallback, detailKey, detailFallback]) => <li key={number}><span>{number}</span><div><h3>{t(titleKey, titleFallback)}</h3><p>{t(detailKey, detailFallback)}</p></div></li>)}
      </ol>
    </section>

    <section className="how-role-section">
      <div className="how-section-heading"><p className="eyebrow">{t('how.role_eyebrow', 'Built for both sides')}</p><h2>{t('how.role_title', 'Choose the experience you need today.')}</h2></div>
      <div className="how-role-grid">
        <article className="how-role-card freelancer">
          <span className="how-role-icon" aria-hidden="true">F</span><p className="eyebrow">{t('how.for_freelancers', 'For freelancers')}</p><h3>{t('how.freelancer_title', 'Find and deliver great work.')}</h3>
          <ol><li>{t('how.freelancer_step_1', 'Create a focused profile, portfolio, and optional CV.')}</li><li>{t('how.freelancer_step_2', 'Search jobs and spend Proposal Credits only on work you want.')}</li><li>{t('how.freelancer_step_3', 'Message clients, agree milestones, submit work, and build your review history.')}</li></ol>
          <Link className="button button-primary" to="/register?role=freelancer">{t('how.create_freelancer', 'Create freelancer account')}</Link>
        </article>
        <article className="how-role-card client">
          <span className="how-role-icon" aria-hidden="true">C</span><p className="eyebrow">{t('how.for_clients', 'For clients')}</p><h3>{t('how.client_title', 'Hire with confidence.')}</h3>
          <ol><li>{t('how.client_step_1', 'Create a client workspace and post a clear job.')}</li><li>{t('how.client_step_2', 'Review proposals, portfolios, and trust history before you hire.')}</li><li>{t('how.client_step_3', 'Manage milestones, approve delivery, and leave a fair review.')}</li></ol>
          <Link className="button button-outline" to="/register?role=client">{t('how.create_client', 'Create client account')}</Link>
        </article>
      </div>
    </section>

    <section className="how-confidence-section">
      <div className="how-section-heading"><p className="eyebrow">{t('how.confidence_eyebrow', 'Work with confidence')}</p><h2>{t('how.confidence_title', 'A marketplace is more than a list of jobs.')}</h2></div>
      <div className="how-confidence-grid">
        {confidencePoints.map(([titleKey, titleFallback, detailKey, detailFallback], index) => <article key={titleKey}><span>{String(index + 1).padStart(2, '0')}</span><h3>{t(titleKey, titleFallback)}</h3><p>{t(detailKey, detailFallback)}</p></article>)}
      </div>
    </section>

    <section className="how-final-cta">
      <div><p className="eyebrow">{t('how.final_eyebrow', 'Ready when you are')}</p><h2>{t('how.final_title', 'Build better working relationships from the first step.')}</h2><p>{t('how.final_detail', 'Create a focused workspace, explore the marketplace, and make your next project easier to manage.')}</p></div>
      <div><Link className="button button-primary" to="/register">{t('how.create_account', 'Create your account')}</Link><Link to="/login">{t('how.already_account', 'Already have an account? Log in')}</Link></div>
    </section>
  </section>
}

function AboutPage() {
  const { t } = usePreferences()
  const principles = [
    ['01', 'about.expectations', 'Clear expectations', 'about.expectations_detail', 'People should understand the work, the proposal, and the next step before they commit.'],
    ['02', 'about.practical', 'Practical workflow', 'about.practical_detail', 'Profiles, proposals, messages, milestones, and reviews support the full working relationship.'],
    ['03', 'about.context', 'Local context', 'about.context_detail', 'Myanmar talent deserves a professional place to build experience and work with businesses near and far.'],
    ['04', 'about.accountability', 'Thoughtful accountability', 'about.accountability_detail', 'Verification, reports, support requests, and documented project decisions make professional conduct easier to maintain.'],
  ]

  return <section className="guest-info-page why-page">
    <section className="why-hero">
      <div><p className="eyebrow">{t('about.eyebrow', 'Why TalentXpanse')}</p><h1>{t('about.title', 'Great freelance work deserves a clearer way to start and finish.')}</h1><p>{t('about.intro', 'TalentXpanse is a Myanmar-first freelance marketplace built to make professional collaboration feel more focused, fair, and organised.')}</p><div className="guest-page-actions"><Link className="button button-primary" to="/register">{t('about.join', 'Join TalentXpanse')}</Link><Link className="button button-outline" to="/how-it-works">{t('about.see_how', 'See how it works')}</Link></div></div>
      <aside><span>TX</span><p>{t('about.hero_quote', 'Less uncertainty. More meaningful work.')}</p><small>{t('about.hero_detail', 'A practical home for discovering talent, making decisions, and keeping projects on track.')}</small></aside>
    </section>

    <section className="why-statement"><p className="eyebrow">{t('about.statement_eyebrow', 'What we are building for')}</p><h2>{t('about.statement_title', 'Not just introductions—better working relationships.')}</h2><p>{t('about.statement_detail', 'A job post is only the beginning. TalentXpanse brings the important moments around it into one connected experience: professional profiles, informed proposals, clear decisions, shared project progress, and a record people can rely on.')}</p></section>

    <section className="why-principles"><div className="how-section-heading"><p className="eyebrow">{t('about.principles_eyebrow', 'The TalentXpanse standard')}</p><h2>{t('about.principles_title', 'Designed around the details that make work feel professional.')}</h2></div><div className="why-principles-grid">{principles.map(([number, titleKey, titleFallback, detailKey, detailFallback]) => <article key={number}><span>{number}</span><h3>{t(titleKey, titleFallback)}</h3><p>{t(detailKey, detailFallback)}</p></article>)}</div></section>

    <section className="why-commitment"><div><p className="eyebrow">{t('about.commitment_eyebrow', 'For Myanmar, and beyond')}</p><h2>{t('about.commitment_title', 'Your skills should have room to grow.')}</h2><p>{t('about.commitment_detail', 'Whether you are building a freelance career, hiring for a growing business, or doing both, TalentXpanse helps you work with more context and less friction—in English or Myanmar.')}</p></div><div><Link className="button button-primary" to="/register">{t('about.join', 'Join TalentXpanse')}</Link><Link className="button button-outline" to="/jobs">{t('about.explore', 'Explore opportunities')}</Link></div></section>
  </section>
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
