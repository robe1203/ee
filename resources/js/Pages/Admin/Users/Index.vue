<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  users: Object,
  roles: Array,
})

const page = usePage()

function destroyUser(id) {
  if (!confirm('¿Estás completamente seguro? Esta acción no se puede deshacer.')) return

  router.delete(route('admin.users.destroy', id), {
    preserveScroll: true,
  })
}

function toggleUser(id) {
  router.patch(route('admin.users.toggle', id), {}, {
    preserveScroll: true,
  })
}

const q = ref('')
const roleFilter = ref('all')
const statusFilter = ref('all')

const sortKey = ref('name')
const sortDir = ref('asc')

const rows = computed(() => props.users?.data ?? [])

const filteredRows = computed(() => {
  const query = q.value.trim().toLowerCase()

  return rows.value.filter((u) => {
    const matchesQuery =
      !query ||
      (u.name || '').toLowerCase().includes(query) ||
      (u.email || '').toLowerCase().includes(query) ||
      (u.role || '').toLowerCase().includes(query)

    const matchesRole = roleFilter.value === 'all' ? true : u.role === roleFilter.value

    const matchesStatus =
      statusFilter.value === 'all'
        ? true
        : statusFilter.value === 'active'
          ? !!u.is_active
          : !u.is_active

    return matchesQuery && matchesRole && matchesStatus
  })
})

const sortedRows = computed(() => {
  const dir = sortDir.value === 'asc' ? 1 : -1
  const list = [...filteredRows.value]

  const val = (u) => {
    switch (sortKey.value) {
      case 'role':
        return (u.role || '').toLowerCase()
      case 'quarter':
        return u.role === 'alumno' ? Number(u.cuatrimestre ?? 0) : -1
      case 'status':
        return u.is_active ? 1 : 0
      case 'name':
      default:
        return (u.name || '').toLowerCase()
    }
  }

  list.sort((a, b) => {
    const A = val(a)
    const B = val(b)

    if (A < B) return -1 * dir
    if (A > B) return 1 * dir
    return 0
  })

  return list
})

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

function sortIcon(key) {
  if (sortKey.value !== key) return 'opacity-30'
  return sortDir.value === 'asc' ? 'rotate-0 opacity-100' : 'rotate-180 opacity-100'
}

function resetFilters() {
  q.value = ''
  roleFilter.value = 'all'
  statusFilter.value = 'all'
  sortKey.value = 'name'
  sortDir.value = 'asc'
}

const counts = computed(() => {
  const all = rows.value.length
  const active = rows.value.filter((u) => !!u.is_active).length
  const inactive = rows.value.filter((u) => !u.is_active).length
  const alumno = rows.value.filter((u) => u.role === 'alumno').length

  return { all, active, inactive, alumno }
})

const roleLabel = (r) => {
  const map = { admin: 'Admin', alumno: 'Alumno', docente: 'Docente' }
  return map[r] || (r ? String(r).toUpperCase() : 'Sin rol')
}

const rolePill = (role) => {
  const styles = {
    admin: 'bg-violet-50 text-violet-700 border-violet-100 dark:bg-violet-900/20 dark:text-violet-300 dark:border-violet-800',
    alumno: 'bg-sky-50 text-sky-700 border-sky-100 dark:bg-sky-900/20 dark:text-sky-300 dark:border-sky-800',
    docente: 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300 dark:border-indigo-800',
  }

  return (
    styles[role] ||
    'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-900/30 dark:text-slate-200 dark:border-slate-700'
  )
}

