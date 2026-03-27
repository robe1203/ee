<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import { isOnline } from '@/utils/network'
import {
  getCompanies,
  getCompanyByUuid,
  getActiveCompanyUuid,
  setActiveCompanyUuid,
  saveCompany,
  deleteCompany,
  upsertCompaniesFromServer,
} from '@/offline/db'
import { syncAll } from '@/offline/sync'

const props = defineProps({
  company: Object,
  companies: { type: Array, default: () => [] },
  currentCompanyId: [Number, String, null],
  currentCompanyUuid: { type: String, default: null },
  regimens: { type: Array, default: () => [] },
})

const page = usePage()
const companies = ref([])
const currentCompanyId = ref(props.currentCompanyId ?? null)
const currentCompanyUuid = ref(props.currentCompanyUuid ?? null)

const form = useForm({
  name: props.company?.name ?? '',
  rfc: props.company?.rfc ?? '',
  regimen_codigo: props.company?.regimen_codigo ?? '',
  regimen_fiscal: props.company?.regimen_fiscal ?? '',
  address: props.company?.address ?? '',
})

async function loadCompaniesFromDb() {
  companies.value = await getCompanies()
}

async function hydrateFromServer() {
  if (props.companies?.length) {
    await upsertCompaniesFromServer(props.companies, props.currentCompanyUuid ?? null)
  }

  await loadCompaniesFromDb()

  currentCompanyUuid.value = props.currentCompanyUuid ?? (await getActiveCompanyUuid()) ?? null
  const active = currentCompanyUuid.value ? await getCompanyByUuid(currentCompanyUuid.value) : null
  currentCompanyId.value = active?.id ?? props.currentCompanyId ?? null

  if (active) {
    form.name = active.name ?? ''
    form.rfc = active.rfc ?? ''
    form.regimen_codigo = active.regimen_codigo ?? ''
    form.regimen_fiscal = active.regimen_fiscal ?? ''
    form.address = active.address ?? ''
  }
}

async function refreshActiveForm() {
  const active = currentCompanyUuid.value ? await getCompanyByUuid(currentCompanyUuid.value) : null
  form.name = active?.name ?? ''
  form.rfc = active?.rfc ?? ''
  form.regimen_codigo = active?.regimen_codigo ?? ''
  form.regimen_fiscal = active?.regimen_fiscal ?? ''
  form.address = active?.address ?? ''
  currentCompanyId.value = active?.id ?? null
}

watch(
  () => props.currentCompanyUuid,
  async () => {
    await hydrateFromServer()
  }
)

function onRegimenChange() {
  const sel = props.regimens?.find((r) => String(r.code) === String(form.regimen_codigo))
  form.regimen_fiscal = sel ? sel.label : ''
}

async function saveActive() {
  if (!currentCompanyUuid.value) {
    alert('Primero crea o selecciona una empresa.')
    return
  }

  const current = await getCompanyByUuid(currentCompanyUuid.value)
  if (!current) return

  await saveCompany({
    ...current,
    name: form.name,
    rfc: form.rfc,
    regimen_codigo: form.regimen_codigo,
    regimen_fiscal: form.regimen_fiscal,
    address: form.address,
    synced: false,
  })

  await loadCompaniesFromDb()

  if (isOnline.value) {
    try {
      await syncAll()
      router.reload({ preserveScroll: true, preserveState: true })
    } catch (error) {
      console.error(error)
    }
  } else {
    alert('Empresa guardada localmente. Se sincronizará cuando vuelva el internet.')
  }
}

const quickCreateForm = useForm({})
async function quickCreateCompany() {
  const uuid = crypto.randomUUID()
  const count = companies.value.length + 1

  await saveCompany({
    uuid,
    name: `Empresa ${count}`,
    rfc: '',
    regimen_codigo: '',
    regimen_fiscal: '',
    address: '',
    synced: false,
  })

  await setActiveCompanyUuid(uuid)
  currentCompanyUuid.value = uuid
  await loadCompaniesFromDb()
  await refreshActiveForm()

  if (isOnline.value) {
    try {
      await syncAll()
      router.reload({ preserveScroll: true, preserveState: true })
    } catch (error) {
      console.error(error)
    }
  }
}

async function selectCompany(id) {
  const company = companies.value.find((item) => String(item.id) === String(id)) || companies.value.find((item) => String(item.uuid) === String(id))
  if (!company) return

  currentCompanyUuid.value = company.uuid
  currentCompanyId.value = company.id ?? id
  await setActiveCompanyUuid(company.uuid)
  await refreshActiveForm()

  if (isOnline.value && company.id) {
    router.post(route('app.companies.select', company.id), {}, { preserveScroll: true })
  }
}

const editingId = ref(null)
const editNameForm = useForm({ name: '' })

function startEdit(company) {
  editingId.value = company.id ?? company.uuid
  editNameForm.name = company.name
  editNameForm.clearErrors()
}

