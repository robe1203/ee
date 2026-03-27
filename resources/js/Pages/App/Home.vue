<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed, ref, onMounted, watch } from 'vue'
import { getPolizas } from '@/offline/db'
import { isOnline } from '@/utils/network'
const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      monthPolicies: 0,
      monthDebit: 0,
      monthCredit: 0,
      lastPolicies: [],
      companyReady: false,
      catalogReady: false,
      offlineQueue: 0,
      online: true,
    }),
  },
})

const DASHBOARD_CACHE_KEY = 'dashboard_stats_snapshot'

const localStats = ref({
  monthPolicies: 0,
  monthDebit: 0,
  monthCredit: 0,
  lastPolicies: [],
  companyReady: false,
  catalogReady: false,
  offlineQueue: 0,
  online: false,
})

const loadingOffline = ref(false)

const money = (n) =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(Number(n || 0))

const normalizeDateToMonth = (dateStr) => {
  if (!dateStr) return ''
  return String(dateStr).slice(0, 7)
}

const currentMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const computeOfflineStats = async () => {
  loadingOffline.value = true

  try {
    const polizas = await getPolizas()
    const rows = Array.isArray(polizas) ? polizas : []
    const monthKey = currentMonth()

    const monthRows = rows.filter((p) => normalizeDateToMonth(p.movement_date) === monthKey)

    const monthDebit = monthRows.reduce((sum, p) => {
      const lines = Array.isArray(p.lines) ? p.lines : []
      return sum + lines.reduce((ls, l) => ls + Number(l.debit || 0), 0)
    }, 0)

    const monthCredit = monthRows.reduce((sum, p) => {
      const lines = Array.isArray(p.lines) ? p.lines : []
      return sum + lines.reduce((ls, l) => ls + Number(l.credit || 0), 0)
    }, 0)

    const sorted = [...rows].sort((a, b) => {
      const da = new Date(a.movement_date || 0).getTime()
      const db = new Date(b.movement_date || 0).getTime()
      return db - da
    })

    const lastPolicies = sorted.slice(0, 8).map((p) => {
      const lines = Array.isArray(p.lines) ? p.lines : []
      const totalDebit = lines.reduce((sum, l) => sum + Number(l.debit || 0), 0)

      return {
        id: p.id ?? `offline-${p.folio}-${p.movement_date}`,
        folio: p.folio,
        movement_date: p.movement_date,
        policy_type: p.policy_type,
        total_debit: totalDebit,
        status: p.status ?? 'draft',
        synced: p.synced ?? false,
      }
    })

    const pendingCount = rows.filter((p) => p?.synced === false).length

    const snapshot = readDashboardSnapshot()

    localStats.value = {
      monthPolicies: monthRows.length,
      monthDebit,
      monthCredit,
      lastPolicies,
      companyReady: snapshot.companyReady ?? false,
      catalogReady: snapshot.catalogReady ?? false,
      offlineQueue: pendingCount,
      online: false,
    }
  } catch (error) {
    console.error('Error cargando dashboard offline:', error)
  } finally {
    loadingOffline.value = false
  }
}

const saveDashboardSnapshot = () => {
  try {
    localStorage.setItem(
      DASHBOARD_CACHE_KEY,
      JSON.stringify({
        companyReady: props.stats?.companyReady ?? false,
        catalogReady: props.stats?.catalogReady ?? false,
      })
    )
  } catch {}
}

