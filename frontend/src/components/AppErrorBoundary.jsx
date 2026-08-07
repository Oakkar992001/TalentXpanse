import { Component } from 'react'

export default class AppErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false }
  }

  static getDerivedStateFromError() {
    return { hasError: true }
  }

  render() {
    if (!this.state.hasError) return this.props.children

    return <main className="app-error" role="alert" aria-live="assertive">
      <section className="app-error-card">
        <p className="eyebrow">Something needs attention</p>
        <h1>We could not load this part of TalentXpanse.</h1>
        <p>Your work has not been changed. Try loading the page again, or return to the marketplace.</p>
        <div>
          <button type="button" className="button button-primary" onClick={() => window.location.reload()}>Try again</button>
          <a className="button button-outline" href="/">Go to home</a>
        </div>
      </section>
    </main>
  }
}
