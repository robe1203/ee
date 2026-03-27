import {
  getPending,
  getLocalChanges,
  mergeCompaniesWithServer,
  mergeAccountsWithServer,
  markCompanyAsSynced,
  markAccountAsSynced,
  markAsSynced,
  setActiveCompanyUuid,
  saveCompany,
  saveAccount,
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

  if (!response.ok) {
    const json = await response.json()
    throw new Error(json?.message || `HTTP ${response.status}`)
  }

  return response.json()
}

// ========== SINCRONIZACIÓN CON NUEVAS RUTAS API ==========

/**
 * Sincronizar empresas: obtener todas del servidor y mergear con locales
 */
async function syncCompanies() {
  try {
    const response = await apiRequest('/api/sync/companies')
    if (!response.success) throw new Error('No se pudo sincronizar empresas')

    const result = await mergeCompaniesWithServer(response.companies || [])
    console.log('[SYNC] Empresas sincronizadas:', result)
    return result
  } catch (error) {
    console.error('[SYNC] Error sincronizando empresas:', error)
    throw error
  }
}

/**
 * Sincronizar cuentas de una empresa
 */
async function syncAccounts(companyUuid) {
  try {
    const response = await apiRequest('/api/sync/accounts?company_uuid=' + encodeURIComponent(companyUuid))
    if (!response.success) throw new Error('No se pudo sincronizar cuentas')

    const result = await mergeAccountsWithServer(companyUuid, response.accounts || [])
    console.log('[SYNC] Cuentas sincronizadas:', result)
    return result
  } catch (error) {
    console.error('[SYNC] Error sincronizando cuentas:', error)
    throw error
  }
}

/**
 * Sincronizar pólizas de una empresa
 */
async function syncPolicies(companyUuid) {
  try {
    const response = await apiRequest('/api/sync/policies?company_uuid=' + encodeURIComponent(companyUuid))
    if (!response.success) throw new Error('No se pudo sincronizar pólizas')

    // Por ahora, solo guardar las pólizas sincronizadas
    // TODO: Implementar merge para pólizas también
    console.log('[SYNC] Pólizas obtenidas del servidor:', response.policies?.length)
    return response
  } catch (error) {
    console.error('[SYNC] Error sincronizando pólizas:', error)
    throw error
  }
}

/**
 * Enviar cambios pendientes al servidor (batch sync)
 */
async function sendPendingChanges() {
  const changes = await getLocalChanges()
  if (!changes.length) {
    console.log('[SYNC] Sin cambios pendientes para enviar')
    return { synced: [], conflicts: [], errors: [] }
  }

  try {
    console.log('[SYNC] Enviando', changes.length, 'cambios al servidor')
    
    const response = await apiRequest('/api/sync/batch', 'POST', {
      changes: changes.map(c => ({
        entity: c.entity,
        action: c.action,
        payload: c.payload,
      })),
    })

    if (!response.success) throw new Error('No se pudo enviar cambios')

    // Procesar resultados
    const results = response.results || {}

    // Marcar como sincronizados los que se sincronizaron
    if (Array.isArray(results.synced)) {
      for (const item of results.synced) {
        if (item.entity === 'company') {
          await markCompanyAsSynced(item.uuid, item.version)
        } else if (item.entity === 'account') {
          await markAccountAsSynced(item.uuid, item.version)
        }
      }
    }

    // Loguear conflictos y errores
    if (Array.isArray(results.conflicts) && results.conflicts.length > 0) {
      console.warn('[SYNC] Conflictos detectados:', results.conflicts)
    }

    if (Array.isArray(results.errors) && results.errors.length > 0) {
      console.error('[SYNC] Errores:', results.errors)
    }

    return results
  } catch (error) {
    console.error('[SYNC] Error enviando cambios:', error)
    throw error
  }
}

// ========== SINCRONIZACIÓN PRINCIPAL ==========

/**
 * Sincronización completa: enviar cambios + recibir actualizaciones
 */
export async function syncAll() {
  const pending = await getPending()
  if (!pending.length) {
    console.log('[SYNC] Sin cambios pendientes')
    return
  }

  try {
    console.log('[SYNC] Iniciando sincronización completa...')

    // 1. Enviar cambios locales al servidor
    await sendPendingChanges()

    // 2. Sincronizar empresas (recibir actualizaciones)
    await syncCompanies()

    // 3. Sincronizar cuentas para cada empresa
    // TODO: Obtener lista de empresas activas y sincronizar cada una

    console.log('[SYNC] ✅ Sincronización completada')
  } catch (error) {
    console.error('[SYNC] ❌ Error en sincronización:', error)
  }
}

export async function syncPolizas() {
  return syncAll()
}
