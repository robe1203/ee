<script setup>
import { reactive } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import { exportTablePDF, exportTableExcel } from '@/utils/exporters'

const props = defineProps({
  filters: { type: Object, default: () => ({ from: '', to: '' }) },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({ cargo: 0, abono: 0, deudor: 0, acreedor: 0 }) },
  company: { type: Object, default: () => ({}) },
})

const money = (n) =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
  }).format(Number(n || 0))

const apply = (e) => {
  e.preventDefault()
  const fd = new FormData(e.target)
  router.get(route('app.reports.balanza'), Object.fromEntries(fd), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

// Exportaciones
function toExcel() {
  exportTableExcel({
    sheetName: 'Balanza_Comprobacion',
    title: 'Balanza de Comprobación',
    company: props.company,
    rows: (props.rows || []).map(r => ({
      Código: r.codigo,
      Cuenta: r.cuenta,
      Cargo: r.cargo,
      Abono: r.abono,
      Deudor: r.deudor,
      Acreedor: r.acreedor,
    })),
    fileName: 'balanza-comprobacion',
  })
}

function toPDF() {
  const body = (props.rows || []).map(r => [
    r.codigo, r.cuenta, money(r.cargo), money(r.abono), money(r.deudor), money(r.acreedor)
  ])
  body.push(['TOTAL', '', money(props.totals?.cargo), money(props.totals?.abono), money(props.totals?.deudor), money(props.totals?.acreedor)])

  exportTablePDF({
    title: 'Balanza de Comprobación',
    company: props.company,
    head: [['Código', 'Cuenta', 'Cargo', 'Abono', 'Deudor', 'Acreedor']],
    body,
    fileName: 'balanza-comprobacion',
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
               Balanza de Comprobación
             </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm font-medium ml-5">
            Resumen consolidado de saldos y movimientos por periodo.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
          <button 
            @click="toExcel" 
            :disabled="!rows.length"
            class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 hover:scale-[1.02] transition-all text-sm disabled:opacity-50"
          >
            Excel
          </button>
          <button 
            @click="toPDF" 
            :disabled="!rows.length"
            class="w-full sm:w-auto text-center px-5 py-2.5 rounded-xl font-bold text-white shadow-lg shadow-rose-900/20 hover:scale-[1.02] active:scale-95 transition-all text-sm btn-gradient disabled:opacity-50"
          >
            Exportar PDF
          </button>
        </div>
      </header>

      <section class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-6 shadow-xl shadow-slate-200/30 dark:shadow-none">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-[#9F223C] mb-6 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-[#9F223C]"></span>
          Datos de la Entidad
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="(val, label) in { 'Empresa': company?.name, 'RFC': company?.rfc, 'Régimen': company?.regimen_fiscal, 'Ubicación': company?.address }" :key="label">
            <dt class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ label }}</dt>
            <dd class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200 truncate">{{ val || '---' }}</dd>
          </div>
        </div>
      </section>

      <form 
        @submit="apply"
        class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-4 shadow-sm flex flex-col md:flex-row items-end gap-4"
      >
        <div class="flex-1 w-full">
          <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Desde</label>
          <input type="date" name="from" :value="filters.from" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C]" />
        </div>
        <div class="flex-1 w-full">
          <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Hasta</label>
          <input type="date" name="to" :value="filters.to" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C]" />
        </div>
        <button type="submit" class="w-full md:w-auto px-10 py-2.5 rounded-xl bg-slate-900 dark:bg-slate-100 dark:text-slate-900 text-white font-bold hover:opacity-90 transition-all text-sm">
          Filtrar Balanza
        </button>
      </form>

      <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl overflow-hidden">
        <div class="hidden lg:block overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead>
              <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                <th class="th-style">Código / Cuenta</th>
                <th class="th-style text-right">Cargo</th>
                <th class="th-style text-right">Abono</th>
                <th class="th-style text-right">Saldo Deudor</th>
                <th class="th-style text-right">Saldo Acreedor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
              <tr v-if="!rows.length">
                <td colspan="5" class="p-16 text-center">
                  <div class="text-slate-300 dark:text-slate-600 mb-2">🔍</div>
                  <p class="text-slate-400 text-sm font-medium italic">No se encontraron registros para este periodo</p>
                </td>
              </tr>
              <tr v-for="(r, i) in rows" :key="i" class="hover:bg-rose-50/30 dark:hover:bg-rose-900/5 transition-colors group">
                <td class="px-6 py-4">
                  <div class="text-xs font-mono font-black text-[#9F223C] dark:text-rose-400">{{ r.codigo }}</div>
                  <div class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ r.cuenta }}</div>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-medium text-slate-500 font-mono">{{ money(r.cargo) }}</span>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-medium text-slate-500 font-mono">{{ money(r.abono) }}</span>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ money(r.deudor) }}</span>
                </td>
                <td class="px-6 py-4 text-right">
                  <span class="text-sm font-black text-[#9F223C] dark:text-rose-400 font-mono">{{ money(r.acreedor) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="lg:hidden divide-y divide-slate-100 dark:divide-slate-700">
          <div v-for="(r, i) in rows" :key="i" class="p-6 space-y-4">
            <div class="flex items-center gap-3">
              <span class="badge-guinda font-mono">{{ r.codigo }}</span>
              <h3 class="font-black text-slate-800 dark:text-white text-sm">{{ r.cuenta }}</h3>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-700">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Movimientos</p>
                <div class="flex flex-col text-[11px] font-mono">
                  <span class="text-emerald-600">C: {{ money(r.cargo) }}</span>
                  <span class="text-rose-600">A: {{ money(r.abono) }}</span>
                </div>
              </div>
              <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-2xl border border-slate-100 dark:border-slate-700">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Saldos</p>
                <div class="flex flex-col text-[11px] font-mono font-bold">
                  <span class="text-emerald-600">D: {{ money(r.deudor) }}</span>
                  <span class="text-rose-600">A: {{ money(r.acreedor) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-slate-900 dark:bg-black/40 p-8">
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-right">
            <div>
              <span class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Σ Cargos</span>
              <span class="text-lg font-mono text-slate-300">{{ money(totals.cargo) }}</span>
            </div>
            <div>
              <span class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Σ Abonos</span>
              <span class="text-lg font-mono text-slate-300">{{ money(totals.abono) }}</span>
            </div>
            <div class="border-t lg:border-t-0 lg:border-l border-slate-800 pt-4 lg:pt-0">
              <span class="block text-[10px] font-black text-emerald-500 uppercase tracking-widest">Saldo Deudor</span>
              <span class="text-xl font-black text-emerald-400 tracking-tight">{{ money(totals.deudor) }}</span>
            </div>
            <div class="border-t lg:border-t-0 lg:border-l border-slate-800 pt-4 lg:pt-0">
              <span class="block text-[10px] font-black text-rose-500 uppercase tracking-widest">Saldo Acreedor</span>
              <span class="text-xl font-black text-rose-500 tracking-tight">{{ money(totals.acreedor) }}</span>
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

.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

input[type="date"] {
  @apply transition-all duration-200 border-slate-200 dark:border-slate-700 shadow-sm focus:ring-opacity-50;
}
</style>