import { createContext, useCallback, useContext, useState } from 'react'
import ConfirmDialog from '../components/ConfirmDialog'

const ConfirmContext = createContext(null)

export function ConfirmProvider({ children }) {
  const [confirmation, setConfirmation] = useState(null)

  const confirm = useCallback((options) => new Promise((resolve) => {
    setConfirmation({ ...options, resolve })
  }), [])

  const settle = useCallback((confirmed) => {
    const resolve = confirmation?.resolve
    setConfirmation(null)
    resolve?.(confirmed)
  }, [confirmation])

  return <ConfirmContext.Provider value={confirm}>
    {children}
    <ConfirmDialog confirmation={confirmation} onCancel={() => settle(false)} onConfirm={() => settle(true)} />
  </ConfirmContext.Provider>
}

export function useConfirmation() {
  const confirm = useContext(ConfirmContext)
  if (!confirm) throw new Error('useConfirmation must be used inside ConfirmProvider.')
  return confirm
}
