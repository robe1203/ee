const VERSION = 'contasync-v9'
const SHELL_CACHE = `${VERSION}-shell`
const RUNTIME_CACHE = `${VERSION}-runtime`
const PAGE_CACHE = `${VERSION}-pages`

const APP_SHELL = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then(async (cache) => {
      await Promise.allSettled(
        APP_SHELL.map(async (asset) => {
          try {
            await cache.add(asset)
          } catch (error) {
            console.warn('[SW] No se pudo precachear:', asset, error)
          }
        })
      )
    })
  )

  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const valid = new Set([SHELL_CACHE, RUNTIME_CACHE, PAGE_CACHE])
    const keys = await caches.keys()

    await Promise.all(
      keys.map((key) => (valid.has(key) ? Promise.resolve() : caches.delete(key)))
    )

    await self.clients.claim()
    await warmupBuildAssets()
  })())
})

async function warmupBuildAssets() {
  try {
    const manifestResp = await fetch('/build/manifest.json', { cache: 'no-cache' })
    if (!manifestResp.ok) return

    const manifest = await manifestResp.json()
    const cache = await caches.open(RUNTIME_CACHE)
    const urls = []

    for (const entry of Object.values(manifest)) {
      if (entry?.file) urls.push('/build/' + entry.file)
      if (Array.isArray(entry?.css)) {
        entry.css.forEach((file) => urls.push('/build/' + file))
      }
      if (Array.isArray(entry?.assets)) {
        entry.assets.forEach((file) => urls.push('/build/' + file))
      }
    }

    await Promise.allSettled(
      [...new Set(urls)].map(async (url) => {
        try {
          const response = await fetch(url, { cache: 'no-cache' })
          if (response.ok) {
            await cache.put(url, response.clone())
          }
        } catch {
          // Ignorar assets individuales que fallen.
        }
      })
    )
  } catch {
    // Sin conexión durante activate.
  }
}

function isStaticAsset(request) {
  return ['style', 'script', 'worker', 'font', 'image'].includes(request.destination)
}

function shouldCacheResponse(response) {
  return response && response.ok && response.type !== 'opaqueredirect'
}

async function staleWhileRevalidate(request, cacheName = RUNTIME_CACHE) {
  const cache = await caches.open(cacheName)
  const cached = await cache.match(request)

  const networkPromise = fetch(request)
    .then(async (response) => {
      if (shouldCacheResponse(response)) {
        await cache.put(request, response.clone())
      }
      return response
    })
    .catch(() => cached)

  return cached || networkPromise
}

async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE)

  try {
    const response = await fetch(request)

    if (shouldCacheResponse(response)) {
      await cache.put(request, response.clone())
    }

    return response
  } catch {
    const cached = await cache.match(request)
    if (cached) return cached

    const rootCached = await cache.match('/')
    if (rootCached) return rootCached

    const shell = await caches.open(SHELL_CACHE)
    const offline = await shell.match('/offline.html')
    if (offline) return offline

    return new Response('Sin conexión', {
      status: 503,
      headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    })
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  if (request.method !== 'GET') {
    return
  }

  if (url.origin !== self.location.origin) {
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstPage(request))
    return
  }

  if (url.pathname === '/manifest.json') {
    event.respondWith(staleWhileRevalidate(request, SHELL_CACHE))
    return
  }

  if (url.pathname.startsWith('/build/')) {
    event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE))
    return
  }

  if (isStaticAsset(request)) {
    event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE))
  }
})

self.addEventListener('message', (event) => {
  if (event.data?.type === 'CACHE_WARMUP') {
    event.waitUntil(warmupBuildAssets())
  }
})
