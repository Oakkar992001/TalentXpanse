import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { PreferencesProvider } from './contexts/PreferencesContext'
import { AuthProvider } from './contexts/AuthContext'
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
import { AdminDashboardScreen, AdminLoginScreen } from './pages/Admin'
function App() {
  const pages = [['/', 'Home'], ['/how-it-works', 'HowItWorks'], ['/about', 'About'], ['/contact', 'Contact']]

  return <PreferencesProvider><AuthProvider><BrowserRouter><Routes>
    <Route path="/admin/login" element={<AdminLoginScreen />} />
    <Route path="/admin" element={<AdminDashboardScreen />} />
    <Route element={<AppLayout />}>
      {pages.map(([path, name]) => <Route key={path} path={path} element={<Page name={name} />} />)}
      <Route path="/login" element={<AuthScreen mode="login" />} />
      <Route path="/register" element={<AuthScreen mode="register" />} />
      <Route path="/forgot-password" element={<PasswordRecoveryScreen />} />
      <Route path="/reset-password" element={<PasswordRecoveryScreen reset />} />
      <Route path="/jobs" element={<JobsScreen />} />
      <Route path="/jobs/:id" element={<JobDetailScreen />} />
      <Route path="/profile" element={<ProfileScreen />} />
      <Route path="/workspace-setup" element={<WorkspaceSetupScreen />} />
      <Route path="/messages" element={<MessagesScreen />} />
      <Route path="/notifications" element={<NotificationsScreen />} />
      <Route path="/freelancers/:id" element={<FreelancerPublicProfile />} />
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
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></BrowserRouter></AuthProvider></PreferencesProvider>
}
export default App
