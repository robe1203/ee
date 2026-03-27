import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { syncAll } from './offline/sync'
import { getPending } from './offline/db'
import { startNetworkListeners, currentOnlineStatus } from './utils/network'
import { saveOfflineSnapshot } from './offline/snapshots'
import OfflineShell from './offline/OfflineShell.vue'

const appName = import.meta.env.VITE_APP_NAME || 'ContaSync'

startNetworkListeners()

function rememberCurrentPage(page) {
  if (!currentOnlineStatus()) return
  if (!page?.url || !page?.component) return

  saveOfflineSnapshot({
    url: page.url,
    component: page.component,
    props: page.props ?? {},
  })
}

function shouldBootOfflineShell() {
  if (typeof window === 'undefined') return false
  return !navigator.onLine && window.location.pathname.startsWith('/app')
}

if (shouldBootOfflineShell()) {
  const el = document.getElementById('app')
  if (el) {
    createApp(OfflineShell).mount(el)
  }
} else {
  createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
      resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
      rememberCurrentPage(props.initialPage)

      return createApp({ render: () => h(App, props) })
        .use(plugin)
        .use(ZiggyVue)
        .mount(el)
    },
    progress: {
      color: '#4B5563',
    },
  })

  if (router && typeof router.on === 'function') {
    router.on('success', (event) => {
      const page = event?.detail?.page
      if (!page) return
      rememberCurrentPage(page)
    })
  }
}

let syncInProgress = false

async function runPendingSync() {
  if (syncInProgress) return
  if (!currentOnlineStatus()) return

  syncInProgress = true

  try {
    const pending = await getPending()
    if (!pending || pending.length === 0) return

    const result = await syncAll()
    const changed = (result?.syncedCount ?? 0) > 0

    if (!changed) return

    const path = window.location.pathname
    const isFormPage = path.includes('/create') || path.includes('/edit')

    if (!isFormPage && typeof router?.reload === 'function') {
      router.reload({ preserveState: true, preserveScroll: true })
    }
  } catch (error) {
    console.error('Error sincronizando pendientes:', error)
  } finally {
    syncInProgress = false
  }
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js')
      console.log('SW registrado:', registration)

      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'CACHE_WARMUP' })
      }

      if (typeof registration.update === 'function') {
        await registration.update()
      }
    } catch (error) {
      console.error('SW error:', error)
    }

    await runPendingSync()
  })
} else {
  window.addEventListener('load', runPendingSync)
}

window.addEventListener('online', runPendingSync)

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') {
    runPendingSync()
  }
})
