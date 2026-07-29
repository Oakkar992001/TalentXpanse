import { Link } from 'react-router-dom'
import { usePreferences } from '../contexts/PreferencesContext'
import '../public-pages.css'

const categories = [
  'Development & IT',
  'Design & Creative',
  'Sales & Marketing',
  'Writing & Translation',
  'Admin Support',
  'Finance & Accounting',
]

function HomePage() {
  const { language } = usePreferences()
  const isMyanmar = language === 'my'
  const copy = isMyanmar
    ? {
      eyebrow: 'မြန်မာ ဖရီးလန်းဈေးကွက်',
      title: 'အရည်အချင်းနဲ့ အခွင့်အလမ်းကို ချိတ်ဆက်ပါ။',
      body: 'TalentXpanse သည် လုပ်ငန်းများနှင့် ကျွမ်းကျင်သော မြန်မာပညာရှင်များကို ရှင်းလင်းပြီး ယုံကြည်ရသော ပရောဂျက်လုပ်ငန်းစဉ်ဖြင့် ချိတ်ဆက်ပေးသည်။',
      find: 'အလုပ်များ ရှာဖွေပါ',
      hire: 'ပညာရှင်များ ငှားရမ်းပါ',
      explore: 'အခွင့်အလမ်းများကို လေ့လာပါ',
      heading: 'သင့်ကျွမ်းကျင်မှုနဲ့ ကိုက်ညီသောအလုပ်များ',
      how: 'လုပ်ဆောင်ပုံကို ကြည့်ပါ',
    }
    : {
      eyebrow: 'Myanmar freelance marketplace',
      title: 'Myanmar talent. Meaningful opportunity.',
      body: 'TalentXpanse connects businesses with skilled Myanmar professionals through a clear, trustworthy project workflow.',
      find: 'Find freelance jobs',
      hire: 'Hire freelancers',
      explore: 'Explore the marketplace',
      heading: 'Work that matches your expertise',
      how: 'Understand the workflow',
    }

  return <>
    <section className="home-hero">
      <div className="hero-copy">
        <p className="eyebrow">{copy.eyebrow}</p>
        <h1>{copy.title}</h1>
        <p>{copy.body}</p>
        <div className="hero-actions">
          <Link to="/jobs" className="button button-primary">{copy.explore}</Link>
          <Link to="/how-it-works" className="button button-outline">{copy.how}</Link>
        </div>
        <p className="hero-assurance"><span>01</span> Explore real opportunities and the project process before creating a workspace.</p>
      </div>
      <div className="hero-art" aria-label="A simple freelance project workflow">
        <div className="orb orb-one" />
        <div className="orb orb-two" />
        <div className="profile-float">
          <span className="float-icon">01</span>
          <div><b>Discover the right fit</b><small>Explore jobs and professional profiles.</small></div>
        </div>
        <div className="project-float">
          <small>PROJECT WORKFLOW</small>
          <b>Plan with confidence</b>
          <div className="workflow-pills"><span>Scope</span><span>Milestones</span><span>Delivery</span></div>
        </div>
        <div className="hero-person" aria-hidden="true">TX</div>
      </div>
    </section>

    <section className="guest-workflow">
      <div>
        <p className="eyebrow">Built for practical collaboration</p>
        <h2>From first search to completed work.</h2>
      </div>
      <ol>
        <li><span>1</span><div><b>Find a fit</b><small>Browse opportunities or discover talent by specialty.</small></div></li>
        <li><span>2</span><div><b>Agree clearly</b><small>Use proposals, messages, and milestones to set expectations.</small></div></li>
        <li><span>3</span><div><b>Deliver well</b><small>Keep each project organised and build a trusted work history.</small></div></li>
      </ol>
    </section>

    <section className="category-section">
      <div className="section-heading">
        <div><p className="eyebrow">{copy.explore}</p><h2>{copy.heading}</h2></div>
        <Link to="/jobs">Browse current jobs <span aria-hidden="true">-&gt;</span></Link>
      </div>
      <div className="category-grid">
        {categories.map((label, index) => <Link key={label} to="/jobs" className="category-card"><span>{String(index + 1).padStart(2, '0')}</span>{label}</Link>)}
      </div>
    </section>

    <section className="trust-row">
      <div><span>01</span><b>Focused profiles</b><small>Show skills, portfolio work, and a clear professional story.</small></div>
      <div><span>02</span><b>Clear delivery</b><small>Milestones and project updates make progress easy to follow.</small></div>
      <div><span>03</span><b>Local context</b><small>Use TalentXpanse in English or Myanmar as you prefer.</small></div>
    </section>
  </>
}

