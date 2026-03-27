<script setup>
import { computed, reactive } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { exportTablePDF, exportTableExcel } from '@/utils/exporters'

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  items: { type: Array, default: () => [] },
  company: { type: Object, default: () => ({}) },
})

const form = reactive({
  from: props.filters?.from || '',
  to: props.filters?.to || '',
})

const search = reactive({ q: '' })

const money = (n) =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
  }).format(Number(n || 0))

function apply() {
  router.get(
    route('app.reports.libroDiario'),
    { from: form.from, to: form.to },
    { preserveState: true, preserveScroll: true }
  )
}

const flatRows = computed(() => {
  const rows = []
  ;(props.items || []).forEach((p) => {
    ;(p.lines || []).forEach((l) => {
      rows.push({
        Fecha: p.movement_date || '',
        Folio: p.folio || '',
        Tipo: p.policy_type || '',
        Codigo: l.account_code || '',
        Cuenta: l.account_name || '',
        Concepto: l.concept || '',
        Cargo: Number(l.debit || 0),
        Abono: Number(l.credit || 0),
      })
    })
  })
  return rows
})

const filteredRows = computed(() => {
  const q = search.q.toLowerCase()
  if (!q) return flatRows.value
  return flatRows.value.filter((r) => {
    return Object.values(r).some(val => String(val).toLowerCase().includes(q))
  })
})

const totalCargo = computed(() => filteredRows.value.reduce((sum, row) => sum + row.Cargo, 0))
const totalAbono = computed(() => filteredRows.value.reduce((sum, row) => sum + row.Abono, 0))

function toExcel() {
  exportTableExcel({
    sheetName: 'Libro_Diario',
    title: 'Libro Diario',
    company: props.company,
    rows: filteredRows.value,
    fileName: 'libro-diario',
  })
}

function toPDF() {
  const body = filteredRows.value.map((r) => [
    r.Fecha, r.Folio, r.Tipo, r.Codigo, r.Cuenta, r.Concepto, money(r.Cargo), money(r.Abono)
  ])
  body.push(['','','','','','TOTAL', money(totalCargo.value), money(totalAbono.value)])
  exportTablePDF({
    title: 'Libro Diario',
    company: props.company,
    head: [['Fecha', 'Folio', 'Tipo', 'Código', 'Cuenta', 'Concepto', 'Cargo', 'Abono']],
    body,
    fileName: 'libro-diario',
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto pt-4 px-4 sm:px-6 lg:px-8 pb-8 space-y-6 animate-fade-in">
      
      <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200/60 dark:border-slate-700/50 pb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
             <div class="w-2 h-8 bg-[#9F223C] rounded-full"></div>
             <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
               Libro Diario
             </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm font-medium ml-5">
            Reporte detallado de movimientos contables institucionales.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
          <button @click="toExcel" class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 hover:scale-[1.02] transition-all text-sm shadow-sm">
            Excel
          </button>
          <button @click="toPDF" class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-white shadow-lg shadow-rose-900/20 hover:scale-[1.02] active:scale-95 transition-all text-sm btn-gradient">
            Exportar PDF
          </button>
        </div>
      </header>

      <section class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-6 shadow-xl shadow-slate-200/30 dark:shadow-none">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#9F223C] mb-6 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-[#9F223C]"></span>
          Datos del Emisor
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="(val, label) in { 'Razón Social': company?.name, 'RFC': company?.rfc, 'Régimen': company?.regimen_fiscal, 'Dirección': company?.address }" :key="label">
            <dt class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ label }}</dt>
            <dd class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200 truncate">{{ val || '---' }}</dd>
          </div>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-4 shadow-sm flex flex-col sm:flex-row items-end gap-4">
          <div class="flex-1 w-full">
            <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Desde</label>
            <input v-model="form.from" type="date" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C]" />
          </div>
          <div class="flex-1 w-full">
            <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Hasta</label>
            <input v-model="form.to" type="date" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C]" />
          </div>
          <button @click="apply" class="w-full sm:w-auto px-8 py-2.5 rounded-xl bg-slate-900 dark:bg-slate-100 dark:text-slate-900 text-white font-bold hover:opacity-90 transition-all text-sm">
            Filtrar
          </button>
        </div>

        <div class="lg:col-span-4 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-4 shadow-sm">
          <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Búsqueda rápida</label>
          <div class="relative mt-1">
            <input v-model="search.q" type="text" placeholder="Concepto, cuenta, folio..." class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C] pr-10" />
            <span class="absolute right-3 top-2.5 opacity-30">🔍</span>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl overflow-hidden">
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead>
              <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                <th class="th-style">Fecha / Folio</th>
                <th class="th-style">Tipo</th>
                <th class="th-style">Cuenta Contable</th>
                <th class="th-style">Concepto</th>
                <th class="th-style text-right">Cargo</th>
                <th class="th-style text-right">Abono</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
              <tr v-for="(row, i) in filteredRows" :key="i" class="hover:bg-rose-50/30 dark:hover:bg-rose-900/5 transition-colors group">
                <td class="px-6 py-4">
                  <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ row.Fecha }}</div>
                  <div class="text-[10px] font-black text-[#9F223C] dark:text-rose-400 font-mono">{{ row.Folio }}</div>
                </td>
                <td class="px-6 py-4">
                  <span class="badge-slate">{{ row.Tipo }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="text-xs font-mono font-black text-slate-400">{{ row.Codigo }}</div>
                  <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ row.Cuenta }}</div>
                </td>
                <td class="px-6 py-4">
                  <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate font-medium">{{ row.Concepto }}</p>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ money(row.Cargo) }}</span>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-black text-[#9F223C] dark:text-rose-400">{{ money(row.Abono) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700">
          <div v-for="(row, i) in filteredRows" :key="i" class="p-6 space-y-4">
            <div class="flex justify-between items-start">
              <div>
                <span class="badge-guinda">{{ row.Folio }}</span>
                <div class="text-[10px] font-black text-slate-400 uppercase mt-1">{{ row.Fecha }}</div>
              </div>
              <span class="badge-slate">{{ row.Tipo }}</span>
            </div>
            <div>
              <div class="text-[10px] font-mono font-black text-slate-400">{{ row.Codigo }}</div>
              <div class="text-sm font-black text-slate-800 dark:text-slate-100">{{ row.Cuenta }}</div>
              <p class="text-xs text-slate-500 italic mt-1">"{{ row.Concepto }}"</p>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-2">
              <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-700">
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Cargo</div>
                <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ money(row.Cargo) }}</div>
              </div>
              <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-700">
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Abono</div>
                <div class="font-bold text-[#9F223C] dark:text-rose-400">{{ money(row.Abono) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-slate-900 dark:bg-black/40 p-8">
          <div class="flex flex-col md:flex-row justify-end items-center gap-8 md:gap-16">
            <div class="text-center md:text-right">
              <span class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Total Cargos</span>
              <span class="text-3xl font-black text-emerald-400 tracking-tight">{{ money(totalCargo) }}</span>
            </div>
            <div class="text-center md:text-right border-t md:border-t-0 md:border-l border-slate-800 pt-4 md:pt-0 md:pl-16 w-full md:w-auto">
              <span class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Total Abonos</span>
              <span class="text-3xl font-black text-rose-500 tracking-tight">{{ money(totalAbono) }}</span>
            </div>
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

.th-style {
  @apply px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500;
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

/* Estilos de inputs para que coincidan con el diseño */
input[type="date"], input[type="text"] {
  @apply transition-all duration-200 border-slate-200 dark:border-slate-700 shadow-sm focus:ring-opacity-50;
}
</style>