const statusPill = (isActive) =>
  isActive
    ? 'bg-emerald-50 text-emerald-800 border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800'
    : 'bg-amber-50 text-amber-800 border-amber-100 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800'
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 animate-fade-in">
      <section class="card">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
          <div class="space-y-1">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
              Gestión de usuarios
            </h1>
            <p class="text-slate-500 dark:text-slate-300 font-medium">
              Administra accesos, perfiles y estado de cuentas.
            </p>

            <div class="pt-3 flex flex-wrap gap-2">
              <span class="chip">
                Total: <b class="text-slate-900 dark:text-white">{{ counts.all }}</b>
              </span>
              <span class="chip chip-good">
                Activos: <b class="text-emerald-900 dark:text-emerald-200">{{ counts.active }}</b>
              </span>
              <span class="chip chip-warn">
                Inactivos: <b class="text-amber-900 dark:text-amber-200">{{ counts.inactive }}</b>
              </span>
              <span class="chip chip-info">
                Alumnos: <b class="text-sky-900 dark:text-sky-200">{{ counts.alumno }}</b>
              </span>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-3">
            <Link :href="route('admin.users.create')" class="btn-primary">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              <span>Registrar</span>
            </Link>

            <button type="button" class="btn-secondary" @click="resetFilters">
              Limpiar
            </button>
          </div>
        </div>
      </section>

      <transition name="list">
        <div v-if="page.props.flash?.success" class="alert-success">
          <div class="alert-icon">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <p class="font-black text-sm">{{ page.props.flash.success }}</p>
        </div>
      </transition>

      <transition name="list">
        <div v-if="page.props.errors?.error" class="alert-error">
          <div class="alert-icon-danger">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M18 10A8 8 0 114 4.5l9.5 9.5A7.97 7.97 0 0018 10zM9 7a1 1 0 012 0v3a1 1 0 11-2 0V7zm1 7a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 14z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <p class="font-black text-sm">{{ page.props.errors.error }}</p>
        </div>
      </transition>

      <section class="card">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-6">
            <label class="label">Buscar</label>
            <div class="field">
              <svg
                class="w-5 h-5 transition-all duration-300"
                :class="q ? 'text-[#9F223C] scale-110' : 'text-slate-400'"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 103.75 3.75a7.5 7.5 0 0012.9 12.9z"
                />
              </svg>

              <input
                v-model="q"
                type="text"
                placeholder="Nombre, correo o rol..."
                class="input"
              />

              <button
                v-if="q"
                type="button"
                class="icon-btn"
                @click="q = ''"
                aria-label="Limpiar búsqueda"
                title="Limpiar"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="md:col-span-3">
            <label class="label">Estado</label>
            <select v-model="statusFilter" class="select">
              <option value="all">Todos</option>
              <option value="active">Activos</option>
              <option value="inactive">Inactivos</option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 font-semibold">
          <div>
            Mostrando <b class="text-slate-900 dark:text-white">{{ sortedRows.length }}</b> de
            <b class="text-slate-900 dark:text-white">{{ rows.length }}</b> usuarios (solo en esta página).
          </div>

          <div class="flex items-center gap-2">
            <span class="text-[10px] uppercase tracking-[0.25em]">Orden:</span>
            <span class="px-3 py-1 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 text-slate-700 dark:text-slate-200 text-[11px] font-black">
              {{ sortKey }} · {{ sortDir }}
            </span>
          </div>
        </div>
      </section>

      <section class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 z-10">
              <tr class="bg-slate-900 dark:bg-slate-950 text-white">
                <th class="th">
                  <button class="th-btn" @click="toggleSort('name')">
                    Usuario
                    <svg class="w-4 h-4 transition-transform" :class="sortIcon('name')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </th>

                <th class="th">
                  <button class="th-btn" @click="toggleSort('role')">
                    Rol
                    <svg class="w-4 h-4 transition-transform" :class="sortIcon('role')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </th>

                <th class="th text-center">
                  <button class="th-btn mx-auto" @click="toggleSort('quarter')">
                    Cuatr.
                    <svg class="w-4 h-4 transition-transform" :class="sortIcon('quarter')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </th>

                <th class="th">
                  <button class="th-btn" @click="toggleSort('status')">
                    Estado
                    <svg class="w-4 h-4 transition-transform" :class="sortIcon('status')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </th>

                <th class="th text-right">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
              <tr
                v-for="u in sortedRows"
                :key="u.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-900/20 transition-colors"
              >
                <td class="td">
                  <div class="flex items-center gap-4">
                    <div class="avatar">
                      {{ (u.name || '?').charAt(0).toUpperCase() }}
                    </div>

                    <div class="min-w-0">
                      <p class="font-black text-slate-900 dark:text-white leading-tight truncate">
                        {{ u.name }}
                      </p>
                      <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        {{ u.email }}
                      </p>
                    </div>
                  </div>
                </td>

                <td class="td">
                  <span class="pill" :class="rolePill(u.role)">
                    {{ roleLabel(u.role) }}
                  </span>
                </td>

                <td class="td text-center">
                  <span v-if="u.role === 'alumno'" class="quarter" :title="`Cuatrimestre: ${u.cuatrimestre ?? '—'}`">
                    {{ u.cuatrimestre ?? '—' }}<span v-if="u.cuatrimestre">°</span>
                  </span>
                  <span v-else class="text-slate-300 font-bold">—</span>
                </td>

                <td class="td">
                  <button
                    type="button"
                    class="pill transition hover:scale-[1.02] active:scale-95"
                    :class="statusPill(!!u.is_active)"
                    @click="toggleUser(u.id)"
                    :title="u.is_active ? 'Desactivar acceso' : 'Activar acceso'"
                  >
                    <span :class="['dot', u.is_active ? 'dot-on' : 'dot-off']"></span>
                    {{ u.is_active ? 'Activo' : 'Inactivo' }}
                  </button>
                </td>

                <td class="td text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <Link
                      :href="route('admin.users.edit', u.id)"
                      class="action-btn"
                      title="Editar"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </Link>

                    <button
                      type="button"
                      @click="destroyUser(u.id)"
                      class="action-btn danger"
                      title="Eliminar"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="sortedRows.length === 0">
                <td colspan="5" class="px-6 sm:px-8 py-14 text-center">
                  <div class="mx-auto w-full max-w-md space-y-2">
                    <div class="empty-icon">
                      <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 103.75 3.75a7.5 7.5 0 0012.9 12.9z" />
                      </svg>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-black text-lg">Sin resultados</h3>
                    <p class="text-slate-500 dark:text-slate-300 font-semibold text-sm">Ajusta filtros o búsqueda.</p>

                    <button type="button" class="btn-secondary mx-auto" @click="resetFilters">
                      Restablecer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-6 sm:px-8 py-5 bg-slate-50/60 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
          <p class="text-[10px] font-black uppercase text-slate-400 tracking-[0.3em]">
            Página {{ users.current_page }} de {{ users.last_page }}
          </p>

          <div class="flex gap-2 flex-wrap justify-center">
            <Link
              v-for="l in users.links"
              :key="l.label"
              :href="l.url || ''"
              v-html="l.label"
              class="page-btn"
              :class="[
                l.active ? 'page-active' : 'page-idle',
                !l.url && 'opacity-30 cursor-not-allowed pointer-events-none',
              ]"
            />
          </div>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

