import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom'
import { PreferencesProvider } from './contexts/PreferencesContext'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import AppLayout from './layouts/AppLayout'
import Page from './pages/Page'
import { AuthScreen, DashboardScreen, JobDetailScreen, JobsScreen } from './pages/Marketplace'
import ProfileScreen from './pages/Profile'
import WorkspaceSetupScreen from './pages/WorkspaceSetup'
import MessagesScreen from './pages/Messages'
import { ProjectDetailScreen, ProjectsScreen } from './pages/Projects'
import NotificationsScreen from './pages/Notifications'
import FreelancerPublicProfile from './pages/FreelancerPublicProfile'
import SettingsScreen from './pages/Settings'
import PasswordRecoveryScreen from './pages/PasswordRecovery'
import SearchResultsScreen from './pages/WorkspaceDiscovery'
import WorkManagementScreen from './pages/WorkManagement'
import { AdminDashboardScreen, AdminLoginScreen } from './pages/Admin'

function GuestOnly({ children, redirectTo }) {
  const { user, loading } = useAuth()

  if (loading) return <main className="route-loading" aria-live="polite">Loading your workspace...</main>
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

function App() {
  const pages = [['/', 'Home'], ['/how-it-works', 'HowItWorks'], ['/about', 'About'], ['/contact', 'Contact']]

  return <PreferencesProvider><AuthProvider><BrowserRouter><Routes>
    <Route path="/admin/login" element={<AdminLoginScreen />} />
    <Route path="/admin" element={<AdminDashboardScreen />} />
    <Route element={<AppLayout />}>
      {pages.map(([path, name]) => <Route key={path} path={path} element={<GuestOnly><Page name={name} /></GuestOnly>} />)}
      <Route path="/login" element={<GuestOnly><AuthScreen mode="login" /></GuestOnly>} />
      <Route path="/register" element={<GuestOnly><AuthScreen mode="register" /></GuestOnly>} />
      <Route path="/forgot-password" element={<GuestOnly><PasswordRecoveryScreen /></GuestOnly>} />
      <Route path="/reset-password" element={<GuestOnly><PasswordRecoveryScreen reset /></GuestOnly>} />
      <Route path="/jobs" element={<GuestOnly redirectTo="/search?scope=jobs"><JobsScreen /></GuestOnly>} />
      <Route path="/jobs/:id" element={<GuestJobDetail />} />
      <Route path="/profile" element={<ProfileScreen />} />
      <Route path="/workspace-setup" element={<WorkspaceSetupScreen />} />
      <Route path="/messages" element={<MessagesScreen />} />
      <Route path="/notifications" element={<NotificationsScreen />} />
      <Route path="/freelancers/:id" element={<GuestFreelancerProfile />} />
      <Route path="/settings" element={<SettingsScreen />} />
      <Route path="/settings/account" element={<SettingsScreen section="account" />} />
      <Route path="/settings/security" element={<SettingsScreen section="security" />} />
      <Route path="/settings/notifications" element={<SettingsScreen section="notifications" />} />
      <Route path="/settings/credits" element={<SettingsScreen section="credits" />} />
      <Route path="/search" element={<SearchResultsScreen />} />
      <Route path="/search/jobs/:id" element={<JobDetailScreen />} />
      <Route path="/search/freelancers/:id" element={<FreelancerPublicProfile />} />
      <Route path="/projects" element={<ProjectsScreen />} />
      <Route path="/projects/:id" element={<ProjectDetailScreen />} />
      <Route path="/dashboard" element={<DashboardScreen />} />
      <Route path="/work" element={<WorkManagementScreen />} />
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></BrowserRouter></AuthProvider></PreferencesProvider>
}
export default App
