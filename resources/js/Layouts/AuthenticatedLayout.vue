<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { isOnline, useNetworkStatus } from '@/utils/network'

useNetworkStatus()

const page = usePage()
const open = ref(false)
const user = computed(() => page.props.auth?.user || null)
const navComponent = computed(() => (isOnline.value ? Link : 'a'))

const roleName = computed(() => String(user.value?.role || '').toLowerCase())
const roleList = computed(() => {
  const u = user.value
  const roles = u?.roles || u?.roles_names || u?.role_names || []
  return Array.isArray(roles) ? roles.map(r => String(r).toLowerCase()) : []
})

const hasAnyRole = (...names) => {
  const normalized = names.map(n => String(n).toLowerCase())

  if (user.value?.is_admin && normalized.includes('admin')) return true
  if (roleName.value && normalized.includes(roleName.value)) return true
  return roleList.value.some(r => normalized.includes(r))
}

const canManageUsers = computed(() => hasAnyRole('admin', 'super_admin', 'superadmin'))
const isSuperAdmin = computed(() => hasAnyRole('super_admin', 'superadmin'))

const canSeeFinancialReports = computed(() => !!user.value)
const canSeeBalanceGeneral = computed(() => hasAnyRole('admin', 'super_admin', 'superadmin'))

const isDark = ref(false)

