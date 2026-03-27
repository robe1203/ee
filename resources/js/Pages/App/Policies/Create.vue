<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CalculatorModal from '@/Components/CalculatorModal.vue'
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import { savePoliza } from '@/offline/db'
import { isOnline } from '@/utils/network' // 🔥 corregido

const props = defineProps({
  mode: { type: String, default: 'create' },
  policy: { type: Object, default: null },
  lines: { type: Array, default: null },
  accounts: Array,
  currentCompanyUuid: { type: String, default: null },
  nextFolio: Number,
  today: String,
  policyTypes: { type: Array, default: () => ['Diario','Ingreso','Compras','Egreso','Nóminas'] },
  policyStatuses: { type: Array, default: () => ['Borrador'] },
})

const page = usePage()
const isEdit = computed(() => props.mode === 'edit' && props.policy?.id)

const DRAFT_KEY = computed(() => isEdit.value ? `policy_draft_edit_${props.policy.id}` : `policy_draft_create`)

const defaultLines = () => ([
  { account_id: '', account_code: '', account_name: '', concept: '', debit: 0, credit: 0 },
  { account_id: '', account_code: '', account_name: '', concept: '', debit: 0, credit: 0 },
])

const form = useForm({
  folio: isEdit.value ? (props.policy?.folio ?? 1000) : (props.nextFolio ?? 1000),
  policy_type: isEdit.value ? (props.policy?.policy_type ?? '') : '',
  movement_date: isEdit.value ? (props.policy?.movement_date ?? props.today) : props.today,
  status: isEdit.value ? (props.policy?.status ?? 'Borrador') : 'Borrador',
  lines: props.lines?.length ? props.lines.map(l => ({
    account_id: l.account_id ?? '',
    account_code: l.account_code ?? '',
    account_name: l.account_name ?? '',
    concept: l.concept ?? '',
    debit: Number(l.debit ?? 0),
    credit: Number(l.credit ?? 0),
  })) : defaultLines(),
})

// Totales
const totalDebit = computed(() => form.lines.reduce((s,l)=> s + (Number(l.debit)||0), 0))
const totalCredit = computed(() => form.lines.reduce((s,l)=> s + (Number(l.credit)||0), 0))
const isBalanced = computed(() => Math.abs(totalDebit.value - totalCredit.value) < 0.01)

// =========================
// ACCIONES
// =========================
const addRow = () => form.lines.push({ account_id: '', account_code: '', account_name: '', concept: '', debit: 0, credit: 0 })

const removeRow = (i) => {
  form.lines.splice(i,1)
  if(form.lines.length === 0) addRow()
}

const onAccount = (i) => {
  const acc = props.accounts.find(a => String(a.id) === String(form.lines[i].account_id))
  if(acc) {
    form.lines[i].account_code = acc.code
    form.lines[i].account_name = acc.name
  }
}

const clearAllLines = () => {
  if(confirm('¿Borrar todos los movimientos?')) {
    form.lines = defaultLines()
    form.clearErrors()
  }
}

// =========================
// CALCULADORA
// =========================
const calcOpen = ref(false)
const calcRow = ref(null)
const calcField = ref('debit')

const openCalc = (i, field) => {
  calcRow.value = i
  calcField.value = field
  calcOpen.value = true
}

const applyCalc = (n) => {
  if(calcRow.value !== null) {
    form.lines[calcRow.value][calcField.value] = Number(n || 0).toFixed(2)
  }
}

// =========================
// DRAFT LOCAL
// =========================
const saveDraft = () => {
  try {
    const payload = {
      policy_type: form.policy_type,
      movement_date: form.movement_date,
      status: form.status,
      lines: form.lines,
      ts: Date.now(),
    }

    if (!isEdit.value) payload.folio = form.folio

    localStorage.setItem(DRAFT_KEY.value, JSON.stringify(payload))
  } catch {}
}

const loadDraft = () => {
  try {
    const raw = localStorage.getItem(DRAFT_KEY.value)
    if(!raw) return

    const d = JSON.parse(raw)

    form.policy_type = d.policy_type ?? form.policy_type
    form.movement_date = d.movement_date ?? form.movement_date
    form.status = d.status ?? form.status
    form.lines = Array.isArray(d.lines) && d.lines.length ? d.lines : form.lines

    if (!isEdit.value && d.folio !== undefined) {
      form.folio = d.folio
    }
  } catch {}
}

const clearDraft = () => localStorage.removeItem(DRAFT_KEY.value)

let draftTimer = null
watch(() => form.data(), () => {
  clearTimeout(draftTimer)
  draftTimer = setTimeout(saveDraft, 500)
}, { deep: true })

onMounted(() => {
  loadDraft()
  window.addEventListener('beforeunload', saveDraft)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', saveDraft)
})

