import {
  getPending,
  getLocalChanges,
  getCompanies,
  getActiveCompanyUuid,
  mergeCompaniesWithServer,
  mergeAccountsWithServer,
  mergePoliciesWithServer,
  markCompanyAsSynced,
  markAccountAsSynced,
  markPolicyAsSynced,
} from './db'

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

async function apiRequest(url, method = 'GET', body = null) {
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  }

  if (body) {
    options.body = JSON.stringify(body)
  }

  const response = await fetch(url, options)
  const contentType = response.headers.get('content-type') || ''
  const isJson = contentType.includes('application/json')
  const json = isJson ? await response.json() : null

  if (!response.ok) {
    throw new Error(json?.message || `HTTP ${response.status}`)
  }

  return json
}

async function syncCompaniesFromServer() {
  const response = await apiRequest('/api/sync/companies')
  if (!response?.success) throw new Error('No se pudo sincronizar empresas')
  return mergeCompaniesWithServer(response.companies || [])
}

async function syncAccountsFromServer(companyUuid) {
  if (!companyUuid) return { merged: 0, conflicts: 0, updated: 0 }

  const response = await apiRequest('/api/sync/accounts?company_uuid=' + encodeURIComponent(companyUuid))
  if (!response?.success) throw new Error('No se pudo sincronizar cuentas')
  return mergeAccountsWithServer(companyUuid, response.accounts || [])
}

async function syncPoliciesFromServer(companyUuid) {
  if (!companyUuid) return { merged: 0, conflicts: 0, updated: 0 }

  const response = await apiRequest('/api/sync/policies?company_uuid=' + encodeURIComponent(companyUuid))
  if (!response?.success) throw new Error('No se pudo sincronizar pólizas')
  return mergePoliciesWithServer(companyUuid, response.policies || [])
}

async function sendPendingChanges() {
  const changes = await getLocalChanges()
  if (!changes.length) {
    return { synced: [], conflicts: [], errors: [] }
  }

  const response = await apiRequest('/api/sync/batch', 'POST', {
    changes: changes.map((change) => ({
      entity: change.entity,
      action: change.action,
      payload: change.payload,
    })),
  })

  if (!response?.success) {
    throw new Error('No se pudo enviar cambios pendientes')
  }

  const results = response.results || { synced: [], conflicts: [], errors: [] }

  if (Array.isArray(results.synced)) {
    for (const item of results.synced) {
      if (item.entity === 'company') {
        await markCompanyAsSynced(item.uuid, item.version)
      }

      if (item.entity === 'account') {
        await markAccountAsSynced(item.uuid)
      }

      if (item.entity === 'policy') {
        await markPolicyAsSynced(item.uuid, {
          uuid: item.uuid,
          server_id: item.server_id ?? null,
          company_uuid: item.company_uuid ?? null,
          synced: true,
        })
      }
    }
  }

  return results
}

export async function syncAll() {
  const pending = await getPending()
  const companies = await getCompanies()
  const activeCompanyUuid = await getActiveCompanyUuid()

  if (!pending.length && !companies.length) {
    return {
      syncedCount: 0,
      conflicts: [],
      errors: [],
    }
  }

  const results = {
    syncedCount: 0,
    conflicts: [],
    errors: [],
    server: {
      companies: null,
      accounts: {},
      policies: {},
    },
  }

  const batch = await sendPendingChanges()
  results.syncedCount += Array.isArray(batch.synced) ? batch.synced.length : 0
  if (Array.isArray(batch.conflicts)) results.conflicts.push(...batch.conflicts)
  if (Array.isArray(batch.errors)) results.errors.push(...batch.errors)

  results.server.companies = await syncCompaniesFromServer()

  const uuids = [...new Set(companies.map((company) => company.uuid).filter(Boolean))]
  if (activeCompanyUuid && !uuids.includes(activeCompanyUuid)) {
    uuids.push(activeCompanyUuid)
  }

  for (const companyUuid of uuids) {
    try {
      results.server.accounts[companyUuid] = await syncAccountsFromServer(companyUuid)
      results.server.policies[companyUuid] = await syncPoliciesFromServer(companyUuid)
    } catch (error) {
      results.errors.push({ company_uuid: companyUuid, message: error.message })
    }
  }

  return results
}

export async function syncPolizas() {
  return syncAll()
}
