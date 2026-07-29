import { createContext, useContext, useEffect, useState } from 'react'

const PreferencesContext = createContext(null)

const translations = {
  my: {
    'nav.find_jobs': 'အလုပ်များ ရှာဖွေရန်',
    'nav.hire': 'ကျွမ်းကျင်သူ ရှာဖွေရန်',
    'nav.how_it_works': 'အသုံးပြုပုံ',
    'nav.open_workspace': 'အလုပ်ခွင် ဖွင့်ရန်',
    'nav.logout': 'ထွက်ရန်',
    'nav.login': 'ဝင်ရန်',
    'nav.signup': 'အကောင့်ဖွင့်ရန်',
    'nav.overview': 'အနှစ်ချုပ်',
    'nav.messages': 'စာတိုများ',
    'nav.projects': 'ပရောဂျက်များ',
    'nav.profile': 'ကိုယ်ရေးအကျဉ်း',
    'nav.search': 'ရှာဖွေရန်',
    'nav.notifications': 'အသိပေးချက်များ',
    'nav.settings': 'ဆက်တင်များ',
    'theme.light': 'အလင်း',
    'theme.dark': 'အမှောင်',
    'workspace.client': 'အလုပ်ရှင် အလုပ်ခွင်',
    'workspace.freelancer': 'ဖရီးလန်ဆာ အလုပ်ခွင်',
    'workspace.client_subtitle': 'အလုပ်ခန့်ခြင်းနှင့် လုပ်ငန်းပို့ဆောင်မှုကို တစ်နေရာတည်းမှ စီမံပါ။',
    'workspace.freelancer_subtitle': 'အလုပ်အခွင့်အလမ်းများကို ရှာဖွေပြီး ပရောဂျက်များကို ဆက်လက်ရွှေ့ပါ။',
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
