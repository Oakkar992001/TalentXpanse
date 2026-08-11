import { createContext, useContext, useEffect, useState } from 'react'
import { myTranslations } from '../i18n/translations'

const PreferencesContext = createContext(null)
const translations = { my: myTranslations }

const interpolate = (text, values) => String(text).replace(/{{(.*?)}}/g, (_, key) => values?.[key.trim()] ?? '')

export function PreferencesProvider({ children }) {
  const [theme, setTheme] = useState(() => localStorage.getItem('tx-theme') || 'light')
  const [language, setLanguage] = useState(() => localStorage.getItem('tx-language') || 'en')
  const locale = language === 'my' ? 'my-MM' : 'en-US'

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('tx-theme', theme)
  }, [theme])
  useEffect(() => localStorage.setItem('tx-language', language), [language])
  useEffect(() => { document.documentElement.lang = language === 'my' ? 'my' : 'en' }, [language])

  const translate = (key, fallback = key, values = {}) => interpolate(translations[language]?.[key] || fallback, values)

  return <PreferencesContext.Provider value={{
    theme,
    language,
    locale,
    t: translate,
    formatDate: (value, options) => new Intl.DateTimeFormat(locale, options).format(new Date(value)),
    formatNumber: (value, options) => new Intl.NumberFormat(locale, options).format(value),
    setLanguage,
    toggleTheme: () => setTheme((value) => value === 'light' ? 'dark' : 'light'),
    toggleLanguage: () => setLanguage((value) => value === 'en' ? 'my' : 'en'),
  }}>{children}</PreferencesContext.Provider>
}

export const usePreferences = () => useContext(PreferencesContext)
