import { ref, readonly } from 'vue'

const online = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)

let listenersStarted = false
let onlineHandler = null
let offlineHandler = null

function setOnlineStatus(value) {
  online.value = !!value
}

export function currentOnlineStatus() {
  return online.value
}

export function startNetworkListeners() {
  if (listenersStarted || typeof window === 'undefined') return

  onlineHandler = () => setOnlineStatus(true)
  offlineHandler = () => setOnlineStatus(false)

  window.addEventListener('online', onlineHandler)
  window.addEventListener('offline', offlineHandler)

  listenersStarted = true
}

export function stopNetworkListeners() {
  if (!listenersStarted || typeof window === 'undefined') return

  window.removeEventListener('online', onlineHandler)
  window.removeEventListener('offline', offlineHandler)

  listenersStarted = false
  onlineHandler = null
  offlineHandler = null
}

export const isOnline = readonly(online)

export function useNetworkStatus() {
  startNetworkListeners()

  return {
    isOnline,
    currentOnlineStatus,
  }
}