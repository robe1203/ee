<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed, onMounted, ref, watch } from 'vue'
import satAccounts from '@/data/sat_accounts.json'
import { isOnline } from '@/utils/network'
import {
  getAccountsByCompany,
  getActiveCompanyUuid,
  saveAccount,
  deleteAccount,
  upsertAccountsFromServer,
} from '@/offline/db'

const props = defineProps({
  accounts: { type: Array, default: () => [] },
  currentCompanyUuid: { type: String, default: null },
  currentCompanyId: { type: [Number, String, null], default: null },
})

const accounts = ref([])
const companyUuid = ref(props.currentCompanyUuid ?? null)
const satQuery = ref('')
const showSatList = ref(false)
const form = ref({
  uuid: null,
  code: '',
  name: '',
  nature: 'D',
})

const satFiltered = computed(() => {
  const term = satQuery.value.trim().toLowerCase()
  if (term.length < 2) return []
  return satAccounts
    .filter(a => (a.code || '').toLowerCase().includes(term) || (a.name || '').toLowerCase().includes(term))
    .slice(0, 30)
})

function resetForm() {
  form.value = { uuid: null, code: '', name: '', nature: 'D' }
}

function pickSatAccount(a) {
  form.value.code = a.code
  form.value.name = a.name
  showSatList.value = false
  satQuery.value = ''
}

function onSatBlur() {
  setTimeout(() => (showSatList.value = false), 150)
}

async function loadAccounts() {
  companyUuid.value = companyUuid.value || await getActiveCompanyUuid()
  accounts.value = await getAccountsByCompany(companyUuid.value)
}

async function hydrateFromServer() {
  if (!props.currentCompanyUuid || !props.accounts?.length) return
  await upsertAccountsFromServer(props.currentCompanyUuid, props.accounts)
  await loadAccounts()
}

async function submit() {
  if (!companyUuid.value) {
    alert('Selecciona una empresa primero.')
    return
  }

  if (!form.value.code || !form.value.name) {
    alert('Debes escribir código y nombre.')
    return
  }

  const duplicated = accounts.value.some(a => a.code === form.value.code && a.uuid !== form.value.uuid)
  if (duplicated) {
    alert('Ese código ya existe en el catálogo.')
    return
  }

  await saveAccount({
    uuid: form.value.uuid || crypto.randomUUID(),
    company_uuid: companyUuid.value,
    code: form.value.code,
    name: form.value.name,
    nature: form.value.nature,
    synced: false,
  })

  await loadAccounts()
  resetForm()
  alert(isOnline.value ? 'Cuenta guardada.' : 'Cuenta guardada sin internet. Se sincronizará después.')
}

async function destroyAccountByUuid(uuid) {
  if (!confirm('¿Eliminar esta cuenta del catálogo?')) return
  await deleteAccount(uuid)
  await loadAccounts()
}

onMounted(async () => {
  await hydrateFromServer()
  await loadAccounts()
})

watch(() => props.currentCompanyUuid, async (uuid) => {
  companyUuid.value = uuid
  await loadAccounts()
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      <div v-if="!isOnline" class="bg-amber-100 border border-amber-200 text-amber-800 px-4 py-3 rounded-2xl font-bold text-sm">
        Estás sin internet. Las cuentas que registres quedarán guardadas localmente y se subirán cuando vuelva la conexión.
      </div>

      <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-black text-slate-900 dark:text-white">Catálogo de cuentas</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Empresa activa en caché: {{ companyUuid || 'Ninguna' }}</p>
        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800">
            <h2 class="text-white text-sm font-black mb-4">Asistente SAT</h2>
            <div class="relative">
              <input
                v-model="satQuery"
                class="w-full bg-slate-800 border border-slate-700 rounded-2xl p-4 text-white placeholder-slate-500"
                placeholder="Busca por código o nombre..."
                @focus="showSatList = true"
                @blur="onSatBlur"
              />

              <div v-if="showSatList && (satFiltered.length || satQuery.length >= 2)" class="absolute z-20 mt-2 w-full max-h-72 overflow-auto rounded-2xl bg-slate-900 border border-slate-700">
                <button
                  v-for="a in satFiltered"
                  :key="a.code + a.name"
                  type="button"
                  class="w-full text-left px-4 py-3 border-b border-slate-800 hover:bg-slate-800"
                  @mousedown.prevent="pickSatAccount(a)"
                >
                  <div class="text-[#9F223C] text-xs font-black">{{ a.code }}</div>
                  <div class="text-white text-sm font-bold">{{ a.name }}</div>
                </button>

                <div v-if="satQuery.length >= 2 && satFiltered.length === 0" class="p-6 text-slate-400 text-center text-sm font-bold">
                  Sin resultados
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
            <h2 class="title-sm">Nueva cuenta</h2>

            <div>
              <label class="label">Código</label>
              <input v-model="form.code" class="input" placeholder="Ej: 101-01-001" />
            </div>

            <div>
              <label class="label">Nombre</label>
              <input v-model="form.name" class="input" placeholder="Nombre de la cuenta" />
            </div>

            <div>
              <label class="label">Naturaleza</label>
              <div class="grid grid-cols-2 gap-3">
                <button type="button" @click="form.nature = 'D'" :class="form.nature === 'D' ? 'btn-primary' : 'btn-secondary'">Deudora</button>
                <button type="button" @click="form.nature = 'A'" :class="form.nature === 'A' ? 'btn-primary' : 'btn-secondary'">Acreedora</button>
              </div>
            </div>

            <button @click="submit" class="btn-primary w-full">Guardar cuenta</button>
          </div>
        </div>

        <div class="lg:col-span-7 bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h2 class="title-sm">Listado</h2>
            <span class="text-xs font-bold text-slate-500">{{ accounts.length }} registros</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-900/40">
                  <th class="th">Código</th>
                  <th class="th">Nombre</th>
                  <th class="th">Naturaleza</th>
                  <th class="th text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <tr v-for="a in accounts" :key="a.uuid">
                  <td class="td font-black text-[#9F223C]">{{ a.code }}</td>
                  <td class="td">
                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ a.name }}</div>
                    <span v-if="a.synced !== true" class="badge-offline mt-1 inline-flex">Pendiente</span>
                  </td>
                  <td class="td">{{ a.nature === 'D' ? 'Deudora' : 'Acreedora' }}</td>
                  <td class="td text-right">
                    <button @click="destroyAccountByUuid(a.uuid)" class="btn-danger">Eliminar</button>
                  </td>
                </tr>
                <tr v-if="accounts.length === 0">
                  <td colspan="4" class="p-10 text-center text-slate-400 font-bold">No hay cuentas registradas.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.label { @apply block text-xs font-black uppercase tracking-widest text-slate-500 mb-2; }
.input { @apply w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 outline-none; }
.title-sm { @apply text-lg font-black text-slate-900 dark:text-white; }
.th { @apply px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-slate-500; }
.td { @apply px-6 py-4 text-sm text-slate-600 dark:text-slate-300; }
.btn-primary { @apply px-4 py-3 rounded-2xl bg-[#9F223C] text-white font-black text-sm; }
.btn-secondary { @apply px-4 py-3 rounded-2xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-black text-sm; }
.btn-danger { @apply px-4 py-2 rounded-xl bg-rose-100 text-rose-700 font-black text-sm; }
.badge-offline { @apply px-2 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-black; }
</style>
