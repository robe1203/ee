const SNAPSHOT_KEY = 'contasync:offline:snapshots:v1'
const AUTH_KEY = 'contasync:offline:auth:v1'

function safeJsonParse(value, fallback) {
  try {
    const parsed = JSON.parse(value)
    return parsed ?? fallback
  } catch {
    return fallback
  }
}

function ensureObject(value) {
  return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
}

function readAll() {
  if (typeof window === 'undefined') return {}
  const raw = localStorage.getItem(SNAPSHOT_KEY)
  return ensureObject(safeJsonParse(raw, {}))
}

function writeAll(data) {
  if (typeof window === 'undefined') return
  localStorage.setItem(SNAPSHOT_KEY, JSON.stringify(ensureObject(data)))
}

export function saveOfflineSnapshot({ url, component, props }) {
  if (typeof window === 'undefined' || !url || !component) return

  const current = readAll()

  current[url] = {
    url,
    component,
    props: props ?? {},
    saved_at: new Date().toISOString(),
  }

  writeAll(current)

  const authUser = props?.auth?.user ?? null
  if (authUser) {
    localStorage.setItem(
      AUTH_KEY,
      JSON.stringify({
        user: {
          name: authUser.name ?? 'Usuario',
          email: authUser.email ?? '',
        },
        saved_at: new Date().toISOString(),
      })
    )
  }
}

export function getOfflineSnapshot(url) {
  const all = readAll()
  return all[url] ?? null
}

export function getOfflineAuth() {
  if (typeof window === 'undefined') return null
  const parsed = safeJsonParse(localStorage.getItem(AUTH_KEY), null)

  if (!parsed || typeof parsed !== 'object') return null
  return parsed
}

export function getAllOfflineSnapshots() {
  return readAll()
}

export function clearOfflineSnapshots() {
  if (typeof window === 'undefined') return
  localStorage.removeItem(SNAPSHOT_KEY)
}

export function clearOfflineAuth() {
  if (typeof window === 'undefined') return
  localStorage.removeItem(AUTH_KEY)
}