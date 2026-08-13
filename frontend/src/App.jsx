import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Outlet, Route, Routes, useOutletContext, useParams } from 'react-router-dom'
import { PreferencesProvider } from './contexts/PreferencesContext'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import { NotificationProvider } from './contexts/NotificationContext'
import { ConfirmProvider } from './contexts/ConfirmContext'
import { usePreferences } from './contexts/PreferencesContext'
import AppErrorBoundary from './components/AppErrorBoundary'

const AppLayout = lazy(() => import('./layouts/AppLayout'))
const Page = lazy(() => import('./pages/Page'))
const AuthScreen = lazy(() => import('./pages/Marketplace').then((module) => ({ default: module.AuthScreen })))
const JobDetailScreen = lazy(() => import('./pages/Marketplace').then((module) => ({ default: module.JobDetailScreen })))
const JobsScreen = lazy(() => import('./pages/Marketplace').then((module) => ({ default: module.JobsScreen })))
const DashboardScreen = lazy(() => import('./pages/Dashboard'))
const ProfileScreen = lazy(() => import('./pages/Profile'))
const WorkspaceSetupScreen = lazy(() => import('./pages/WorkspaceSetup'))
const MessagesScreen = lazy(() => import('./pages/Messages'))
const ProjectDetailScreen = lazy(() => import('./pages/Projects').then((module) => ({ default: module.ProjectDetailScreen })))
const ProjectsScreen = lazy(() => import('./pages/Projects').then((module) => ({ default: module.ProjectsScreen })))
const NotificationsScreen = lazy(() => import('./pages/Notifications'))
const FreelancerPublicProfile = lazy(() => import('./pages/FreelancerPublicProfile'))
const SettingsScreen = lazy(() => import('./pages/Settings'))
const PasswordRecoveryScreen = lazy(() => import('./pages/PasswordRecovery'))
const SearchResultsScreen = lazy(() => import('./pages/WorkspaceDiscovery'))
const WorkManagementScreen = lazy(() => import('./pages/WorkManagement'))
const ProposalManagerScreen = lazy(() => import('./pages/ProposalManager'))
const AdminDashboardScreen = lazy(() => import('./pages/Admin').then((module) => ({ default: module.AdminDashboardScreen })))
const AdminLoginScreen = lazy(() => import('./pages/Admin').then((module) => ({ default: module.AdminLoginScreen })))

function RouteFallback() {
  const { t } = usePreferences()
  return <main className="route-loading" role="status" aria-live="polite">{t('route.loading_page', 'Loading page...')}</main>
}

function GuestOnly({ children, redirectTo }) {
  const { user, loading } = useAuth()
  const { t } = usePreferences()

  if (loading) return <main className="route-loading" aria-live="polite">{t('route.loading_workspace', 'Loading your workspace...')}</main>
  if (!user) return children

  const destination = typeof redirectTo === 'function'
    ? redirectTo(user)
    : redirectTo || `/dashboard?role=${user.active_role || user.roles?.[0] || 'freelancer'}`

  return <Navigate to={destination} replace />
}

function GuestJobDetail() {
  const { id } = useParams()
  return <GuestOnly redirectTo={`/search/jobs/${id}`}><JobDetailScreen /></GuestOnly>
}

function GuestFreelancerProfile() {
  const { id } = useParams()
  return <GuestOnly redirectTo={`/search/freelancers/${id}`}><FreelancerPublicProfile /></GuestOnly>
}

function RequireAuth() {
  const { user, loading } = useAuth()
  const layoutContext = useOutletContext()
  const { t } = usePreferences()

  if (loading) return <main className="route-loading" aria-live="polite">{t('route.loading_workspace', 'Loading your workspace...')}</main>
  if (!user) return <Navigate to="/login" replace />

  return <Outlet context={layoutContext} />
}

function App() {
  const pages = [['/', 'Home'], ['/how-it-works', 'HowItWorks'], ['/about', 'About'], ['/help', 'Help'], ['/contact', 'Contact']]

  return <PreferencesProvider><AuthProvider><NotificationProvider><ConfirmProvider><BrowserRouter><AppErrorBoundary><Suspense fallback={<RouteFallback />}><Routes>
    <Route path="/admin/login" element={<AdminLoginScreen />} />
    <Route path="/admin" element={<AdminDashboardScreen />} />
    <Route element={<AppLayout />}>
      {pages.map(([path, name]) => <Route key={path} path={path} element={<GuestOnly><Page name={name} /></GuestOnly>} />)}
      <Route path="/terms" element={<Page name="Terms" />} />
      <Route path="/privacy" element={<Page name="Privacy" />} />
      <Route path="/marketplace-rules" element={<Page name="MarketplaceRules" />} />
      <Route path="/login" element={<GuestOnly><AuthScreen mode="login" /></GuestOnly>} />
      <Route path="/register" element={<GuestOnly><AuthScreen mode="register" /></GuestOnly>} />
      <Route path="/forgot-password" element={<GuestOnly><PasswordRecoveryScreen /></GuestOnly>} />
      <Route path="/reset-password" element={<GuestOnly><PasswordRecoveryScreen reset /></GuestOnly>} />
      <Route path="/jobs" element={<GuestOnly redirectTo="/search?scope=jobs"><JobsScreen /></GuestOnly>} />
      <Route path="/jobs/:id" element={<GuestJobDetail />} />
      <Route path="/freelancers/:id" element={<GuestFreelancerProfile />} />
      <Route element={<RequireAuth />}>
      <Route path="/profile" element={<ProfileScreen />} />
      <Route path="/workspace-setup" element={<WorkspaceSetupScreen />} />
      <Route path="/messages" element={<MessagesScreen />} />
      <Route path="/notifications" element={<NotificationsScreen />} />
      <Route path="/freelancers/:id" element={<GuestFreelancerProfile />} />
      <Route path="/settings" element={<SettingsScreen />} />
      <Route path="/settings/account" element={<SettingsScreen section="account" />} />
      <Route path="/settings/security" element={<SettingsScreen section="security" />} />
      <Route path="/settings/verification" element={<SettingsScreen section="verification" />} />
      <Route path="/settings/notifications" element={<SettingsScreen section="notifications" />} />
      <Route path="/settings/credits" element={<SettingsScreen section="credits" />} />
      <Route path="/settings/reliability" element={<SettingsScreen section="reliability" />} />
      <Route path="/search" element={<SearchResultsScreen />} />
      <Route path="/search/jobs/:id" element={<JobDetailScreen />} />
      <Route path="/search/freelancers/:id" element={<FreelancerPublicProfile />} />
      <Route path="/projects" element={<ProjectsScreen />} />
      <Route path="/projects/:id" element={<ProjectDetailScreen />} />
      <Route path="/dashboard" element={<DashboardScreen />} />
      <Route path="/work" element={<WorkManagementScreen />} />
      <Route path="/manage/jobs/:id/proposals" element={<ProposalManagerScreen />} />
      </Route>
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></Suspense></AppErrorBoundary></BrowserRouter></ConfirmProvider></NotificationProvider></AuthProvider></PreferencesProvider>
}
export default App