function cancelEdit() {
  editingId.value = null
  editNameForm.reset()
}

async function saveEdit(id) {
  const company = companies.value.find((item) => String(item.id) === String(id) || String(item.uuid) === String(id))
  if (!company) return

  await saveCompany({
    ...company,
    name: editNameForm.name,
    synced: false,
  })

  cancelEdit()
  await loadCompaniesFromDb()
  await refreshActiveForm()

  if (isOnline.value) {
    try {
      await syncAll()
      router.reload({ preserveScroll: true, preserveState: true })
    } catch (error) {
      console.error(error)
    }
  }
}

async function destroyCompanyAction(id) {
  const company = companies.value.find((item) => String(item.id) === String(id) || String(item.uuid) === String(id))
  if (!company) return
  if (!confirm('¿Eliminar esta empresa?')) return

  await deleteCompany(company.uuid)
  await loadCompaniesFromDb()

  if (currentCompanyUuid.value === company.uuid) {
    currentCompanyUuid.value = null
    currentCompanyId.value = null
    form.reset()
  }

  if (isOnline.value) {
    try {
      await syncAll()
      router.reload({ preserveScroll: true, preserveState: true })
    } catch (error) {
      console.error(error)
    }
  }
}

const activeName = () =>
  companies.value?.find((c) => String(c.uuid) === String(currentCompanyUuid.value))?.name ?? 'Sin selección'

const importForm = useForm({
  file: null,
  force: false,
})

const fileInput = ref(null)
const showConflict = ref(false)

function pickFile(e) {
  importForm.file = e.target.files?.[0] || null
}

function exportActiveCompany() {
  const active = companies.value.find((c) => String(c.uuid) === String(currentCompanyUuid.value))
  if (!active?.id) {
    alert('Para exportar con validación de seguridad primero necesitas sincronizar esta empresa en el servidor.')
    return
  }
  window.location.href = route('app.companies.export', active.id)
}

function importCompany(force = false) {
  if (!importForm.file) {
    alert('Selecciona un archivo .json')
    return
  }

  if (!isOnline.value) {
    alert('La importación protegida requiere internet para validar propietario y firma del archivo.')
    return
  }

  importForm.force = !!force
  importForm.post(route('app.companies.import'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: async () => {
      if (fileInput.value) fileInput.value.value = ''
      importForm.reset()
      showConflict.value = false
      await hydrateFromServer()
    },
  })
}

watch(
  () => page.props.flash?.import_conflict,
  (v) => {
    showConflict.value = !!v
  },
  { immediate: true }
)

function closeConflict() {
  showConflict.value = false
}

const importFileName = () => (importForm.file ? importForm.file.name : 'Ninguno')

onMounted(async () => {
  await hydrateFromServer()
})

