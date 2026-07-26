import { createContext, useContext, useEffect, useState } from 'react'

const PreferencesContext = createContext(null)

export function PreferencesProvider({ children }) {
  const [theme, setTheme] = useState(() => localStorage.getItem('tx-theme') || 'light')
  const [language, setLanguage] = useState(() => localStorage.getItem('tx-language') || 'en')

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('tx-theme', theme)
  }, [theme])

  useEffect(() => localStorage.setItem('tx-language', language), [language])
  useEffect(() => { document.documentElement.lang = language === 'my' ? 'my' : 'en' }, [language])

  return <PreferencesContext.Provider value={{
    theme, language,
    toggleTheme: () => setTheme(value => value === 'light' ? 'dark' : 'light'),
    toggleLanguage: () => setLanguage(value => value === 'en' ? 'my' : 'en'),
  }}>{children}</PreferencesContext.Provider>
}

export const usePreferences = () => useContext(PreferencesContext)