* { font-family: 'Plus Jakarta Sans', sans-serif; }

.card{
  @apply bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/50 dark:shadow-none p-6;
}

.chip{
  @apply px-3 py-1.5 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-300;
}
.chip-good{ @apply border-emerald-100 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300; }
.chip-warn{ @apply border-amber-100 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300; }
.chip-info{ @apply border-sky-100 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300; }

.btn-primary{
  @apply inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl font-black text-white shadow-lg transition-all active:scale-95;
  background:#9F223C;
}
.btn-primary:hover{ filter: brightness(1.05); }

.btn-secondary{
  @apply inline-flex items-center justify-center px-6 py-3 rounded-2xl font-black bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-100 border border-slate-200/70 dark:border-slate-600 shadow-sm hover:bg-slate-200 dark:hover:bg-slate-600 active:scale-95 transition-all;
}

.icon-btn{
  @apply p-2 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 hover:bg-[#9F223C] hover:text-white transition-all duration-300 shadow-sm;
}

.alert-success{
  @apply flex items-center gap-3 p-4 rounded-2xl border shadow-sm bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300;
}

.alert-error{
  @apply flex items-center gap-3 p-4 rounded-2xl border shadow-sm bg-rose-50 dark:bg-rose-900/20 border-rose-100 dark:border-rose-800 text-rose-800 dark:text-rose-300;
}

.alert-icon{
  @apply bg-emerald-500 p-1 rounded-lg text-white;
}

.alert-icon-danger{
  @apply bg-rose-500 p-1 rounded-lg text-white;
}

.label{
  @apply text-[10px] font-black uppercase tracking-[0.25em] text-slate-400;
}

.field{
  @apply mt-2 flex items-center gap-3 px-5 py-3 rounded-full border border-slate-200/70 dark:border-slate-600 bg-white/70 dark:bg-slate-900/40 backdrop-blur-md shadow-md shadow-slate-200/40 dark:shadow-none transition-all duration-300;
}
.field:focus-within{
  @apply border-[#9F223C] shadow-lg shadow-[#9F223C]/20 scale-[1.01];
}

.input{
  @apply w-full bg-transparent outline-none text-slate-900 dark:text-white placeholder:text-slate-400 font-semibold tracking-wide;
}

.select{
  @apply mt-2 w-full px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 text-slate-900 dark:text-white font-black;
}

.th{
  @apply px-6 sm:px-8 py-4 text-[10px] font-black uppercase tracking-[0.25em] text-slate-200;
}

.th-btn{
  @apply inline-flex items-center gap-2 hover:text-white transition;
}

.td{
  @apply px-6 sm:px-8 py-5 align-middle;
}

.avatar{
  @apply w-11 h-11 rounded-2xl bg-gradient-to-br from-[#9F223C] to-[#c73c59] text-white flex items-center justify-center font-black shadow-lg shrink-0;
}

.pill{
  @apply inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-black;
}

.dot{
  @apply inline-block w-2.5 h-2.5 rounded-full;
}
.dot-on{
  @apply bg-emerald-500;
}
.dot-off{
  @apply bg-amber-500;
}

.quarter{
  @apply inline-flex items-center justify-center min-w-[44px] px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100 font-black;
}

.action-btn{
  @apply inline-flex items-center justify-center w-10 h-10 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all active:scale-95;
}

.action-btn.danger{
  @apply text-rose-600 border-rose-200 dark:border-rose-800 hover:bg-rose-50 dark:hover:bg-rose-900/20;
}

.empty-icon{
  @apply mx-auto w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center;
}

.page-btn{
  @apply min-w-[40px] h-10 px-3 rounded-xl inline-flex items-center justify-center text-sm font-bold border transition;
}

.page-active{
  @apply bg-[#9F223C] text-white border-[#9F223C];
}

.page-idle{
  @apply bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700;
}

.list-enter-active,
.list-leave-active{
  transition: all .2s ease;
}
.list-enter-from,
.list-leave-to{
  opacity: 0;
  transform: translateY(-6px);
}
</style>