<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  selectedFolio: { type: [Number, null], default: null },
  policies: { type: Array, default: () => [] },
  policy: { type: Object, default: null },
  lines: { type: Array, default: () => [] },
})

const q = ref(props.selectedFolio ? String(props.selectedFolio) : '')
const isModalOpen = ref(!!props.policy)
const isLoading = ref(false)

/* =========================
   MODAL REDIMENSIONABLE
========================= */
const modalWidth = ref(1000)
const modalHeight = ref(640)
const isResizing = ref(false)

const startResize = () => {
  isResizing.value = true
  document.body.style.cursor = 'nwse-resize'
  window.addEventListener('mousemove', handleResize)
  window.addEventListener('mouseup', stopResize)
}

const handleResize = (e) => {
  if (!isResizing.value) return
  modalWidth.value = Math.max(700, Math.min(e.clientX, window.innerWidth - 30))
  modalHeight.value = Math.max(450, Math.min(e.clientY, window.innerHeight - 30))
}

const stopResize = () => {
  isResizing.value = false
  document.body.style.cursor = 'default'
  window.removeEventListener('mousemove', handleResize)
  window.removeEventListener('mouseup', stopResize)
}

onUnmounted(stopResize)

watch(() => props.policy, (newVal) => {
  if (newVal) isModalOpen.value = true
})

/* =========================
   FORMATOS Y FILTROS
========================= */
const currency = (val) =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
  }).format(Number(val || 0))

const filteredPolicies = computed(() => {
  const s = (q.value || '').trim().toLowerCase()
  if (!s) return props.policies

  return props.policies.filter((p) =>
    String(p.folio).includes(s) ||
    String(p.policy_type || '').toLowerCase().includes(s) ||
    String(p.status || '').toLowerCase().includes(s) ||
    String(p.fecha || '').toLowerCase().includes(s)
  )
})

function goFolio(folio) {
  if (isLoading.value) return
  isLoading.value = true

  router.get(route('app.reports.unified'), { folio }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      isLoading.value = false
      isModalOpen.value = true
    }
  })
}

const totals = computed(() => {
  return (props.lines || []).reduce((acc, r) => {
    acc.debit += parseFloat(r.debit || 0)
    acc.credit += parseFloat(r.credit || 0)
    return acc
  }, { debit: 0, credit: 0 })
})

const isBalanced = computed(() => {
  const diff = Math.abs(totals.value.debit - totals.value.credit)
  return diff < 0.01
})