// =========================
// SUBMIT (WHATSAPP MODE)
// =========================
const submit = async () => {
  const payload = {
    uuid: props.policy?.uuid ?? crypto.randomUUID(),
    company_uuid: props.currentCompanyUuid,
    folio: form.folio,
    policy_type: form.policy_type,
    movement_date: form.movement_date,
    status: form.status,
    lines: form.lines.map((line, index) => ({
      uuid: line.uuid ?? crypto.randomUUID(),
      account_id: line.account_id || null,
      account_code: line.account_code ?? '',
      account_name: line.account_name ?? '',
      concept: line.concept ?? '',
      debit: Number(line.debit || 0),
      credit: Number(line.credit || 0),
      sort: index + 1,
    })),
  }

  // 📴 OFFLINE PRIMERO (IMPORTANTE)
  if (!isOnline.value) {
    console.log('📴 Guardando offline SIN usar Inertia')

    try {
      await savePoliza(payload)

      alert("Póliza guardada sin internet 🔥")

      clearDraft()
      form.reset()
      form.lines = defaultLines()

    } catch (error) {
      console.error('Error guardando offline:', error)
      alert("Error al guardar offline")
    }

    return // 🚨 ESTO ES CLAVE (corta ejecución)
  }

  // 🌐 ONLINE (solo aquí usamos Inertia)
  try {
    const options = {
      preserveScroll: true,
      onSuccess: async () => {
        clearDraft()

        // guardar copia local
        await savePoliza({
          ...payload,
          id: props.policy?.id ?? null,
          synced: true,
        })
      },
    }

    if (isEdit.value) {
      form.put(route('app.policies.update', props.policy.id), options)
    } else {
      form.post(route('app.policies.store'), options)
    }

  } catch (error) {
    console.error('Error online:', error)
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <div v-if="!isOnline" class="bg-yellow-500 text-white text-center py-2 font-bold rounded-xl">
  📴 Modo sin conexión - los datos se guardarán localmente
</div>
    <div class="max-w-[98%] mx-auto py-6 animate-fade-in space-y-6 px-4">

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-black text-slate-900 dark:text-white tracking-tighter">
            {{ isEdit ? 'Editar Póliza' : 'Nueva Póliza Contable' }}
          </h1>
          <div class="flex items-center gap-2 text-slate-500 font-medium">
            <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
            Autoguardado local activado
          </div>
        </div>

        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 p-2 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-sm">
          <Link
            :href="route('app.policies.index')"
            class="px-6 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-black text-[11px] uppercase tracking-widest hover:bg-slate-200 transition-all"
          >
            Volver
          </Link>

          <button
            @click="submit"
            :disabled="!isBalanced || form.processing"
            class="px-8 py-2.5 rounded-2xl bg-slate-900 dark:bg-indigo-600 text-white font-black text-[11px] uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-lg disabled:opacity-30"
          >
            {{ isEdit ? 'Actualizar Cambios' : 'Registrar Póliza' }}
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 p-8">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-end">

          <div class="md:col-span-2 space-y-2">
            <label class="label-style">Folio</label>

            <input
              v-model="form.folio"
              class="input-header font-mono text-lg"
              :readonly="isEdit"
              :disabled="isEdit"
              :class="isEdit ? 'bg-slate-100 dark:bg-slate-900/70 text-slate-500 dark:text-slate-400 cursor-not-allowed' : ''"
            />
          </div>

          <div class="md:col-span-5 space-y-2">
            <label class="label-style">Clasificación</label>
            <div class="flex gap-1.5 p-1.5 bg-slate-100 dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-700">
              <button
                v-for="type in policyTypes"
                :key="type"
                type="button"
                @click="form.policy_type = type"
                :class="form.policy_type === type
                  ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200 dark:ring-slate-600'
                  : 'text-slate-500'"
                class="flex-1 py-2.5 rounded-xl font-black text-[9px] transition-all uppercase tracking-widest"
              >
                {{ type }}
              </button>
            </div>
          </div>

          <div class="md:col-span-3 space-y-2">
            <label class="label-style">Fecha Emisión</label>
            <input type="date" v-model="form.movement_date" class="input-header" />
          </div>

          <div class="md:col-span-2 space-y-2">
            <label class="label-style">Estatus</label>
            <select v-model="form.status" class="input-header appearance-none">
              <option v-for="s in policyStatuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[3rem] shadow-2xl shadow-slate-200/60 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-900 dark:bg-slate-950 text-white">
                <th class="th-style w-40">Código</th>
                <th class="th-style">Cuenta</th>
                <th class="th-style">Concepto</th>
                <th class="th-style w-52 text-right">Cargo</th>
                <th class="th-style w-52 text-right">Abono</th>
                <th class="th-style w-20 text-center"></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr
                v-for="(l,i) in form.lines"
                :key="i"
                class="group hover:bg-slate-50/50 dark:hover:bg-slate-900/20 transition-all"
              >
                <td class="p-3">
                  <input
                    v-model="l.account_code"
                    class="input-table font-black text-indigo-600 dark:text-indigo-400 bg-slate-50/50 dark:bg-slate-900/30"
                    readonly
                  />
                </td>

                <td class="p-3 min-w-[250px]">
                  <select v-model="l.account_id" class="input-table font-bold" @change="onAccount(i)">
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="a in accounts" :key="a.id" :value="a.id">
                      {{ a.code }} — {{ a.name }}
                    </option>
                  </select>
                </td>

                <td class="p-3">
                  <input v-model="l.concept" class="input-table" placeholder="Descripción..." />
                </td>

                <td class="p-3">
                  <div class="flex items-center gap-2">
                    <input
                      v-model="l.debit"
                      type="number"
                      step="0.01"
                      class="input-table text-right font-black flex-1"
                    />
                    <button
                      type="button"
                      @click="openCalc(i,'debit')"
                      class="ml-2 text-lg hover:scale-110 transition"
                      title="Calculadora"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 text-black dark:text-white"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="6" y="3" width="12" height="18" rx="2"/>
                        <rect x="8" y="5" width="8" height="3" rx="1"/>
                        <path d="M9 11h.01M12 11h.01M15 11h.01"/>
                        <path d="M9 14h.01M12 14h.01M15 14h.01"/>
                        <path d="M9 17h.01M12 17h.01M15 17h.01"/>
                      </svg>
                    </button>
                  </div>
                </td>

                <td class="p-3">
                  <div class="flex items-center gap-2">
                    <input
                      v-model="l.credit"
                      type="number"
                      step="0.01"
                      class="input-table text-right font-black flex-1 text-emerald-600 dark:text-emerald-400"
                    />
                    <button
                      type="button"
                      @click="openCalc(i,'credit')"
                      class="ml-2 text-lg hover:scale-110 transition"
                      title="Calculadora"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 text-black dark:text-white"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="6" y="3" width="12" height="18" rx="2"/>
                        <rect x="8" y="5" width="8" height="3" rx="1"/>
                        <path d="M9 11h.01M12 11h.01M15 11h.01"/>
                        <path d="M9 14h.01M12 14h.01M15 14h.01"/>
                        <path d="M9 17h.01M12 17h.01M15 17h.01"/>
                      </svg>
                    </button>
                  </div>
                </td>

                <td class="p-3 text-center">
                  <button
                    @click="removeRow(i)"
                    class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-all"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-8 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-6">
          <div class="flex gap-3">
            <button
              @click="addRow"
              class="px-6 py-3 rounded-2xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-black text-[10px] uppercase tracking-widest hover:border-slate-900 transition-all"
            >
              + Añadir Movimiento
            </button>

            <button
              @click="clearAllLines"
              class="px-6 py-3 rounded-2xl bg-rose-50 dark:bg-rose-900/20 text-rose-700 font-black text-[10px] uppercase tracking-widest hover:bg-rose-600 hover:text-white transition-all"
            >
              🧹 Limpiar Asiento
            </button>
          </div>

          <div class="flex items-center gap-8">
            <div
              :class="isBalanced ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
              class="px-6 py-3 rounded-3xl border flex items-center gap-3 transition-all font-black text-[11px] uppercase tracking-widest"
            >
              <div :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500'" class="w-2.5 h-2.5 rounded-full animate-pulse"></div>
              {{ isBalanced ? 'Balance Cuadrado' : 'Asiento Descuadrado' }}
            </div>

            <div class="flex gap-8 border-l pl-8 border-slate-200 dark:border-slate-700">
              <div class="text-right">
                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Cargos</p>
                <p class="text-2xl font-black">$ {{ totalDebit.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</p>
              </div>
              <div class="text-right">
                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Abonos</p>
                <p class="text-2xl font-black text-emerald-600">$ {{ totalCredit.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <CalculatorModal :show="calcOpen" @close="calcOpen=false" @apply="applyCalc" />
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
* { font-family: 'Plus Jakarta Sans', sans-serif; }

.label-style { @apply text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500 ml-1; }
.input-header { @apply w-full border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50 rounded-2xl p-4 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-bold text-slate-700 dark:text-white border; }
.th-style { @apply px-8 py-5 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400; }
.input-table { @apply w-full border-transparent bg-transparent rounded-xl px-4 py-3 focus:bg-white dark:focus:bg-slate-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm outline-none border dark:text-slate-200; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1); }

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>