function applyTheme() {
  if (isDark.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

function toggleDarkMode() {
  isDark.value = !isDark.value
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
  applyTheme()
}

onMounted(() => {
  isDark.value = localStorage.getItem('theme') === 'dark'
  applyTheme()
})

onBeforeUnmount(() => {})

const toggleDrawer = () => (open.value = !open.value)
const closeDrawer = () => (open.value = false)

const isActive = (name) => {
  try {
    return window.route().current(name)
  } catch {
    return false
  }
}

function navAttrs(name) {
  const href = route(name)
  if (isOnline.value) {
    return { href }
  }
  return { href }
}

const logoIcon = new URL('../../image/icono.png', import.meta.url).href
const iconInicio = new URL('../../image/Inicio.png', import.meta.url).href
const iconEmpresa = new URL('../../image/Empresa.png', import.meta.url).href
const iconCatalogo = new URL('../../image/catalogo.png', import.meta.url).href
const iconPoliza = new URL('../../image/Poliza.png', import.meta.url).href
const iconInforme = new URL('../../image/Informe.png', import.meta.url).href
const iconUsuario = new URL('../../image/Usuario.png', import.meta.url).href
const iconBalanza = new URL('../../image/Balanza.png', import.meta.url).href

const navMain = [
  { name: 'app.home', label: 'Dashboard', icon: iconInicio },
  { name: 'app.company.index', label: 'Empresas', icon: iconEmpresa },
  { name: 'app.catalog.index', label: 'Catálogos', icon: iconCatalogo },
  { name: 'app.policies.index', label: 'Pólizas', icon: iconPoliza },
  { name: 'app.reports.unified', label: 'Informes y Reportes', icon: iconInforme },
]

const balanzaOpen = ref(false)

const balanzaMenu = computed(() => {
  if (!canSeeFinancialReports.value) return []

  const items = [
    { name: 'app.reports.balanza', label: 'Balanza de Comprobación' },
    { name: 'app.reports.libroDiario', label: 'Libro Diario' },
  ]

  if (canSeeBalanceGeneral.value) {
    items.push({ name: 'app.reports.balanceGeneral', label: 'Balance General' })
  }

  return items
})

const isBalanzaActive = computed(() => balanzaMenu.value.some(it => isActive(it.name)))

const navAdmin = computed(() => [
  {
    name: 'admin.users.index',
    label: isSuperAdmin.value ? 'Administradores' : 'Alumnos',
    icon: iconUsuario,
  },
])
</script>

<template>
  <div class="min-h-screen font-sans antialiased transition-colors duration-300 bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <header class="sticky top-0 z-50 w-full border-b backdrop-blur-lg bg-white/90 border-slate-200 dark:bg-slate-900/80 dark:border-slate-800">
      <div class="max-w-[1700px] mx-auto px-4 lg:px-10 h-16 lg:h-20 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button @click="toggleDrawer" class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <span class="text-2xl">☰</span>
          </button>

          <a href="/" class="flex items-center gap-3">
            <div class="logo-container">
              <img :src="logoIcon" alt="Logo" class="w-9 h-9 object-contain relative z-10" />
            </div>
            <div class="hidden sm:block">
              <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 leading-none mb-1">Accounting</div>
              <div class="text-xl font-black tracking-tighter">Conta<span class="text-blue-600">Sync</span></div>
            </div>
          </a>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
          <div :class="isOnline ? 'badge-online' : 'badge-offline'" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold border transition-colors">
            <span class="flex h-2 w-2 relative">
              <span v-if="isOnline" class="animate-ping absolute h-full w-full rounded-full bg-current opacity-75"></span>
              <span class="relative h-2 w-2 rounded-full bg-current"></span>
            </span>
            <span class="hidden md:inline">{{ isOnline ? 'Sistema Activo' : 'Sin Conexión' }}</span>
          </div>

          <button @click="toggleDarkMode" class="p-2.5 rounded-2xl border border-slate-200 bg-white dark:bg-slate-800 dark:border-slate-700 transition-all">
            <span>{{ isDark ? '☀️' : '🌙' }}</span>
          </button>

          <div class="hidden lg:flex items-center gap-3 pl-4 border-l dark:border-slate-800">
            <div class="text-right">
              <div class="text-sm font-bold truncate max-w-[150px]">{{ user?.name }}</div>
              <div class="text-[10px] text-slate-400">{{ user?.email }}</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold">
              {{ user?.name?.charAt(0) }}
            </div>
          </div>

          <Link :href="route('logout')" method="post" as="button" class="logout-btn" :disabled="!isOnline">
            <span class="hidden sm:inline">Salir</span>
            <span class="sm:hidden text-lg">✕</span>
          </Link>
        </div>
      </div>
    </header>

    <div class="max-w-[1700px] mx-auto flex flex-col lg:flex-row gap-6 lg:gap-10 px-4 lg:px-10 py-6 lg:py-10">
      <aside class="hidden lg:block w-72 shrink-0">
        <nav class="sticky top-28 space-y-8">
          <div class="nav-card">
            <h3 class="nav-section-title">Navegación</h3>
            <div class="mt-4 space-y-1">
              <component
                :is="navComponent"
                v-for="item in navMain"
                :key="item.name"
                v-bind="navAttrs(item.name)"
                class="nav-item group"
                :class="isActive(item.name) ? 'active' : 'inactive'"
              >
                <img :src="item.icon" class="w-6 h-6 object-contain" />
                {{ item.label }}
              </component>

              <div v-if="canSeeFinancialReports" class="pt-1">
                <button @click="balanzaOpen = !balanzaOpen" class="nav-item w-full group" :class="isBalanzaActive ? 'active' : 'inactive'">
                  <img :src="iconBalanza" class="w-6 h-6 object-contain" />
                  <span class="flex-1 text-left">Balanza</span>
                  <span class="text-[10px] transition-transform" :class="balanzaOpen ? 'rotate-180' : ''">▼</span>
                </button>

                <div v-show="balanzaOpen" class="mt-2 ml-10 space-y-2 border-l-2 dark:border-slate-800 pl-4">
                  <component
                    :is="navComponent"
                    v-for="it in balanzaMenu"
                    :key="it.name"
                    v-bind="navAttrs(it.name)"
                    class="sub-nav-item"
                    :class="isActive(it.name) ? 'sub-active' : ''"
                  >
                    {{ it.label }}
                  </component>
                </div>
              </div>
            </div>
          </div>

          <div v-if="canManageUsers" class="nav-card">
            <h3 class="nav-section-title">Administración</h3>
            <div class="mt-4 space-y-1">
              <component
                :is="navComponent"
                v-for="item in navAdmin"
                :key="item.name"
                v-bind="navAttrs(item.name)"
                class="nav-item group"
                :class="isActive(item.name) ? 'active' : 'inactive'"
              >
                <img :src="item.icon" class="w-6 h-6 object-contain" />
                {{ item.label }}
              </component>
            </div>
          </div>
        </nav>
      </aside>

      <main class="flex-1 min-w-0">
        <div class="fade-in-up">
          <slot />
        </div>
      </main>
    </div>

    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-[100] lg:hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeDrawer"></div>

        <Transition name="slide">
          <div class="absolute left-0 top-0 h-full w-[280px] bg-white dark:bg-slate-900 shadow-2xl p-6 flex flex-col">
            <div class="flex items-center justify-between mb-8">
              <span class="font-black text-xl dark:text-white">Menú</span>
              <button @click="closeDrawer" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 dark:text-white">✕</button>
            </div>

            <nav class="flex-1 overflow-y-auto space-y-6">
              <div class="space-y-1">
                <component
                  :is="navComponent"
                  v-for="item in navMain"
                  :key="item.name"
                  v-bind="navAttrs(item.name)"
                  @click="closeDrawer"
                  class="mobile-nav-link"
                  :class="isActive(item.name) ? 'm-active' : ''"
                >
                  <img :src="item.icon" class="w-6 h-6 object-contain" />
                  {{ item.label }}
                </component>

                <template v-if="canSeeFinancialReports">
                  <button @click="balanzaOpen = !balanzaOpen" class="mobile-nav-link w-full">
                    <img :src="iconBalanza" class="w-6 h-6 object-contain" />
                    <span class="flex-1 text-left">Balanza</span>
                    <span class="text-xs">{{ balanzaOpen ? '▲' : '▼' }}</span>
                  </button>

                  <div v-show="balanzaOpen" class="pl-12 space-y-3 py-2 border-l-2 dark:border-slate-800 ml-3">
                    <component
                      :is="navComponent"
                      v-for="it in balanzaMenu"
                      :key="it.name"
                      v-bind="navAttrs(it.name)"
                      @click="closeDrawer"
                      class="block text-sm font-semibold opacity-70 dark:text-slate-300"
                    >
                      {{ it.label }}
                    </component>
                  </div>
                </template>
              </div>

              <div v-if="canManageUsers" class="pt-6 border-t dark:border-slate-800">
                <div class="text-[10px] font-bold uppercase text-slate-400 mb-4 px-2">Configuración</div>
                <component
                  :is="navComponent"
                  v-for="item in navAdmin"
                  :key="item.name"
                  v-bind="navAttrs(item.name)"
                  @click="closeDrawer"
                  class="mobile-nav-link"
                  :class="isActive(item.name) ? 'm-active' : ''"
                >
                  <img :src="item.icon" class="w-6 h-6 object-contain" />
                  {{ item.label }}
                </component>
              </div>
            </nav>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.logo-container {
  @apply h-11 w-11 rounded-2xl flex items-center justify-center bg-gradient-to-br from-blue-600 to-blue-800 shadow-lg shadow-blue-500/20;
}

.nav-card {
  @apply bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm;
}

.nav-section-title {
  @apply text-[11px] font-black text-slate-400 uppercase tracking-widest px-3;
}

.nav-item {
  @apply flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] font-semibold transition-all duration-200;
}

.inactive { @apply text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800; }
.active { @apply bg-blue-600 text-white shadow-lg shadow-blue-500/30; }

.sub-nav-item { @apply block py-1.5 text-xs font-bold text-slate-500 hover:text-blue-600 dark:text-slate-400 transition-colors; }
.sub-active { @apply text-blue-600 dark:text-blue-400; }

.mobile-nav-link {
  @apply flex items-center gap-4 p-4 rounded-2xl font-bold text-slate-800 dark:text-slate-200 transition-colors;
}

.m-active { @apply bg-blue-50 text-blue-700 dark:bg-blue-600/10 dark:text-blue-400; }

.logout-btn {
  @apply px-5 py-2.5 rounded-xl bg-slate-950 text-white text-xs font-black uppercase tracking-widest hover:bg-red-600 transition-all dark:bg-white dark:text-slate-950 dark:hover:bg-red-500 dark:hover:text-white disabled:opacity-40 disabled:cursor-not-allowed;
}

.badge-online { @apply bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20; }
.badge-offline { @apply bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20; }

.fade-in-up { animation: fadeUp 0.6s ease-out both; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-enter-active, .slide-leave-active { transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
</style>