const closeModal = () => {
  isModalOpen.value = false
  router.get(route('app.reports.unified'), {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const statusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'registrada':
      return 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20'
    case 'borrador':
      return 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'
    case 'cancelado':
      return 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20'
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-[#F8FAFC] dark:bg-slate-950 p-4 md:p-8 animate-fade-in">
      
      <div class="max-w-[1500px] mx-auto mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <div class="w-1.5 h-12 bg-[#9F223C] rounded-full shadow-sm"></div>
          <div>
            <h1 class="text-3xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-none">
              Informes y Reportes
            </h1>
            <p class="text-slate-500 dark:text-slate-400 font-bold text-xs uppercase tracking-widest mt-1">
              Visualización unificada de movimientos contables
            </p>
          </div>
        </div>

        <div class="relative group">
          <input
            v-model="q"
            type="text"
            placeholder="Buscar por fecha, folio o estado..."
            class="w-full md:w-96 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-3.5 pl-12 focus:ring-4 focus:ring-[#9F223C]/10 focus:border-[#9F223C] transition-all outline-none font-bold text-sm border shadow-sm"
          />
          <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-[#9F223C] transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
        </div>
      </div>

      <div class="max-w-[1500px] mx-auto bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-xl shadow-slate-200/40 dark:shadow-none overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full text-left border-collapse min-w-[1100px]">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                <th class="th-style">Fecha</th>
                <th class="th-style">Folio</th>
                <th class="th-style">Tipo</th>
                <th class="th-style text-right">Cargo</th>
                <th class="th-style text-right">Abono</th>
                <th class="th-style text-center">Estado</th>
                <th class="th-style text-center">Acción</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
              <tr
                v-for="p in filteredPolicies"
                :key="p.id"
                @dblclick="goFolio(p.folio)"
                class="hover:bg-rose-50/20 dark:hover:bg-[#9F223C]/5 transition-all cursor-pointer select-none group"
                :class="{ 'opacity-60 pointer-events-none': isLoading }"
              >
                <td class="px-6 py-5 text-slate-700 dark:text-slate-300 font-bold text-sm">
                  {{ p.fecha || '-' }}
                </td>

                <td class="px-6 py-5">
                  <span class="font-black text-[#9F223C] dark:text-[#E04F6E] bg-[#9F223C]/5 px-3 py-1.5 rounded-lg text-sm border border-[#9F223C]/10">
                    #{{ p.folio }}
                  </span>
                </td>

                <td class="px-6 py-5">
                  <span class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-black text-slate-600 dark:text-slate-400 uppercase tracking-tighter">
                    {{ p.policy_type }}
                  </span>
                </td>

                <td class="px-6 py-5 text-right font-mono font-bold text-slate-900 dark:text-white text-sm">
                  {{ currency(p.total_debit) }}
                </td>

                <td class="px-6 py-5 text-right font-mono font-bold text-emerald-600 text-sm">
                  {{ currency(p.total_credit) }}
                </td>

                <td class="px-6 py-5 text-center">
                  <span :class="statusClass(p.status)" class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest">
                    {{ p.status }}
                  </span>
                </td>

                <td class="px-6 py-5 text-center">
                  <div class="flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-tighter text-[#9F223C] opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="w-1.5 h-1.5 bg-[#9F223C] rounded-full animate-pulse"></span>
                    Ver Detalle
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-md" @click="closeModal"></div>

        <div
          class="relative bg-white dark:bg-slate-900 shadow-[0_0_50px_rgba(0,0,0,0.3)] flex flex-col overflow-hidden animate-modal-in border border-slate-200 dark:border-slate-700 rounded-3xl"
          :style="{
            width: modalWidth + 'px',
            height: modalHeight + 'px',
            maxWidth: '95vw',
            maxHeight: '92vh'
          }"
        >
          <div class="h-14 flex items-center justify-between px-6 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 select-none">
            <div class="flex items-center gap-6">
              <div class="flex gap-2">
                <div @click="closeModal" class="w-3.5 h-3.5 rounded-full bg-[#FF5F57] hover:brightness-90 cursor-pointer transition-all flex items-center justify-center group">
                  <span class="text-[8px] opacity-0 group-hover:opacity-100 transition-opacity font-bold">×</span>
                </div>
                <div class="w-3.5 h-3.5 rounded-full bg-[#FEBC2E]"></div>
                <div class="w-3.5 h-3.5 rounded-full bg-[#28C840]"></div>
              </div>

              <div v-if="policy" class="flex items-center gap-4 border-l border-slate-300 dark:border-slate-600 pl-6">
                <h2 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest">
                  Detalle de Póliza <span class="text-[#9F223C]">#{{ policy.folio }}</span>
                </h2>
                <span :class="statusClass(policy.status)" class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest">
                  {{ policy.status }}
                </span>
              </div>
            </div>

            <button @click="closeModal" class="text-slate-400 hover:text-[#9F223C] transition-colors p-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <div class="px-8 py-6 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800/50">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-8">
              <div v-for="(val, label) in { 'Fecha': policy?.fecha, 'Folio': '#'+policy?.folio, 'Tipo': policy?.policy_type, 'Total Cargo': currency(policy?.total_debit), 'Total Abono': currency(policy?.total_credit) }" :key="label">
                <p class="text-[9px] uppercase font-black text-slate-400 tracking-widest mb-1">{{ label }}</p>
                <p class="font-bold text-slate-900 dark:text-white text-sm" :class="{ 'text-emerald-600': label === 'Total Abono' }">{{ val || '-' }}</p>
              </div>
            </div>
          </div>

          <div class="flex-1 overflow-auto custom-scrollbar bg-white dark:bg-slate-900 px-8 py-6">
            <table class="w-full">
              <thead>
                <tr class="text-left border-b-2 border-slate-100 dark:border-slate-800/50">
                  <th class="pb-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Cuenta Contable</th>
                  <th class="pb-4 text-[10px] font-black uppercase text-slate-400 tracking-widest">Concepto</th>
                  <th class="pb-4 text-[10px] font-black uppercase text-slate-400 tracking-widest text-right">Cargo</th>
                  <th class="pb-4 text-[10px] font-black uppercase text-slate-400 tracking-widest text-right">Abono</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                <tr v-for="r in lines" :key="r.id" class="group transition-colors">
                  <td class="py-4">
                    <div class="font-black text-[#9F223C] dark:text-[#E04F6E] text-xs mb-0.5">{{ r.code }}</div>
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">{{ r.account }}</div>
                  </td>
                  <td class="py-4 text-slate-600 dark:text-slate-400 text-xs font-bold">{{ r.concept }}</td>
                  <td class="py-4 text-right font-mono text-xs font-bold text-slate-900 dark:text-white">{{ currency(r.debit) }}</td>
                  <td class="py-4 text-right font-mono text-xs font-bold text-emerald-600">{{ currency(r.credit) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="px-8 py-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-between items-center">
            <div class="flex gap-10">
              <div v-for="(amount, label) in { 'Total Cargos': totals.debit, 'Total Abonos': totals.credit }" :key="label">
                <span class="block text-[9px] uppercase font-black text-slate-400 tracking-widest mb-1">{{ label }}</span>
                <span class="text-xl font-mono font-black" :class="label.includes('Abonos') ? 'text-emerald-600' : 'text-slate-900 dark:text-white'">
                  {{ currency(amount) }}
                </span>
              </div>
            </div>

            <div
              :class="isBalanced ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20 border-transparent' : 'bg-rose-500 text-white shadow-lg shadow-rose-500/20 border-transparent'"
              class="text-[10px] font-black px-6 py-3 rounded-xl border uppercase tracking-widest transition-all scale-100 hover:scale-105"
            >
              {{ isBalanced ? '✓ Póliza Cuadrada' : '⚠ Descuadre Detectado' }}
            </div>
          </div>

          <div @mousedown="startResize" class="absolute bottom-0 right-0 w-6 h-6 cursor-nwse-resize flex items-end justify-end p-1.5 opacity-30 hover:opacity-100 transition-opacity">
            <div class="w-1 h-1 bg-[#9F223C] rounded-full mb-0.5 mr-0.5"></div>
            <div class="w-1 h-1 bg-[#9F223C] rounded-full mb-1.5 mr-0.5"></div>
            <div class="w-1 h-1 bg-[#9F223C] rounded-full mb-0.5 mr-1.5"></div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');

:deep(*) { font-family: 'Plus Jakarta Sans', sans-serif; }

.th-style {
  @apply px-6 py-4 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em];
}

.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { @apply bg-slate-300 dark:bg-slate-700 rounded-full; }

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.9) translateY(40px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal-in { animation: modalIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.animate-fade-in { animation: fadeIn 0.6s ease; }
</style>