function HowItWorks() {
  return <section className="how-page">
    <header><p className="eyebrow">How TalentXpanse works</p><h1>A clear process for better freelance work.</h1><p>Start with the role you need today. If you later need the other workspace, you can add it to the same account.</p></header>
    <div className="how-grid">
      <article><p className="eyebrow">For freelancers</p><h2>Find and deliver great work.</h2><ol><li>Create a focused profile, portfolio, and optional CV.</li><li>Search jobs and spend Proposal Credits only on work you want.</li><li>Message clients, agree milestones, submit work, and build your review history.</li></ol><Link className="button button-primary" to="/register?role=freelancer">Create a freelancer account</Link></article>
      <article><p className="eyebrow">For clients</p><h2>Hire with confidence.</h2><ol><li>Create a company workspace and post a clear job.</li><li>Review proposals, portfolios, and trust history before you hire.</li><li>Manage milestones, approve delivery, and leave a fair review.</li></ol><Link className="button button-outline" to="/register?role=client">Create a client account</Link></article>
    </div>
    <section className="how-note"><b>Payments are not yet live.</b><span>TalentXpanse currently supports project delivery tracking. Secure payment collection and payouts will be added through a compliant payment partner later.</span></section>
    <div className="guest-inline-actions"><Link to="/jobs">Browse open work</Link><Link to="/login">Already have an account? Log in</Link></div>
  </section>
}

function AboutPage() {
  return <section className="guest-info-page">
    <header><p className="eyebrow">About TalentXpanse</p><h1>Built for ambitious work with a clear process.</h1><p>TalentXpanse is a Myanmar-first freelance marketplace designed around clarity, trust, and real project delivery.</p></header>
    <div className="guest-info-grid">
      <article><span>01</span><h2>Clear expectations</h2><p>People should understand the work, the proposal, and the next step before they commit.</p></article>
      <article><span>02</span><h2>Practical workflow</h2><p>Profiles, proposals, messages, milestones, and reviews support the full working relationship.</p></article>
      <article><span>03</span><h2>Local context</h2><p>Myanmar talent deserves a professional place to build experience and work with businesses near and far.</p></article>
    </div>
    <div className="guest-page-actions"><Link className="button button-primary" to="/jobs">Explore opportunities</Link><Link className="button button-outline" to="/how-it-works">See how it works</Link></div>
  </section>
}

function ContactPage() {
  return <section className="guest-info-page contact-page">
    <header><p className="eyebrow">Contact and support</p><h1>Support that respects your work.</h1><p>We are preparing a dedicated support experience alongside live payments and account-verification processes.</p></header>
    <div className="contact-expectation"><div><b>What you can do today</b><p>Create an account to explore the marketplace, build a profile, post a job, or follow an active project.</p></div><div><b>What comes next</b><p>In-product support, payment assistance, and account-verification help will launch with the relevant live features.</p></div></div>
    <div className="guest-page-actions"><Link className="button button-primary" to="/register">Create an account</Link><Link className="button button-outline" to="/how-it-works">Learn the workflow</Link></div><div className="guest-inline-actions"><Link to="/terms">Terms of use</Link><Link to="/privacy">Privacy</Link><Link to="/marketplace-rules">Marketplace rules</Link></div>
  </section>
}

function PolicyPage({ type }) {
  const content = {
    Terms: ['Terms of use', 'Clear rules help TalentXpanse remain fair for clients and freelancers.', ['Use TalentXpanse honestly and keep account information accurate.', 'Keep proposals, project scope, delivery, and payments on the platform when features are available.', 'Do not post illegal, misleading, discriminatory, or abusive content.', 'TalentXpanse may restrict content or accounts that break marketplace rules.']],
    Privacy: ['Privacy', 'We collect only the data needed to operate a marketplace safely and improve the service.', ['Your profile and portfolio details may be visible where you choose to make them public.', 'Private messages, delivery files, account records, and verification requests are handled as restricted marketplace data.', 'Payment and identity data will receive additional safeguards before those services launch.']],
    MarketplaceRules: ['Marketplace rules', 'Professional, documented collaboration protects both sides of a project.', ['Write clear job posts and proposals. Do not misrepresent skills, experience, work, or identity.', 'Use milestones, documented revisions, scope changes, and support requests to resolve project issues.', 'Do not ask another person to make an off-platform payment or share sensitive verification documents through chat.']],
  }[type]

  return <section className="guest-info-page policy-page"><header><p className="eyebrow">TalentXpanse policies</p><h1>{content[0]}</h1><p>{content[1]}</p></header><ol className="policy-list">{content[2].map((item, index) => <li key={item}><span>{String(index + 1).padStart(2, '0')}</span><p>{item}</p></li>)}</ol><div className="guest-page-actions"><Link className="button button-primary" to="/how-it-works">See how the marketplace works</Link><Link className="button button-outline" to="/contact">Contact and support</Link></div></section>
}

export default function Page({ name }) {
  if (name === 'Home') return <HomePage />
  if (name === 'HowItWorks') return <HowItWorks />
  if (['Terms', 'Privacy', 'MarketplaceRules'].includes(name)) return <PolicyPage type={name} />
  return name === 'About' ? <AboutPage /> : <ContactPage />
}
