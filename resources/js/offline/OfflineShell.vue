<script setup>
import { computed, onMounted, ref } from 'vue'
import { getOfflineAuth, getOfflineSnapshot } from './snapshots'
import { getPolizas, getCompanies, getAccountsByCompany, getActiveCompanyUuid, saveCompany, setActiveCompanyUuid } from './db'

const currentPath = ref(window.location.pathname || '/app')
const auth = ref(getOfflineAuth())
const policies = ref([])
const loadingPolicies = ref(true)

const companies = ref([])
const accounts = ref([])
const activeCompanyUuid = ref(null)
const loadingCompanies = ref(true)

const showCreateCompany = ref(false)
const newCompany = ref({ name: '', rfc: '', address: '' })
const savingCompany = ref(false)
const createError = ref('')

const availablePaths = [
  '/app',
  '/app/company',
  '/app/catalog',
  '/app/policies',
  '/app/informes-reportes',
]

const labels = {
  '/app': 'Dashboard',
  '/app/company': 'Empresas',
  '/app/catalog': 'Catálogos',
  '/app/policies': 'Pólizas',
  '/app/informes-reportes': 'Informes',
}

const homeSnapshot = computed(() => getOfflineSnapshot('/app'))
const reportsSnapshot = computed(() => getOfflineSnapshot('/app/informes-reportes'))
const currentSnapshot = computed(() => getOfflineSnapshot(currentPath.value) || homeSnapshot.value)

const activeCompany = computed(() =>
  companies.value.find((c) => c.uuid === activeCompanyUuid.value) || null
)

const dashboardStats = computed(() => ({
  companies: companies.value.length,
  accounts: accounts.value.length,
  policies: policies.value.length,
  queue: policies.value.filter((p) => p.pending === true || p.synced === false).length,
}))

function openOfflinePath(path) {
  currentPath.value = path
  window.history.replaceState({}, '', path)
}

async function loadCompanies() {
  loadingCompanies.value = true
  try {
    const [allCompanies, uuid] = await Promise.all([getCompanies(), getActiveCompanyUuid()])
    companies.value = Array.isArray(allCompanies) ? allCompanies : []
    activeCompanyUuid.value = uuid

    if (uuid) {
      const accs = await getAccountsByCompany(uuid)
      accounts.value = Array.isArray(accs) ? accs : []
    } else {
      accounts.value = []
    }
  } catch (error) {
    console.error('Error cargando empresas offline:', error)
    companies.value = []
    accounts.value = []
  } finally {
    loadingCompanies.value = false
  }
}

async function loadPolicies() {
  loadingPolicies.value = true
  try {
    const data = await getPolizas()
    policies.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error cargando pólizas offline:', error)
    policies.value = []
  } finally {
    loadingPolicies.value = false
  }
}

async function selectCompanyOffline(uuid) {
  await setActiveCompanyUuid(uuid)
  activeCompanyUuid.value = uuid
  const accs = await getAccountsByCompany(uuid)
  accounts.value = Array.isArray(accs) ? accs : []
  await loadPolicies()
}

async function createCompanyOffline() {
  createError.value = ''
  const name = newCompany.value.name.trim()
  if (!name) {
    createError.value = 'El nombre es requerido.'
    return
  }
  savingCompany.value = true
  try {
    const saved = await saveCompany({
      name,
      rfc: newCompany.value.rfc.trim(),
      address: newCompany.value.address.trim(),
      synced: false,
    })
    await setActiveCompanyUuid(saved.uuid)
    activeCompanyUuid.value = saved.uuid
    newCompany.value = { name: '', rfc: '', address: '' }
    showCreateCompany.value = false
    await loadCompanies()
  } catch (error) {
    createError.value = 'Error al guardar. Inténtalo de nuevo.'
    console.error(error)
  } finally {
    savingCompany.value = false
  }
}

function formatDate(date) {
  if (!date) return '—'
  try {
    return new Date(date).toLocaleDateString('es-MX')
  } catch {
    return date
  }
}

const reportMessage = computed(() => {
  if (reportsSnapshot.value) {
    return 'Esta sección fue abierta antes con internet. Algunos datos pueden verse solo como último respaldo guardado.'
  }
  return 'Los reportes detallados todavía dependen del servidor. En offline, usa Empresas, Catálogos y Pólizas.'
})

