import { createContext, useContext, useEffect, useState } from 'react'

const PreferencesContext = createContext(null)

const translations = {
  my: {
    'nav.find_jobs': 'အလုပ်များရှာရန်',
    'nav.hire': 'ကျွမ်းကျင်သူငှားရန်',
    'nav.how_it_works': 'အသုံးပြုပုံ',
    'nav.open_workspace': 'အလုပ်ခွင်ဖွင့်ရန်',
    'nav.logout': 'အကောင့်မှထွက်ရန်',
    'nav.login': 'အကောင့်ဝင်ရန်',
    'nav.signup': 'အကောင့်ဖွင့်ရန်',
    'nav.overview': 'ခြုံငုံသုံးသပ်ချက်',
    'nav.messages': 'မက်ဆေ့ခ်ျများ',
    'nav.projects': 'ပရောဂျက်များ',
    'nav.profile': 'ကျွန်ုပ်၏ ပရိုဖိုင်',
    'nav.search': 'ရှာဖွေရန်',
    'nav.notifications': 'အသိပေးချက်များ',
    'nav.settings': 'ဆက်တင်များ',
    'theme.light': 'အလင်း',
    'theme.dark': 'အမှောင်',
    'workspace.client': 'အလုပ်အပ်သူ အလုပ်ခွင်',
    'workspace.freelancer': 'အလုပ်ရှာသူ အလုပ်ခွင်',
    'workspace.client_subtitle': 'ငှားရမ်းခြင်းနှင့် ပေးပို့မှုများကို တစ်နေရာတည်းတွင် စီမံပါ။',
    'workspace.freelancer_subtitle': 'အလုပ်များရှာဖွေပြီး ပရောဂျက်များကို ဆက်လက်လုပ်ဆောင်ပါ။',
  },
}

export function PreferencesProvider({ children }) {
  const [theme, setTheme] = useState(() => localStorage.getItem('tx-theme') || 'light')
  const [language, setLanguage] = useState(() => localStorage.getItem('tx-language') || 'en')

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    localStorage.setItem('tx-theme', theme)
  }, [theme])
  useEffect(() => localStorage.setItem('tx-language', language), [language])
  useEffect(() => { document.documentElement.lang = language === 'my' ? 'my' : 'en' }, [language])

  const translate = (key, fallback) => translations[language]?.[key] || fallback

  return <PreferencesContext.Provider value={{
    theme,
    language,
    t: translate,
    toggleTheme: () => setTheme((value) => value === 'light' ? 'dark' : 'light'),
    toggleLanguage: () => setLanguage((value) => value === 'en' ? 'my' : 'en'),
  }}>{children}</PreferencesContext.Provider>
}

export const usePreferences = () => useContext(PreferencesContext)