const readDashboardSnapshot = () => {
  try {
    const raw = localStorage.getItem(DASHBOARD_CACHE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

const activeStats = computed(() => {
  return isOnline.value
    ? {
        ...props.stats,
        online: true,
      }
    : {
        ...localStats.value,
        online: false,
      }
})

onMounted(async () => {
  if (isOnline.value) {
    saveDashboardSnapshot()
  } else {
    await computeOfflineStats()
  }
})

watch(
  () => props.stats,
  () => {
    if (isOnline.value) {
      saveDashboardSnapshot()
    }
  },
  { deep: true }
)

watch(isOnline, async (online) => {
  if (online) {
    saveDashboardSnapshot()
  } else {
    await computeOfflineStats()
  }
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto pt-4 px-4 sm:px-6 lg:px-8 pb-8 space-y-6 sm:space-y-8 animate-fade-in">

      <div
        v-if="!isOnline"
        class="rounded-2xl bg-amber-500 text-white px-5 py-4 shadow-lg flex items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center text-lg">📴</div>
          <div>
            <p class="font-black text-sm uppercase tracking-tight">Modo sin conexión</p>
            <p class="text-xs text-white/90 font-medium">
              Estás viendo un resumen local. Las pólizas pendientes se sincronizarán cuando regrese el internet.
            </p>
          </div>
        </div>
        <div class="text-right">
          <div class="text-[10px] font-black uppercase tracking-widest text-white/80">En cola</div>
          <div class="text-xl font-black">{{ activeStats.offlineQueue }}</div>
        </div>
      </div>

      <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200/60 dark:border-slate-700/50 pb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
            <div class="w-2 h-8 bg-[#9F223C] rounded-full"></div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
              Panel de Control
            </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm font-medium ml-5">
            Gestión contable institucional avanzada.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
          <Link
            :href="route('app.reports.unified')"
            class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-[#9F223C] transition-all text-sm shadow-sm"
          >
            Reportes
          </Link>
          <Link
            :href="route('app.policies.create')"
            class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-white shadow-lg shadow-rose-900/20 hover:scale-[1.02] active:scale-95 transition-all text-sm btn-gradient"
          >
            + Nueva Póliza
          </Link>
        </div>
      </header>

      <div v-if="!activeStats.companyReady || !activeStats.catalogReady" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-if="!activeStats.companyReady"
          class="alert-card border-rose-200 dark:border-rose-900/30 bg-rose-50/50 dark:bg-rose-900/10"
        >
          <div class="flex gap-4">
            <div class="h-10 w-10 rounded-full bg-[#9F223C] flex items-center justify-center text-white font-bold shadow-lg shadow-rose-900/20">!</div>
            <div class="min-w-0">
              <h4 class="font-black text-rose-900 dark:text-rose-200 text-sm uppercase tracking-tight">Configuración Incompleta</h4>
              <p class="text-rose-800/70 dark:text-rose-400/80 text-xs mt-0.5">Faltan datos fiscales obligatorios para operar.</p>
              <Link :href="route('app.company.index')" class="text-xs font-black text-[#9F223C] dark:text-rose-400 underline mt-2 inline-block">
                Configurar ahora
              </Link>
            </div>
          </div>
        </div>

        <div
          v-if="!activeStats.catalogReady"
          class="alert-card border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/50"
        >
          <div class="flex gap-4">
            <div class="h-10 w-10 rounded-full bg-slate-800 dark:bg-slate-700 flex items-center justify-center text-white font-bold">i</div>
            <div class="min-w-0">
              <h4 class="font-black text-slate-900 dark:text-white text-sm uppercase tracking-tight">Catálogo Vacío</h4>
              <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">Es necesario cargar tus cuentas contables.</p>
              <Link :href="route('app.catalog.index')" class="text-xs font-black text-slate-600 dark:text-slate-400 underline mt-2 inline-block">
                Cargar catálogo
              </Link>
            </div>
          </div>
        </div>
      </div>

      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="stat-card group hover:border-[#9F223C]/30 transition-all">
          <span class="stat-label group-hover:text-[#9F223C]">Pólizas del mes</span>
          <div class="flex items-end justify-between mt-2">
            <span class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white">{{ activeStats.monthPolicies }}</span>
            <div class="p-2 bg-rose-50 dark:bg-rose-900/20 rounded-lg text-[#9F223C]">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <span class="stat-label">Cargos (Mes)</span>
          <div class="flex items-end justify-between mt-2">
            <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ money(activeStats.monthDebit) }}</span>
          </div>
          <div class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 mt-4 rounded-full overflow-hidden">
            <div class="bg-[#9F223C] h-full w-2/3"></div>
          </div>
        </div>

        <div class="stat-card">
          <span class="stat-label">Abonos (Mes)</span>
          <div class="flex items-end justify-between mt-2">
            <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ money(activeStats.monthCredit) }}</span>
          </div>
          <div class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 mt-4 rounded-full overflow-hidden">
            <div class="bg-emerald-500 h-full w-1/2"></div>
          </div>
        </div>

        <div class="stat-card bg-slate-900 border-none shadow-2xl shadow-rose-950/20">
          <div class="flex items-center gap-2 mb-3">
            <div class="h-2 w-2 rounded-full" :class="activeStats.online ? 'bg-emerald-400 animate-pulse' : 'bg-rose-500'"></div>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado Servidor</span>
          </div>
          <div class="text-white text-xs font-medium opacity-80">
            {{ activeStats.online ? 'Conexión Segura' : 'Modo Offline' }}
          </div>
          <div class="mt-2 text-xl font-bold text-white flex items-baseline gap-2">
            {{ activeStats.offlineQueue }} <span class="text-[10px] text-rose-400 font-black uppercase">En Cola</span>
          </div>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8">
        <div class="lg:col-span-9 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/40 dark:shadow-none overflow-hidden">
          <div class="p-6 border-b border-slate-50 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-1.5 h-5 bg-[#9F223C] rounded-full"></span>
              <h3 class="text-base font-black text-slate-800 dark:text-slate-200 uppercase tracking-tight">Actividad de Pólizas</h3>
            </div>
            <Link :href="route('app.policies.index')" class="text-[10px] font-black text-[#9F223C] dark:text-rose-400 hover:text-rose-800 uppercase tracking-widest bg-rose-50 dark:bg-rose-900/20 px-3 py-1.5 rounded-lg transition-colors">
              Ver Historial Completo
            </Link>
          </div>

          <div v-if="loadingOffline" class="p-10 text-center">
            <div class="inline-flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900">
              <span class="text-xl">⏳</span>
              <span class="text-sm font-bold text-slate-600 dark:text-slate-300">Cargando resumen local...</span>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[760px]">
              <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                  <th class="th-style">Folio</th>
                  <th class="th-style">Fecha Emisión</th>
                  <th class="th-style">Tipo Póliza</th>
                  <th class="th-style text-right">Monto</th>
                  <th class="th-style text-center">Estado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                <tr
                  v-for="p in activeStats.lastPolicies"
                  :key="p.id"
                  class="hover:bg-rose-50/30 dark:hover:bg-rose-900/5 transition-colors group"
                >
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <span class="font-mono font-black text-[#9F223C] dark:text-rose-400 text-sm">#{{ p.folio }}</span>
                      <span
                        v-if="p.synced === false"
                        class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20"
                      >
                        Pendiente
                      </span>
                    </div>
                  </td>

                  <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 font-medium">
                    {{ p.movement_date }}
                  </td>

                  <td class="px-6 py-4">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-tighter bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">
                      {{ p.policy_type }}
                    </span>
                  </td>

                  <td class="px-6 py-4 text-right">
                    <div class="text-sm font-black text-slate-800 dark:text-slate-100">{{ money(p.total_debit) }}</div>
                  </td>

                  <td class="px-6 py-4 text-center">
                    <span :class="p.status === 'locked' ? 'badge-guinda' : 'badge-slate'">
                      {{ p.status }}
                    </span>
                  </td>
                </tr>

                <tr v-if="!activeStats.lastPolicies?.length">
                  <td colspan="5" class="py-16 text-center">
                    <div class="flex flex-col items-center">
                      <div class="w-20 h-20 rounded-[1.5rem] bg-slate-50 dark:bg-slate-900 flex items-center justify-center text-3xl mb-4">
                        📄
                      </div>
                      <p class="font-black text-slate-800 dark:text-white uppercase text-xs tracking-[0.2em]">
                        Sin movimientos recientes
                      </p>
                      <p class="text-slate-400 dark:text-slate-500 text-sm mt-2 font-medium">
                        Todavía no hay pólizas disponibles para mostrar en este panel.
                      </p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="lg:col-span-3 space-y-4">
          <h3 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] ml-2">Accesos Rápidos</h3>
          <div class="grid grid-cols-1 gap-3">
            <Link
              v-for="link in [
                { label: 'Empresa', icon: '🏢', route: 'app.company.index' },
                { label: 'Catálogo', icon: '📂', route: 'app.catalog.index' },
                { label: 'Pólizas', icon: '📝', route: 'app.policies.index' },
                { label: 'Reportes', icon: '📊', route: 'app.reports.unified' }
              ]"
              :key="link.label"
              :href="route(link.route)"
              class="nav-shortcut-card group"
            >
              <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 group-hover:bg-[#9F223C] transition-colors shadow-sm">
                <span class="text-lg group-hover:scale-110 transition-transform">{{ link.icon }}</span>
              </div>
              <span class="font-bold text-slate-700 dark:text-slate-300 group-hover:text-[#9F223C] dark:group-hover:text-rose-400 transition-colors">{{ link.label }}</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

* { font-family: 'Plus Jakarta Sans', sans-serif; }

.btn-gradient {
  background: linear-gradient(135deg, #9F223C 0%, #7A1E3A 100%);
}

.alert-card {
  @apply p-5 rounded-2xl border transition-all hover:shadow-lg hover:shadow-rose-900/5;
}

.stat-card {
  @apply bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/30 dark:shadow-none flex flex-col justify-between;
}

.stat-label {
  @apply text-[10px] font-black uppercase tracking-widest text-slate-400;
}

.th-style {
  @apply px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500;
}

.nav-shortcut-card {
  @apply flex items-center gap-4 p-3 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-md hover:border-[#9F223C]/30 transition-all active:scale-95;
}

.badge-guinda {
  @apply px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-rose-50 dark:bg-[#9F223C]/20 text-[#9F223C] dark:text-rose-400;
}

.badge-slate {
  @apply px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400;
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>