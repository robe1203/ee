<script setup>
import { reactive } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import { exportTablePDF, exportTableExcel } from '@/utils/exporters'

const props = defineProps({
  filters: { type: Object, default: () => ({ to: '' }) },
  activos: { type: Array, default: () => [] },
  pasivos: { type: Array, default: () => [] },
  capital: { type: Array, default: () => [] },
  totals: {
    type: Object,
    default: () => ({
      activos: 0,
      pasivos: 0,
      capital: 0,
      pasivo_capital: 0,
    }),
  },
  company: { type: Object, default: () => ({}) },
})

const money = (n) =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
  }).format(Number(n || 0))

function apply(e) {
  e.preventDefault()
  const fd = new FormData(e.target)
  router.get(route('app.reports.balanceGeneral'), Object.fromEntries(fd), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function toExcel() {
  exportTableExcel({
    sheetName: 'Balance_General',
    title: 'Balance General',
    company: props.company,
    rows: [
      ...(props.activos || []).map(r => ({ Grupo: 'Activo', Código: r.code, Cuenta: r.name, Saldo: r.saldo })),
      ...(props.pasivos || []).map(r => ({ Grupo: 'Pasivo', Código: r.code, Cuenta: r.name, Saldo: r.saldo })),
      ...(props.capital || []).map(r => ({ Grupo: 'Capital', Código: r.code, Cuenta: r.name, Saldo: r.saldo })),
    ],
    fileName: 'balance-general',
  })
}

function toPDF() {
  const body = []
  body.push([{ content: 'ACTIVOS', styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }, '', '', money(props.totals?.activos || 0)])
  ;(props.activos || []).forEach(r => body.push([r.code, r.name, '', money(r.saldo)]))
  body.push([{ content: 'PASIVOS', styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }, '', '', money(props.totals?.pasivos || 0)])
  ;(props.pasivos || []).forEach(r => body.push([r.code, r.name, '', money(r.saldo)]))
  body.push([{ content: 'CAPITAL', styles: { fontStyle: 'bold', fillColor: [240, 240, 240] } }, '', '', money(props.totals?.capital || 0)])
  ;(props.capital || []).forEach(r => body.push([r.code, r.name, '', money(r.saldo)]))
  body.push([{ content: 'TOTAL PASIVO + CAPITAL', styles: { fontStyle: 'bold', fillColor: [159, 34, 60], textColor: [255, 255, 255] } }, '', '', money(props.totals?.pasivo_capital || 0)])

  exportTablePDF({
    title: 'Balance General',
    company: props.company,
    head: [['Código', 'Cuenta', '', 'Saldo']],
    body,
    fileName: 'balance-general',
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
               Balance General
             </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm font-medium ml-5 italic">
            Estado de posición financiera al corte solicitado.
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
          Identificación Fiscal
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="(val, label) in { 'Entidad': company?.name, 'RFC': company?.rfc, 'Régimen': company?.regimen_fiscal, 'Domicilio': company?.address }" :key="label">
            <dt class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ label }}</dt>
            <dd class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200 truncate leading-tight">{{ val || '---' }}</dd>
          </div>
        </div>
      </section>

      <form @submit="apply" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 p-4 shadow-sm flex flex-col md:flex-row items-end gap-4">
        <div class="flex-1 w-full">
          <label class="text-[10px] font-black text-slate-400 ml-2 uppercase tracking-widest">Fecha de Corte (Al día)</label>
          <input type="date" name="to" :value="filters?.to || ''" class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 text-sm focus:border-[#9F223C] focus:ring-[#9F223C]" />
        </div>
        <button type="submit" class="w-full md:w-auto px-10 py-2.5 rounded-xl bg-slate-900 dark:bg-slate-100 dark:text-slate-900 text-white font-bold hover:opacity-90 transition-all text-sm">
          Generar Reporte
        </button>
      </form>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 overflow-hidden shadow-sm">
          <div class="bg-emerald-50/50 dark:bg-emerald-900/10 p-5 border-b dark:border-slate-700 flex justify-between items-center">
            <h3 class="text-lg font-black text-emerald-800 dark:text-emerald-400 tracking-tight">Activo</h3>
            <span class="text-[10px] font-black bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded tracking-widest">1000</span>
          </div>
          <div class="p-6 space-y-4">
            <div v-for="(r, i) in activos" :key="i" class="flex justify-between items-end group">
              <div class="flex flex-col min-w-0 pr-4">
                <span class="text-[9px] font-mono font-black text-slate-400 uppercase leading-none mb-1">{{ r.code }}</span>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 truncate">{{ r.name }}</span>
              </div>
              <div class="flex-1 border-b border-dotted border-slate-200 dark:border-slate-700 mb-1 mx-2 min-w-[20px]"></div>
              <span class="font-mono text-sm font-bold text-slate-600 dark:text-slate-400">{{ money(r.saldo) }}</span>
            </div>
            <div v-if="!activos.length" class="py-8 text-center text-slate-400 italic text-xs">Sin movimientos registrados</div>
          </div>
          <div class="bg-slate-50 dark:bg-slate-900/30 p-5 border-t dark:border-slate-700 flex justify-between items-center">
            <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Total Activos</span>
            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ money(totals?.activos) }}</span>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 overflow-hidden shadow-sm">
          <div class="bg-rose-50/50 dark:bg-rose-900/10 p-5 border-b dark:border-slate-700 flex justify-between items-center">
            <h3 class="text-lg font-black text-rose-800 dark:text-rose-400 tracking-tight">Pasivo</h3>
            <span class="text-[10px] font-black bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 px-2 py-1 rounded tracking-widest">2000</span>
          </div>
          <div class="p-6 space-y-4">
            <div v-for="(r, i) in pasivos" :key="i" class="flex justify-between items-end group">
              <div class="flex flex-col min-w-0 pr-4">
                <span class="text-[9px] font-mono font-black text-slate-400 uppercase leading-none mb-1">{{ r.code }}</span>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 truncate">{{ r.name }}</span>
              </div>
              <div class="flex-1 border-b border-dotted border-slate-200 dark:border-slate-700 mb-1 mx-2 min-w-[20px]"></div>
              <span class="font-mono text-sm font-bold text-slate-600 dark:text-slate-400">{{ money(r.saldo) }}</span>
            </div>
            <div v-if="!pasivos.length" class="py-8 text-center text-slate-400 italic text-xs">Sin movimientos registrados</div>
          </div>
          <div class="bg-slate-50 dark:bg-slate-900/30 p-5 border-t dark:border-slate-700 flex justify-between items-center">
            <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Total Pasivos</span>
            <span class="text-xl font-black text-rose-600 dark:text-rose-400">{{ money(totals?.pasivos) }}</span>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 overflow-hidden shadow-sm">
          <div class="bg-blue-50/50 dark:bg-blue-900/10 p-5 border-b dark:border-slate-700 flex justify-between items-center">
            <h3 class="text-lg font-black text-blue-800 dark:text-blue-400 tracking-tight">Capital</h3>
            <span class="text-[10px] font-black bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-2 py-1 rounded tracking-widest">3000</span>
          </div>
          <div class="p-6 space-y-4">
            <div v-for="(r, i) in capital" :key="i" class="flex justify-between items-end group">
              <div class="flex flex-col min-w-0 pr-4">
                <span class="text-[9px] font-mono font-black text-slate-400 uppercase leading-none mb-1">{{ r.code }}</span>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 truncate">{{ r.name }}</span>
              </div>
              <div class="flex-1 border-b border-dotted border-slate-200 dark:border-slate-700 mb-1 mx-2 min-w-[20px]"></div>
              <span class="font-mono text-sm font-bold text-slate-600 dark:text-slate-400">{{ money(r.saldo) }}</span>
            </div>
            <div v-if="!capital.length" class="py-8 text-center text-slate-400 italic text-xs">Sin movimientos registrados</div>
          </div>
          <div class="bg-slate-50 dark:bg-slate-900/30 p-5 border-t dark:border-slate-700 flex justify-between items-center">
            <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Total Capital</span>
            <span class="text-xl font-black text-blue-600 dark:text-blue-400">{{ money(totals?.capital) }}</span>
          </div>
        </div>

      </div>

      <div class="bg-slate-900 dark:bg-black text-white rounded-[2rem] p-8 shadow-2xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-80 h-80 bg-rose-600/10 rounded-full -mr-32 -mt-32 blur-[100px] transition-all group-hover:bg-rose-600/20"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
          <div class="flex items-center gap-6">
            <div class="hidden sm:flex w-16 h-16 rounded-2xl bg-white/5 border border-white/10 items-center justify-center text-3xl">
              ⚖️
            </div>
            <div class="text-center md:text-left">
              <h4 class="text-slate-500 text-[10px] font-black uppercase tracking-[0.3em] mb-1">Resultado del Ejercicio</h4>
              <div class="text-2xl sm:text-3xl font-black tracking-tight">Pasivo + Capital Contable</div>
            </div>
          </div>
          
          <div class="flex flex-col items-center md:items-end">
            <span class="text-4xl sm:text-6xl font-black text-rose-500 tracking-tighter drop-shadow-lg">
              {{ money(totals?.pasivo_capital) }}
            </span>
            <div class="mt-2 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Cuadrado con Activo</span>
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

.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

input[type="date"] {
  @apply transition-all duration-200 border-slate-200 dark:border-slate-700 shadow-sm focus:ring-opacity-50 font-bold;
}
</style>