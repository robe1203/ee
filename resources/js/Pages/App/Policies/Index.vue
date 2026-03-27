<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, ref, onMounted, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { getPolizas, savePoliza } from '@/offline/db'
import { isOnline } from '@/utils/network'

const props = defineProps({
  policies: Object,
  currentCompanyUuid: { type: String, default: null },
})
const page = usePage()

const q = ref('')
const localPolicies = ref([])
const loadingOffline = ref(false)

// ✅ Normaliza texto: minúsculas + sin acentos
const normalizeText = (val) => {
  const s = String(val ?? '').toLowerCase()
  if (typeof s.normalize !== 'function') return s
  return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

// ✅ Mapeo semántico de estatus
const statusMap = {
  locked: 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
  draft: 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
  canceled: 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
}

const statusLabels = {
  locked: 'Cerrada',
  draft: 'Borrador',
  canceled: 'Cancelada',
}

// ✅ Datos base según conexión
const serverPolicies = computed(() => props.policies?.data ?? [])

const effectivePolicies = computed(() => {
  return isOnline.value ? serverPolicies.value : localPolicies.value
})

// ✅ Total mostrado
const totalPolicies = computed(() => {
  if (isOnline.value) return props.policies?.total ?? serverPolicies.value.length
  return localPolicies.value.length
})

// ✅ Filtrado
const filteredPolicies = computed(() => {
  const term = normalizeText(q.value).trim()
  const source = effectivePolicies.value ?? []

  if (!term) return source

  return source.filter((p) => {
    const folio = normalizeText(p.folio)
    const date = normalizeText(p.movement_date)
    const type = normalizeText(p.policy_type)
    const status = normalizeText(statusLabels[p.status] || p.status)

    return (
      folio.includes(term) ||
      date.includes(term) ||
      type.includes(term) ||
      status.includes(term)
    )
  })
})

// ✅ Marca visual para pendientes offline
const isPendingSync = (p) => {
  return p?.synced === false || p?.pending === true
}

// ✅ Cargar desde IndexedDB
async function loadOfflinePolicies() {
  loadingOffline.value = true
  try {
    const data = await getPolizas()
    localPolicies.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error cargando pólizas offline:', error)
    localPolicies.value = []
  } finally {
    loadingOffline.value = false
  }
}

// ✅ Guardar copia local de lo que viene del servidor
async function cacheServerPolicies() {
  try {
    const rows = serverPolicies.value ?? []

    for (const p of rows) {
      await savePoliza({
        id: p.id,
        uuid: p.uuid,
        company_uuid: props.currentCompanyUuid,
        folio: p.folio,
        policy_type: p.policy_type,
        movement_date: p.movement_date,
        status: p.status,
        lines: p.lines ?? [],
        synced: true,
      })
    }
  } catch (error) {
    console.error('Error guardando copia offline de pólizas:', error)
  }
}

// ✅ Eliminar
function destroyPolicy(p) {
  if (!isOnline.value) {
    alert('Sin internet no se puede eliminar una póliza del servidor todavía ⚠️')
    return
  }

  const label = `#${p.folio} (${statusLabels[p.status] || p.status})`
  if (!confirm(`¿Seguro que deseas eliminar la póliza ${label}?`)) return

  router.delete(route('app.policies.destroy', p.id), {
    preserveScroll: true,
  })
}

// ✅ Editar offline controlado
function canEdit(p) {
  if (!isOnline.value) return false
  return !isPendingSync(p)
}

onMounted(async () => {
  if (isOnline.value) {
    await cacheServerPolicies()
  } else {
    await loadOfflinePolicies()
  }
})

// ✅ Cuando cambia internet
watch(isOnline, async (online) => {
  if (online) {
    await cacheServerPolicies()
  } else {
    await loadOfflinePolicies()
  }
})

// ✅ Si cambian props del servidor, vuelve a cachear
watch(
  () => props.policies,
  async () => {
    if (isOnline.value) {
      await cacheServerPolicies()
    }
  },
  { deep: true }
)
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-[1400px] mx-auto pt-4 px-4 md:px-8 pb-12 animate-fade-in space-y-8">

      <!-- Estado de conexión -->
      <div
        v-if="!isOnline"
        class="rounded-[1.5rem] bg-amber-500 text-white shadow-lg border-none px-5 py-4 flex items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div class="bg-white/20 p-2 rounded-xl">📴</div>
          <div>
            <p class="font-black text-sm tracking-tight">Modo sin conexión</p>
            <p class="text-xs font-medium text-white/90">
              Estás viendo datos locales. Las pólizas nuevas quedarán pendientes hasta que regrese el internet.
            </p>
          </div>
        </div>
      </div>

      <header class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-slate-200/60 dark:border-slate-700/50 pb-8">
        <div>
          <div class="flex items-center gap-3">
            <div class="w-2 h-10 bg-[#9F223C] rounded-full"></div>
            <h1 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white tracking-tighter uppercase">Pólizas</h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 font-medium mt-2 flex items-center gap-2 ml-5">
            Gestión centralizada de asientos y movimientos contables por periodo.
          </p>
        </div>

        <Link
          :href="route('app.policies.create')"
          class="group flex items-center justify-center gap-3 px-8 py-4 rounded-[2rem] font-black text-white shadow-xl shadow-rose-900/20 transition-all hover:scale-[1.03] active:scale-95 uppercase text-[11px] tracking-[0.15em] btn-gradient"
        >
          <svg class="w-5 h-5 transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/>
          </svg>
          Nueva Póliza
        </Link>
      </header>

      <transition-group name="slide-fade">
        <div
          v-if="page.props.flash?.success"
          key="success"
          class="p-4 rounded-[1.5rem] bg-emerald-600 text-white shadow-lg shadow-emerald-900/20 border-none flex items-center gap-4"
        >
          <div class="bg-white/20 p-2 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <span class="font-bold text-sm tracking-tight">{{ page.props.flash.success }}</span>
        </div>

        <div
          v-if="page.props.errors?.error"
          key="error"
          class="p-4 rounded-[1.5rem] bg-[#9F223C] text-white shadow-lg shadow-rose-900/20 border-none flex items-center gap-4"
        >
          <div class="bg-white/20 p-2 rounded-xl">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <span class="font-bold text-sm tracking-tight">{{ page.props.errors.error }}</span>
        </div>
      </transition-group>

      <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl shadow-slate-200/40 dark:shadow-none border border-slate-100 dark:border-slate-700 p-2">
        <div class="flex flex-col md:flex-row md:items-center gap-2">
          <div class="flex-1 relative group">
            <div class="absolute left-6 top-1/2 -translate-y-1/2">
              <svg class="w-5 h-5 text-slate-400 group-focus-within:text-[#9F223C] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
              </svg>
            </div>

            <input
              v-model="q"
              type="text"
              class="w-full pl-14 pr-12 py-5 rounded-[2rem] bg-slate-50/50 dark:bg-slate-900/50 border-none focus:ring-4 focus:ring-[#9F223C]/10 transition-all text-sm font-bold text-slate-700 dark:text-white placeholder:text-slate-400 placeholder:font-medium"
              placeholder="Buscar por folio, fecha o tipo de movimiento..."
            />

            <button
              v-if="q"
              @click="q=''"
              class="absolute right-6 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-500 hover:bg-rose-500 hover:text-white transition-all"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-2 md:py-0 text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 border-l border-slate-100 dark:border-slate-700 hidden md:block">
            <span class="block opacity-50">Resultados</span>
            <span class="text-slate-900 dark:text-white text-sm tracking-tighter">
              {{ filteredPolicies.length }} / {{ totalPolicies }}
            </span>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[3rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/80 dark:bg-slate-900/50">
                <th class="th-style">Folio</th>
                <th class="th-style">Tipo</th>
                <th class="th-style">Fecha</th>
                <th class="th-style">Estado</th>
                <th class="th-style">Sync</th>
                <th class="th-style text-right">Gestión</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr
                v-for="p in filteredPolicies"
                :key="p.id ?? `offline-${p.folio}-${p.movement_date}`"
                class="group hover:bg-rose-50/30 dark:hover:bg-rose-950/5 transition-all"
              >
                <td class="px-8 py-6">
                  <div class="flex items-center gap-3">
                    <div class="w-1 h-6 bg-slate-200 dark:bg-slate-700 rounded-full group-hover:bg-[#9F223C] transition-colors"></div>
                    <span class="font-black text-slate-900 dark:text-white text-lg tracking-tighter">#{{ p.folio }}</span>
                  </div>
                </td>

                <td class="px-8 py-6">
                  <span class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-900 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest border border-slate-200 dark:border-slate-700">
                    {{ p.policy_type }}
                  </span>
                </td>

                <td class="px-8 py-6">
                  <span class="text-slate-500 dark:text-slate-400 font-bold text-sm font-mono tracking-tight">
                    {{ p.movement_date }}
                  </span>
                </td>

                <td class="px-8 py-6">
                  <span :class="['px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] border transition-colors', statusMap[p.status] || 'bg-slate-50 text-slate-600 border-slate-100']">
                    {{ statusLabels[p.status] || p.status }}
                  </span>
                </td>

                <td class="px-8 py-6">
                  <span
                    v-if="isPendingSync(p)"
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] border bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20"
                  >
                    Pendiente
                  </span>

                  <span
                    v-else
                    class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.15em] border bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20"
                  >
                    OK
                  </span>
                </td>

                <td class="px-8 py-6 text-right">
                  <div class="inline-flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity translate-x-4 group-hover:translate-x-0">

                    <Link
                      v-if="canEdit(p)"
                      :href="route('app.policies.edit', p.id)"
                      class="p-2.5 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-all"
                      title="Editar Póliza"
                    >
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </Link>

                    <button
                      v-else
                      class="p-2.5 rounded-xl text-slate-300 cursor-not-allowed"
                      title="No disponible sin conexión"
                    >
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>

                    <button
                      @click="destroyPolicy(p)"
                      :disabled="!isOnline"
                      class="p-2.5 rounded-xl text-slate-400 hover:text-[#9F223C] hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all disabled:text-slate-300 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                      title="Eliminar Póliza"
                    >
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="loadingOffline">
                <td colspan="6" class="py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-900 flex items-center justify-center text-2xl mb-4">
                      ⏳
                    </div>
                    <p class="font-black text-slate-700 dark:text-slate-200 uppercase text-xs tracking-[0.2em]">
                      Cargando datos locales
                    </p>
                  </div>
                </td>
              </tr>

              <tr v-else-if="filteredPolicies.length === 0">
                <td colspan="6" class="py-32 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-24 h-24 bg-slate-50 dark:bg-slate-900 rounded-[2rem] flex items-center justify-center text-4xl mb-6 shadow-inner">📁</div>
                    <p class="font-black text-slate-800 dark:text-white uppercase text-xs tracking-[0.2em]">Sin registros encontrados</p>
                    <p class="text-slate-400 dark:text-slate-500 text-sm mt-2 font-medium max-w-xs mx-auto">
                      Prueba ajustando los términos de búsqueda o crea una póliza nueva para comenzar.
                    </p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer
          v-if="isOnline && policies?.links?.length"
          class="p-8 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700/50"
        >
          <div class="flex flex-wrap justify-center gap-2">
            <template v-for="(link, k) in policies.links" :key="k">
              <div
                v-if="link.url === null"
                v-html="link.label"
                class="px-5 py-2.5 text-slate-300 dark:text-slate-600 text-[10px] font-black uppercase tracking-widest pointer-events-none select-none"
              />

              <Link
                v-else
                :href="link.url"
                v-html="link.label"
                class="px-6 py-3 rounded-2xl text-[10px] font-black transition-all uppercase tracking-widest shadow-sm"
                :class="link.active
                  ? 'bg-slate-900 dark:bg-[#9F223C] text-white ring-4 ring-slate-900/10 dark:ring-[#9F223C]/20'
                  : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-[#9F223C] hover:text-[#9F223C]'"
              />
            </template>
          </div>
        </footer>

        <footer
          v-else
          class="p-8 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700/50 text-center"
        >
          <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.25em]">
            En modo offline no se usa paginación del servidor
          </p>
        </footer>
      </div>

      <footer class="flex flex-col md:flex-row items-center justify-between px-6 gap-4">
        <div class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.3em] flex items-center gap-4">
          <span class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800">v2.4.0</span>
          <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
          <span>{{ totalPolicies }} Pólizas en total</span>
        </div>

        <div class="text-[10px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-widest italic">
          Las pólizas cerradas no permiten edición de asientos.
        </div>
      </footer>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

* { font-family: 'Plus Jakarta Sans', sans-serif; }

.btn-gradient {
  background: linear-gradient(135deg, #9F223C 0%, #7A1E3A 100%);
}

.th-style {
  @apply px-8 py-5 text-[10px] font-black uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500;
}

.slide-fade-enter-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-fade-enter-from {
  transform: translateY(-20px);
  opacity: 0;
}

.animate-fade-in {
  animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.overflow-x-auto {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}
</style>