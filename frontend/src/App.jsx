import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { PreferencesProvider } from './contexts/PreferencesContext'
import { AuthProvider } from './contexts/AuthContext'
import AppLayout from './layouts/AppLayout'
import Page from './pages/Page'
import { AuthScreen, DashboardScreen, JobDetailScreen, JobsScreen } from './pages/Marketplace'
function App() {
  const pages = [['/', 'Home'], ['/profile', 'Profile'], ['/about', 'About'], ['/contact', 'Contact']]

  return <PreferencesProvider><AuthProvider><BrowserRouter><Routes>
    <Route element={<AppLayout />}>
      {pages.map(([path, name]) => <Route key={path} path={path} element={<Page name={name} />} />)}
      <Route path="/login" element={<AuthScreen mode="login" />} />
      <Route path="/register" element={<AuthScreen mode="register" />} />
      <Route path="/jobs" element={<JobsScreen />} />
      <Route path="/jobs/:id" element={<JobDetailScreen />} />
      <Route path="/dashboard" element={<DashboardScreen />} />
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></BrowserRouter></AuthProvider></PreferencesProvider>
}
export default App