const destroyCompany = destroyCompanyAction
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto pt-4 px-4 sm:px-6 lg:px-8 pb-8 space-y-6 sm:space-y-8 animate-fade-in">
      
      <div class="fixed top-4 right-4 z-50 w-full max-w-sm space-y-3">
        <Transition name="notification">
          <div v-if="page.props.flash?.success" class="toast-success">
             <span class="font-bold text-emerald-900 dark:text-emerald-100">{{ page.props.flash.success }}</span>
          </div>
        </Transition>
      </div>

      <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200/60 dark:border-slate-700/50 pb-6">
        <div class="min-w-0">
          <div class="flex items-center gap-3">
             <div class="w-2 h-8 bg-[#9F223C] rounded-full"></div>
             <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
               Gestión Corporativa
             </h1>
          </div>
          <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm font-medium ml-5">
            Configura tus razones sociales y entorno de trabajo.
          </p>
        </div>

        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 px-4 py-2 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
          <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
          <div class="flex flex-col">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Sesión Activa</span>
            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ activeName() }}</span>
          </div>
        </div>
      </header>

      <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/30">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div>
            <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Sincronización Offline y Fusión</h3>
            <p class="text-xs text-slate-500 mt-1">Archivo: <span class="font-bold text-[#9F223C]">{{ importFileName() }}</span></p>
            <p class="text-[11px] text-slate-500 mt-2 max-w-2xl">Si el archivo pertenece a una empresa tuya ya existente, el sistema intentará fusionar cuentas y pólizas para que no pierdas avances al trabajar en escuela y en casa.</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <button @click="exportActiveCompany" class="action-btn hover:border-[#9F223C]">Exportar JSON</button>
            <label class="action-btn cursor-pointer">
              Seleccionar
              <input ref="fileInput" type="file" class="hidden" @change="pickFile" />
            </label>
            <button @click="importCompany(false)" class="px-5 py-2.5 rounded-xl font-bold text-white text-xs btn-gradient shadow-lg shadow-rose-900/20">
              Importar / Fusionar
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8">
        
        <aside class="lg:col-span-4 space-y-6">
          <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl">
            <h2 class="text-base font-black text-slate-800 dark:text-white uppercase tracking-tight mb-6 flex items-center gap-2">
              <span class="w-1.5 h-4 bg-[#9F223C] rounded-full"></span>
              Detalles Fiscales
            </h2>

            <form @submit.prevent="saveActive" class="space-y-4">
              <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Nombre Comercial</label>
                <input v-model="form.name" class="input-new" placeholder="Nombre de la empresa" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">RFC</label>
                <input v-model="form.rfc" class="input-new font-mono uppercase" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Régimen Fiscal</label>
                <select v-model="form.regimen_codigo" @change="onRegimenChange" class="input-new">
                  <option value="">Seleccionar...</option>
                  <option v-for="r in regimens" :key="r.code" :value="r.code">{{ r.label }}</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1 italic opacity-60">Descripción Régimen</label>
                <input v-model="form.regimen_fiscal" readonly class="input-new bg-slate-50 dark:bg-slate-900/50 border-none italic text-slate-500" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Dirección Legal</label>
                <input v-model="form.address" class="input-new" />
              </div>

              <button :disabled="form.processing" class="w-full py-3.5 mt-4 rounded-2xl font-black text-white text-sm btn-gradient shadow-lg shadow-rose-900/20 transition-all active:scale-95">
                {{ form.processing ? 'PROCESANDO...' : 'GUARDAR CAMBIOS' }}
              </button>
            </form>
          </div>
        </aside>

        <main class="lg:col-span-8">
          <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-xl overflow-hidden">
            <div class="p-6 border-b border-slate-50 dark:border-slate-700 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-5 bg-[#9F223C] rounded-full"></span>
                <h3 class="text-base font-black text-slate-800 dark:text-slate-200 uppercase tracking-tight">Directorio</h3>
              </div>
              <button @click="quickCreateCompany" class="text-[10px] font-black text-[#9F223C] dark:text-rose-400 uppercase tracking-widest bg-rose-50 dark:bg-rose-900/20 px-4 py-2 rounded-lg hover:bg-[#9F223C] hover:text-white transition-all">
                + Nueva Entidad
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                    <th class="th-style">Empresa</th>
                    <th class="th-style">RFC</th>
                    <th class="th-style text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                  <tr v-for="c in companies" :key="c.uuid" @click="selectCompany(c.uuid)" class="hover:bg-rose-50/30 dark:hover:bg-rose-900/5 transition-colors cursor-pointer group">
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl flex items-center justify-center font-black text-xs transition-colors"
                             :class="String(currentCompanyUuid) === String(c.uuid) ? 'bg-[#9F223C] text-white' : 'bg-slate-100 text-slate-400 group-hover:bg-rose-100 group-hover:text-[#9F223C]'">
                          {{ c.name.charAt(0) }}
                        </div>
                        <div class="flex flex-col">
                          <span class="font-bold text-slate-700 dark:text-slate-300">{{ c.name }}</span>
                          <span v-if="String(currentCompanyUuid) === String(c.uuid)" class="text-[9px] font-black text-[#9F223C] uppercase">Activa Ahora</span>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <span class="font-mono text-xs font-bold text-slate-500">{{ c.rfc || '---' }}</span>
                    </td>
                    <td class="px-6 py-4 text-right" @click.stop>
                      <div class="flex justify-end gap-2">
                        <button @click="startEdit(c)" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">✏️</button>
                        <button @click="destroyCompany(c.uuid)" class="p-2 hover:bg-rose-100 dark:hover:bg-rose-900/30 rounded-lg transition-colors">🗑️</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>

      <div v-if="showConflict" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl p-8 border border-slate-200 animate-fade-in">
           <h3 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Conflicto de Datos</h3>
           <p class="mt-4 text-sm text-slate-500 leading-relaxed">{{ page.props.flash.import_conflict?.message }}</p>
           <div class="mt-6 flex gap-3">
             <button @click="closeConflict" class="flex-1 px-4 py-3 rounded-xl font-bold text-slate-500 bg-slate-100">Cancelar</button>
             <button @click="importCompany(true)" class="flex-1 px-4 py-3 rounded-xl font-bold text-white btn-gradient">Forzar Import</button>
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

.input-new {
  @apply w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200 focus:ring-4 focus:ring-rose-500/10 focus:border-[#9F223C] transition-all outline-none border;
}

.th-style {
  @apply px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500;
}

.action-btn {
  @apply px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 hover:shadow-md transition-all active:scale-95;
}

.toast-success {
  @apply bg-emerald-50 dark:bg-emerald-900/40 border border-emerald-100 p-4 rounded-2xl shadow-xl flex items-center backdrop-blur-md;
}

.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

.notification-enter-active, .notification-leave-active { transition: all 0.4s ease; }
.notification-enter-from { opacity: 0; transform: translateY(-20px); }
</style>