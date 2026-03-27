import { openDB } from 'idb'

const DB_NAME = 'contasync-db'
const DB_VERSION = 7

const STORE_POLICIES = 'polizas_cache'
const STORE_COMPANIES = 'companies_cache'
const STORE_ACCOUNTS = 'accounts_cache'
const STORE_QUEUE = 'sync_queue'
const STORE_META = 'meta_store'
const LEGACY_STORE = 'polizas'

function nowIso() {
  return new Date().toISOString()
}

function randomUuid() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
  return `fallback-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function localUid(prefix = 'local') {
  return `${prefix}:${randomUuid()}`
}

function queueKey(entity, uuid, action = 'upsert') {
  return `${entity}:${uuid}:${action}`
}

function normalizeCompany(data = {}) {
  return {
    uid: data.uid ?? `company:${data.uuid ?? randomUuid()}`,
    id: data.id ?? null,
    uuid: data.uuid ?? randomUuid(),
    name: data.name ?? '',
    rfc: data.rfc ?? '',
    regimen_codigo: data.regimen_codigo ?? '',
    regimen_fiscal: data.regimen_fiscal ?? '',
    address: data.address ?? '',
    version: Number(data.version ?? 1),
    creator_id: data.creator_id ?? null,
    synced: data.synced === true,
    pending_action: data.pending_action ?? (data.synced === true ? null : 'upsert'),
    updated_at: data.updated_at ?? nowIso(),
    created_at: data.created_at ?? nowIso(),
    synced_at: data.synced === true ? (data.synced_at ?? nowIso()) : null,
  }
}

function normalizeAccount(data = {}) {
  return {
    uid: data.uid ?? `account:${data.uuid ?? randomUuid()}`,
    id: data.id ?? null,
    uuid: data.uuid ?? randomUuid(),
    company_uuid: data.company_uuid ?? null,
    code: data.code ?? '',
    name: data.name ?? '',
    nature: data.nature ?? 'D',
    synced: data.synced === true,
    pending_action: data.pending_action ?? (data.synced === true ? null : 'upsert'),
    updated_at: data.updated_at ?? nowIso(),
    created_at: data.created_at ?? nowIso(),
    synced_at: data.synced === true ? (data.synced_at ?? nowIso()) : null,
  }
}

function buildPolicyUid(data = {}) {
  if (data.uid) return data.uid
  if (data.server_id) return `server:${data.server_id}`
  if (data.id && data.synced === true) return `server:${data.id}`
  if (data.local_uuid) return `local:${data.local_uuid}`
  if (data.uuid) return `policy:${data.uuid}`
  return localUid('policy')
}

function normalizePolicy(data = {}) {
  const synced = data.synced === true
  const uid = buildPolicyUid(data)
  const serverId = data.server_id ?? (synced && data.id ? data.id : null)
  const uuid = data.uuid ?? data.client_uuid ?? data.local_uuid ?? randomUuid()

  return {
    uid,
    uuid,
    company_uuid: data.company_uuid ?? null,
    server_id: serverId,
    folio: data.folio ?? null,
    policy_type: data.policy_type ?? '',
    movement_date: data.movement_date ?? '',
    status: data.status ?? 'Borrador',
    lines: Array.isArray(data.lines) ? data.lines : [],
    synced,
    pending: !synced,
    pending_action: data.pending_action ?? (!synced ? (serverId ? 'update' : 'create') : null),
    created_at: data.created_at ?? nowIso(),
    updated_at: data.updated_at ?? nowIso(),
    synced_at: synced ? (data.synced_at ?? nowIso()) : null,
  }
}

async function getDb() {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      if (!db.objectStoreNames.contains(STORE_POLICIES)) {
        const store = db.createObjectStore(STORE_POLICIES, { keyPath: 'uid' })
        store.createIndex('uuid', 'uuid', { unique: false })
        store.createIndex('company_uuid', 'company_uuid', { unique: false })
      }

      if (!db.objectStoreNames.contains(STORE_COMPANIES)) {
        const store = db.createObjectStore(STORE_COMPANIES, { keyPath: 'uid' })
        store.createIndex('uuid', 'uuid', { unique: true })
      }

      if (!db.objectStoreNames.contains(STORE_ACCOUNTS)) {
        const store = db.createObjectStore(STORE_ACCOUNTS, { keyPath: 'uid' })
        store.createIndex('uuid', 'uuid', { unique: true })
        store.createIndex('company_uuid', 'company_uuid', { unique: false })
      }

      if (!db.objectStoreNames.contains(STORE_QUEUE)) {
        const queue = db.createObjectStore(STORE_QUEUE, { keyPath: 'key' })
        queue.createIndex('status', 'status')
        queue.createIndex('entity', 'entity')
      }

      if (!db.objectStoreNames.contains(STORE_META)) {
        db.createObjectStore(STORE_META, { keyPath: 'key' })
      }
    },
    blocking() {
      console.warn('IndexedDB bloqueada por otra pestaña. Cierra las demás pestañas de la app y vuelve a intentar.')
    },
    terminated() {
      console.warn('La conexión de IndexedDB fue terminada por el navegador.')
    },
  })
}

async function withDb(work) {
  const db = await getDb()
  return work(db)
}

async function putMeta(key, value) {
  return withDb(async (db) => {
    await db.put(STORE_META, { key, value, updated_at: nowIso() })
  })
}

async function getMeta(key) {
  return withDb(async (db) => {
    const row = await db.get(STORE_META, key)
    return row?.value ?? null
  })
}

async function enqueue(entity, uuid, action, payload) {
  return withDb(async (db) => {
    await db.put(STORE_QUEUE, {
      key: queueKey(entity, uuid, action),
      entity,
      uuid,
      action,
      payload,
      status: 'pending',
      created_at: nowIso(),
      updated_at: nowIso(),
    })
  })
}

async function dequeue(entity, uuid, action = 'upsert') {
  return withDb(async (db) => {
    await db.delete(STORE_QUEUE, queueKey(entity, uuid, action))
  })
}

export async function getPending() {
  return withDb(async (db) => db.getAllFromIndex(STORE_QUEUE, 'status', 'pending'))
}

export async function getLocalChanges() {
  return withDb(async (db) => {
    const pending = await db.getAllFromIndex(STORE_QUEUE, 'status', 'pending')
    return pending.map((item) => ({
      entity: item.entity,
      action: item.action,
      uuid: item.uuid,
      payload: item.payload,
      timestamp: item.created_at,
    }))
  })
}

export async function clearPending() {
  return withDb(async (db) => db.clear(STORE_QUEUE))
}

export async function setActiveCompanyUuid(uuid) {
  await putMeta('active_company_uuid', uuid || null)
}

export async function getActiveCompanyUuid() {
  return getMeta('active_company_uuid')
}

export async function saveCompany(record) {
  const normalized = normalizeCompany(record)

  await withDb(async (db) => {
    await db.put(STORE_COMPANIES, normalized)
  })

  if (normalized.synced !== true) {
    await enqueue('company', normalized.uuid, normalized.pending_action || 'upsert', normalized)
  } else {
    await dequeue('company', normalized.uuid, 'upsert')
    await dequeue('company', normalized.uuid, 'delete')
  }

  return normalized
}

export async function upsertCompaniesFromServer(companies = [], activeUuid = null) {
  await withDb(async (db) => {
    const tx = db.transaction(STORE_COMPANIES, 'readwrite')
    for (const company of companies) {
      await tx.store.put(normalizeCompany({ ...company, synced: true }))
    }
    await tx.done
  })

  if (activeUuid) {
    await setActiveCompanyUuid(activeUuid)
  }
}

export async function getCompanies() {
  return withDb(async (db) => {
    const rows = await db.getAll(STORE_COMPANIES)
    return rows.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
  })
}

export async function getCompanyByUuid(uuid) {
  return withDb(async (db) => db.getFromIndex(STORE_COMPANIES, 'uuid', uuid))
}

export async function deleteCompany(uuid) {
  const company = await getCompanyByUuid(uuid)
  if (!company) return

  await withDb(async (db) => {
    await db.delete(STORE_COMPANIES, company.uid)
  })

  await enqueue('company', uuid, 'delete', { uuid })

  const current = await getActiveCompanyUuid()
  if (current === uuid) {
    await setActiveCompanyUuid(null)
  }
}

export async function saveAccount(record) {
  const normalized = normalizeAccount(record)

  await withDb(async (db) => {
    await db.put(STORE_ACCOUNTS, normalized)
  })

  if (normalized.synced !== true) {
    await enqueue('account', normalized.uuid, normalized.pending_action || 'upsert', normalized)
  } else {
    await dequeue('account', normalized.uuid, 'upsert')
    await dequeue('account', normalized.uuid, 'delete')
  }

  return normalized
}

export async function upsertAccountsFromServer(companyUuid, accounts = []) {
  await withDb(async (db) => {
    const tx = db.transaction(STORE_ACCOUNTS, 'readwrite')
    for (const account of accounts) {
      await tx.store.put(
        normalizeAccount({
          ...account,
          company_uuid: companyUuid,
          synced: true,
        })
      )
    }
    await tx.done
  })
}

export async function getAccountsByCompany(companyUuid) {
  if (!companyUuid) return []

  return withDb(async (db) => {
    const rows = await db.getAllFromIndex(STORE_ACCOUNTS, 'company_uuid', companyUuid)
    return rows.sort((a, b) => String(a.code || '').localeCompare(String(b.code || '')))
  })
}

export async function deleteAccount(uuid) {
  const account = await withDb(async (db) => db.getFromIndex(STORE_ACCOUNTS, 'uuid', uuid))
  if (!account) return

  await withDb(async (db) => {
    await db.delete(STORE_ACCOUNTS, account.uid)
  })

  await enqueue('account', uuid, 'delete', {
    uuid,
    company_uuid: account.company_uuid,
  })
}

async function putPolicy(record) {
  return withDb(async (db) => {
    await db.put(STORE_POLICIES, record)
    return record
  })
}

export async function saveOffline(data) {
  const record = normalizePolicy({ ...data, synced: false })

  await withDb(async (db) => {
    await db.put(STORE_POLICIES, record)
  })

  await enqueue('policy', record.uuid, record.pending_action || 'create', record)
  return record
}

export async function savePoliza(data) {
  const normalized = normalizePolicy(data)

  if (normalized.synced) {
    return putPolicy(normalized)
  }

  return saveOffline(data)
}

export async function upsertPoliciesFromServer(companyUuid, policies = []) {
  await withDb(async (db) => {
    const tx = db.transaction(STORE_POLICIES, 'readwrite')
    for (const policy of policies) {
      await tx.store.put(
        normalizePolicy({
          ...policy,
          company_uuid: companyUuid,
          server_id: policy.server_id ?? policy.id ?? null,
          synced: true,
        })
      )
    }
    await tx.done
  })
}

export async function getPolizas() {
  let rows = await withDb(async (db) => {
    let result = await db.getAll(STORE_POLICIES)

    if (!result.length && db.objectStoreNames.contains(LEGACY_STORE)) {
      try {
        result = await db.getAll(LEGACY_STORE)
      } catch {
        result = []
      }
    }

    return result
  })

  const activeCompanyUuid = await getActiveCompanyUuid()
  if (activeCompanyUuid) {
    rows = rows.filter((row) => !row.company_uuid || row.company_uuid === activeCompanyUuid)
  }

  return rows
    .map((row) => ({
      ...row,
      id: row.server_id ?? row.id ?? row.uid,
    }))
    .sort((a, b) => String(b.movement_date || '').localeCompare(String(a.movement_date || '')))
}

export async function getPoliciesByCompanyUuid(companyUuid) {
  if (!companyUuid) return []

  return withDb(async (db) => {
    const rows = await db.getAllFromIndex(STORE_POLICIES, 'company_uuid', companyUuid)
    return rows
      .map((row) => ({
        ...row,
        id: row.server_id ?? row.id ?? row.uid,
      }))
      .sort((a, b) => String(b.movement_date || '').localeCompare(String(a.movement_date || '')))
  })
}

export async function markAsSynced(uid, serverPayload = {}) {
  return withDb(async (db) => {
    let current = await db.get(STORE_POLICIES, uid)

    if (!current && serverPayload.uuid) {
      current = await db.getFromIndex(STORE_POLICIES, 'uuid', serverPayload.uuid)
    }

    if (!current) return false

    const next = normalizePolicy({
      ...current,
      ...serverPayload,
      uid: serverPayload.server_id ? `server:${serverPayload.server_id}` : current.uid,
      synced: true,
      pending: false,
      pending_action: null,
      synced_at: nowIso(),
    })

    const tx = db.transaction([STORE_POLICIES, STORE_QUEUE], 'readwrite')

    if (current.uid !== next.uid) {
      await tx.objectStore(STORE_POLICIES).delete(current.uid)
    }

    await tx.objectStore(STORE_POLICIES).put(next)
    await tx.objectStore(STORE_QUEUE).delete(queueKey('policy', current.uuid, 'create'))
    await tx.objectStore(STORE_QUEUE).delete(queueKey('policy', current.uuid, 'update'))
    await tx.objectStore(STORE_QUEUE).delete(queueKey('policy', current.uuid, 'delete'))
    await tx.done

    return true
  })
}

export async function markCompanyAsSynced(uuid, version) {
  const company = await getCompanyByUuid(uuid)
  if (!company) return false

  await withDb(async (db) => {
    const updated = normalizeCompany({
      ...company,
      synced: true,
      version: Number(version ?? company.version ?? 1),
      pending_action: null,
      synced_at: nowIso(),
    })

    const tx = db.transaction([STORE_COMPANIES, STORE_QUEUE], 'readwrite')
    await tx.objectStore(STORE_COMPANIES).put(updated)
    await tx.objectStore(STORE_QUEUE).delete(queueKey('company', uuid, 'upsert'))
    await tx.objectStore(STORE_QUEUE).delete(queueKey('company', uuid, 'delete'))
    await tx.done
  })

  return true
}

export async function markAccountAsSynced(uuid) {
  const account = await withDb(async (db) => db.getFromIndex(STORE_ACCOUNTS, 'uuid', uuid))
  if (!account) return false

  await withDb(async (db) => {
    const updated = normalizeAccount({
      ...account,
      synced: true,
      pending_action: null,
      synced_at: nowIso(),
    })

    const tx = db.transaction([STORE_ACCOUNTS, STORE_QUEUE], 'readwrite')
    await tx.objectStore(STORE_ACCOUNTS).put(updated)
    await tx.objectStore(STORE_QUEUE).delete(queueKey('account', uuid, 'upsert'))
    await tx.objectStore(STORE_QUEUE).delete(queueKey('account', uuid, 'delete'))
    await tx.done
  })

  return true
}

export async function markPolicyAsSynced(uuid, payload = {}) {
  const rows = await getPoliciesByCompanyUuid(payload.company_uuid ?? (await getActiveCompanyUuid()))
  const existing = rows.find((row) => row.uuid === uuid)
  if (!existing) return false
  return markAsSynced(existing.uid, payload)
}

export async function deletePoliza(uid) {
  return withDb(async (db) => {
    await db.delete(STORE_POLICIES, uid)
  })
}

export async function clearPolizas() {
  return withDb(async (db) => {
    const tx = db.transaction([STORE_POLICIES, STORE_QUEUE], 'readwrite')
    await tx.objectStore(STORE_POLICIES).clear()
    await tx.objectStore(STORE_QUEUE).clear()
    await tx.done
  })
}

export async function mergeCompaniesWithServer(serverCompanies = []) {
  return withDb(async (db) => {
    const tx = db.transaction([STORE_COMPANIES, STORE_QUEUE], 'readwrite')
    const companiesStore = tx.objectStore(STORE_COMPANIES)
    const queueStore = tx.objectStore(STORE_QUEUE)

    const results = { merged: 0, conflicts: 0, updated: 0 }

    for (const serverData of serverCompanies) {
      const local = await companiesStore.index('uuid').get(serverData.uuid)

      if (!local) {
        await companiesStore.put(normalizeCompany({ ...serverData, synced: true }))
        results.merged++
        continue
      }

      const queueRows = await queueStore.index('entity').getAll('company')
      const hasPendingChanges = queueRows.some((q) => q.uuid === serverData.uuid && q.status === 'pending')

      if (hasPendingChanges) {
        results.conflicts++
        continue
      }

      await companiesStore.put(normalizeCompany({ ...local, ...serverData, synced: true }))
      results.updated++
    }

    await tx.done
    return results
  })
}

export async function mergeAccountsWithServer(companyUuid, serverAccounts = []) {
  return withDb(async (db) => {
    const tx = db.transaction([STORE_ACCOUNTS, STORE_QUEUE], 'readwrite')
    const accountsStore = tx.objectStore(STORE_ACCOUNTS)
    const queueStore = tx.objectStore(STORE_QUEUE)

    const results = { merged: 0, conflicts: 0, updated: 0 }

    for (const serverData of serverAccounts) {
      const local = await accountsStore.index('uuid').get(serverData.uuid)

      if (!local) {
        await accountsStore.put(
          normalizeAccount({
            ...serverData,
            company_uuid: companyUuid,
            synced: true,
          })
        )
        results.merged++
        continue
      }

      const queueRows = await queueStore.index('entity').getAll('account')
      const hasPendingChanges = queueRows.some((q) => q.uuid === serverData.uuid && q.status === 'pending')

      if (hasPendingChanges) {
        results.conflicts++
        continue
      }

      await accountsStore.put(
        normalizeAccount({
          ...local,
          ...serverData,
          company_uuid: companyUuid,
          synced: true,
        })
      )
      results.updated++
    }

    await tx.done
    return results
  })
}

export async function mergePoliciesWithServer(companyUuid, serverPolicies = []) {
  return withDb(async (db) => {
    const tx = db.transaction([STORE_POLICIES, STORE_QUEUE], 'readwrite')
    const policyStore = tx.objectStore(STORE_POLICIES)
    const queueStore = tx.objectStore(STORE_QUEUE)

    const results = { merged: 0, conflicts: 0, updated: 0 }

    for (const serverData of serverPolicies) {
      const local = await policyStore.index('uuid').get(serverData.uuid)

      if (!local) {
        await policyStore.put(
          normalizePolicy({
            ...serverData,
            company_uuid: companyUuid,
            server_id: serverData.server_id ?? serverData.id ?? null,
            synced: true,
          })
        )
        results.merged++
        continue
      }

      const queueRows = await queueStore.index('entity').getAll('policy')
      const hasPendingChanges = queueRows.some((q) => q.uuid === serverData.uuid && q.status === 'pending')

      if (hasPendingChanges) {
        results.conflicts++
        continue
      }

      await policyStore.put(
        normalizePolicy({
          ...local,
          ...serverData,
          company_uuid: companyUuid,
          server_id: serverData.server_id ?? serverData.id ?? local.server_id ?? null,
          synced: true,
        })
      )
      results.updated++
    }

    await tx.done
    return results
  })
}
