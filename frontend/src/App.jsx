import { BrowserRouter, Route, Routes } from 'react-router-dom'
import AppLayout from './layouts/AppLayout'
import Page from './pages/Page'
function App() { const pages = [['/', 'Home'], ['/login', 'Login'], ['/register', 'Register'], ['/jobs', 'Find Jobs'], ['/jobs/:id', 'Job Detail'], ['/dashboard', 'Dashboard'], ['/profile', 'Profile'], ['/about', 'About'], ['/contact', 'Contact']]; return <BrowserRouter><Routes><Route element={<AppLayout />}>{pages.map(([path, name]) => <Route key={path} path={path} element={<Page name={name} />} />)}</Route></Routes></BrowserRouter> }
export default App
