import { useContext } from 'react'
import { NotificationContext } from '../contexts/notification-store'

export const useNotifications = () => useContext(NotificationContext)