onMounted(async () => {
  await loadCompanies()
  await loadPolicies()
})
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-20">
      <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-black">Modo sin conexión</div>
          <h1 class="text-2xl font-black tracking-tight">ContaSync offline</h1>
          <p class="text-sm text-slate-400">La app usa datos guardados localmente y sincronizará cuando vuelva el internet.</p>
        </div>
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm">
          <div class="font-bold">{{ auth?.user?.name || 'Sesión local' }}</div>
          <div class="text-slate-400">{{ auth?.user?.email || 'Se requiere haber iniciado sesión antes con internet.' }}</div>
        </div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-[260px,1fr] gap-6">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900 p-4 h-max">
        <div class="text-[11px] uppercase tracking-[0.25em] text-slate-500 font-black px-3 pb-3">Navegación local</div>
        <div class="space-y-2">
          <button
            v-for="path in availablePaths"
            :key="path"
            @click="openOfflinePath(path)"
            class="w-full text-left px-4 py-3 rounded-2xl font-bold transition"
            :class="currentPath === path ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'"
          >
            {{ labels[path] }}
          </button>
        </div>

        <div class="mt-6 rounded-2xl bg-slate-800 p-4 text-sm text-slate-300">
          <div class="font-black text-white mb-2">Qué sí funciona aquí</div>
          <p>Ver y crear empresas, revisar catálogos y pólizas. Todo se sincroniza al volver a tener internet.</p>
        </div>
      </aside>

      <main class="space-y-6">

        <!-- DASHBOARD -->
        <section v-if="currentPath === '/app'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <div
            class="rounded-3xl border border-slate-800 bg-slate-900 p-6"
            v-for="card in [
              { label: 'Empresas', value: dashboardStats.companies },
              { label: 'Cuentas', value: dashboardStats.accounts },
              { label: 'Pólizas locales', value: dashboardStats.policies },
              { label: 'Pendientes sync', value: dashboardStats.queue },
            ]"
            :key="card.label"
          >
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500 font-black">{{ card.label }}</div>
            <div class="text-4xl font-black mt-3">{{ card.value }}</div>
          </div>
        </section>

        <section v-if="currentPath === '/app'" class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
          <h2 class="text-xl font-black">Resumen offline</h2>
          <p class="text-slate-400 mt-2">
            Este panel ya no depende del servidor para abrir. Si antes entraste con internet, aquí ves el último respaldo local.
          </p>
          <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="rounded-2xl bg-slate-800 p-4">
              <div class="font-black text-white">Empresa activa</div>
              <div class="text-slate-300 mt-2">{{ activeCompany?.name || 'Sin empresa seleccionada' }}</div>
              <div v-if="activeCompany && !activeCompany.synced" class="text-amber-400 text-xs mt-1 font-bold">Pendiente de sincronizar</div>
            </div>
            <div class="rounded-2xl bg-slate-800 p-4">
              <div class="font-black text-white">Último respaldo</div>
              <div class="text-slate-300 mt-2">{{ currentSnapshot?.saved_at ? formatDate(currentSnapshot.saved_at) : 'No hay respaldo previo' }}</div>
            </div>
          </div>
        </section>

        <!-- EMPRESAS -->
        <section v-if="currentPath === '/app/company'" class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div>
              <h2 class="text-2xl font-black">Empresas</h2>
              <p class="text-slate-400 mt-1 text-sm">Empresas guardadas localmente (servidor + creadas offline).</p>
            </div>
            <button
              @click="showCreateCompany = !showCreateCompany"
              class="px-4 py-2 rounded-2xl bg-amber-500 text-slate-950 font-black text-sm"
            >
              {{ showCreateCompany ? 'Cancelar' : '+ Nueva empresa' }}
            </button>
          </div>

          <div v-if="showCreateCompany" class="mb-6 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 space-y-3">
            <div class="text-sm font-black text-amber-300 uppercase tracking-widest mb-1">Nueva empresa offline</div>
            <p class="text-xs text-slate-400">Se guardará localmente y se sincronizará con el servidor cuando haya internet.</p>
            <input
              v-model="newCompany.name"
              placeholder="Nombre de la empresa *"
              class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-amber-500"
            />
            <input
              v-model="newCompany.rfc"
              placeholder="RFC (opcional)"
              class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-amber-500 font-mono uppercase"
            />
            <input
              v-model="newCompany.address"
              placeholder="Dirección (opcional)"
              class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-amber-500"
            />
            <div v-if="createError" class="text-red-400 text-xs font-bold">{{ createError }}</div>
            <button
              @click="createCompanyOffline"
              :disabled="savingCompany"
              class="w-full py-2.5 rounded-xl bg-amber-500 text-slate-950 font-black text-sm disabled:opacity-50"
            >
              {{ savingCompany ? 'Guardando...' : 'Guardar empresa offline' }}
            </button>
          </div>

          <div v-if="loadingCompanies" class="rounded-2xl bg-slate-800 p-6 text-slate-400">Cargando empresas...</div>

          <div v-else class="grid gap-4">
            <div
              v-for="item in companies"
              :key="item.uid || item.uuid"
              class="rounded-2xl p-4 border transition cursor-pointer"
              :class="item.uuid === activeCompanyUuid ? 'bg-slate-700 border-amber-500/60' : 'bg-slate-800 border-slate-700 hover:border-slate-600'"
              @click="selectCompanyOffline(item.uuid)"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <div class="text-lg font-black">{{ item.name || 'Empresa sin nombre' }}</div>
                  <div class="text-slate-400 text-sm mt-1">RFC: {{ item.rfc || '—' }}</div>
                  <div class="text-slate-400 text-sm">Dirección: {{ item.address || '—' }}</div>
                </div>
                <div class="flex flex-col items-end gap-2">
                  <span v-if="item.uuid === activeCompanyUuid" class="px-3 py-1 rounded-full bg-emerald-500 text-slate-950 text-xs font-black">Activa</span>
                  <span v-if="!item.synced" class="px-3 py-1 rounded-full bg-amber-500 text-slate-950 text-xs font-black">Sin sincronizar</span>
                </div>
              </div>
            </div>
            <div v-if="companies.length === 0" class="rounded-2xl bg-slate-800 p-6 text-slate-400">
              No hay empresas guardadas localmente. Puedes crear una con el botón de arriba.
            </div>
          </div>
        </section>

        <!-- CATÁLOGO -->
        <section v-if="currentPath === '/app/catalog'" class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h2 class="text-2xl font-black">Catálogo de cuentas</h2>
              <p class="text-slate-400 mt-2 text-sm">
                {{ activeCompany ? 'Empresa: ' + activeCompany.name : 'Selecciona una empresa en la sección Empresas.' }}
              </p>
            </div>
            <div class="rounded-2xl bg-slate-800 px-4 py-3 text-sm font-bold">{{ accounts.length }} cuentas</div>
          </div>

          <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-800">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-800 text-slate-300">
                <tr>
                  <th class="px-4 py-3 text-left">Código</th>
                  <th class="px-4 py-3 text-left">Cuenta</th>
                  <th class="px-4 py-3 text-left">Naturaleza</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="account in accounts" :key="account.uid || account.uuid" class="border-t border-slate-800">
                  <td class="px-4 py-3 font-black text-amber-400">{{ account.code }}</td>
                  <td class="px-4 py-3">{{ account.name }}</td>
                  <td class="px-4 py-3">{{ account.nature === 'D' ? 'Deudora' : 'Acreedora' }}</td>
                </tr>
                <tr v-if="accounts.length === 0">
                  <td colspan="3" class="px-4 py-8 text-center text-slate-400">
                    {{ activeCompany ? 'No hay cuentas guardadas localmente para esta empresa.' : 'Selecciona una empresa primero.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- PÓLIZAS -->
        <section v-if="currentPath === '/app/policies'" class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h2 class="text-2xl font-black">Pólizas</h2>
              <p class="text-slate-400 mt-2 text-sm">Listado leído desde IndexedDB, incluyendo pendientes por sincronizar.</p>
            </div>
            <button @click="loadPolicies" class="rounded-2xl bg-amber-500 text-slate-950 px-4 py-3 font-black text-sm">Recargar</button>
          </div>

          <div v-if="loadingPolicies" class="mt-6 rounded-2xl bg-slate-800 p-6 text-slate-300">Cargando pólizas locales...</div>

          <div v-else class="mt-6 space-y-3">
            <div
              v-for="policy in policies"
              :key="policy.uid || policy.id"
              class="rounded-2xl bg-slate-800 p-4 border border-slate-700"
            >
              <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                  <div class="text-lg font-black">Póliza #{{ policy.folio || 'S/F' }}</div>
                  <div class="text-slate-400 text-sm mt-1">{{ policy.policy_type || 'Sin tipo' }} · {{ formatDate(policy.movement_date) }}</div>
                  <div class="text-slate-400 text-sm">Estatus: {{ policy.status || 'Borrador' }}</div>
                </div>
                <span
                  :class="policy.pending || policy.synced === false ? 'bg-amber-500 text-slate-950' : 'bg-emerald-500 text-slate-950'"
                  class="px-3 py-1 rounded-full text-xs font-black h-max"
                >
                  {{ policy.pending || policy.synced === false ? 'Pendiente sync' : 'Sincronizada' }}
                </span>
              </div>
            </div>
            <div v-if="policies.length === 0" class="rounded-2xl bg-slate-800 p-6 text-slate-400">No hay pólizas guardadas localmente.</div>
          </div>
        </section>

        <!-- INFORMES -->
        <section v-if="currentPath === '/app/informes-reportes'" class="rounded-3xl border border-slate-800 bg-slate-900 p-6">
          <h2 class="text-2xl font-black">Informes y reportes</h2>
          <p class="text-slate-400 mt-3">{{ reportMessage }}</p>
          <div class="mt-6 rounded-2xl bg-slate-800 p-5 text-sm text-slate-300">
            Para que un reporte complejo quede offline real, hay que guardarlo previamente como snapshot o generarlo con datos locales.
          </div>
        </section>

      </main>
    </div>
  </div>
</template>
