<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const props = defineProps({
  roles: Array,
  quarters: Array,
  mode: Object, // { actor: 'admin' | 'superadmin', managing: 'admin' | 'alumno' }
})

// ✅ Detectar si es superadmin (viene del backend)
const isSuperAdmin = computed(() => String(props.mode?.actor || '').toLowerCase() === 'superadmin')

const form = useForm({
  name: '',
  email: '',
  password: '',
  role: (props.roles && props.roles.length ? props.roles[0] : 'alumno'),
  quarter: (props.quarters && props.quarters.length ? props.quarters[0] : 1),
  is_active: true,
})

function submit() {
  form.post(route('admin.users.store'), {
    onFinish: () => form.reset('password'),
    preserveScroll: true,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-5xl mx-auto p-4 md:p-8 animate-fade-in">
      <div class="mb-8 flex items-center">
        <Link
          :href="route('admin.users.index')"
          class="group flex items-center gap-3 text-slate-400 hover:text-slate-900 transition-all font-black text-[10px] uppercase tracking-[0.2em]"
        >
          <div class="p-2.5 rounded-2xl bg-white border border-slate-100 shadow-sm group-hover:shadow-md group-hover:bg-slate-900 group-hover:text-white transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
            </svg>
          </div>
          Regresar al listado
        </Link>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[3rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-5">

          <div class="lg:col-span-2 bg-slate-50 dark:bg-slate-900/50 p-10 border-b lg:border-b-0 lg:border-r border-slate-100 dark:border-slate-700 flex flex-col justify-between">
            <div>
              <div class="w-14 h-14 bg-white dark:bg-slate-800 rounded-2xl shadow-xl flex items-center justify-center mb-8 border border-slate-100 dark:border-slate-700">
                <svg class="w-7 h-7 text-slate-900 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
              </div>

              <h1 class="text-4xl font-black text-slate-900 dark:text-white leading-none tracking-tighter">
                Nuevo<br>Registro
              </h1>
              <p class="mt-6 text-slate-500 font-medium leading-relaxed text-sm">
                Completa el perfil para integrar a un nuevo miembro a la infraestructura académica.
              </p>
            </div>

            <div class="mt-12 space-y-6">
              <div v-for="(tip, index) in ['Acceso Inmediato', 'Roles Dinámicos', 'Seguridad SSL']" :key="index" class="flex items-center gap-4">
                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: '#9F223C' }"></div>
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">{{ tip }}</p>
              </div>
            </div>
          </div>

          <div class="lg:col-span-3 p-10">
            <form @submit.prevent="submit" class="space-y-6">

              <div class="space-y-2">
                <label class="label-style">Nombre Completo</label>
                <div class="relative group">
                  <input
                    type="text"
                    v-model="form.name"
                    placeholder="Ej. Juan Pérez"
                    class="input-style"
                    :class="{ 'border-red-500 ring-red-50': form.errors.name }"
                  />
                </div>
                <p v-if="form.errors.name" class="error-msg text-red-500">{{ form.errors.name }}</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="label-style">Correo Electrónico</label>
                  <input
                    type="email"
                    v-model="form.email"
                    placeholder="usuario@dominio.com"
                    class="input-style"
                    :class="{ 'border-red-500 ring-red-50': form.errors.email }"
                  />
                  <p v-if="form.errors.email" class="error-msg text-red-500">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                  <label class="label-style">Contraseña</label>
                  <input
                    type="password"
                    v-model="form.password"
                    placeholder="••••••••"
                    class="input-style"
                    :class="{ 'border-red-500 ring-red-50': form.errors.password }"
                  />
                  <p v-if="form.errors.password" class="error-msg text-red-500">{{ form.errors.password }}</p>
                </div>
              </div>

              <!-- Rol -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                <div class="space-y-2">
                  <label class="label-style">Rol del Usuario</label>
                  <select v-model="form.role" class="input-style appearance-none bg-white">
                    <option v-for="r in roles" :key="r" :value="r">{{ String(r).toUpperCase() }}</option>
                  </select>
                </div>

                <!-- ✅ Cuatrimestre solo para ADMIN (no para superadmin) -->
                <div v-if="!isSuperAdmin" class="space-y-2 relative">
                  <label class="label-style">Cuatrimestre</label>
                  <transition name="scale" mode="out-in">
                    <select
                      v-if="form.role === 'alumno'"
                      v-model="form.quarter"
                      class="input-style bg-white border-indigo-200"
                    >
                      <option v-for="q in quarters" :key="q" :value="q">
                        {{ q }}° Cuatrimestre
                      </option>
                    </select>

                    <div v-else class="input-style bg-slate-100/50 border-dashed text-slate-400 italic flex items-center">
                      No aplica para {{ form.role }}
                    </div>
                  </transition>
                </div>
              </div>

              <div class="flex items-center gap-4 p-5 bg-slate-50 dark:bg-slate-900 rounded-[2rem] border border-slate-100 dark:border-slate-700 transition-all hover:bg-white hover:shadow-xl hover:shadow-slate-100">
                <input
                  type="checkbox"
                  v-model="form.is_active"
                  class="w-6 h-6 rounded-lg border-slate-300 text-[#9F223C] focus:ring-[#9F223C]/20 transition-all"
                />
                <div class="flex flex-col">
                  <span class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-tighter">Habilitar Cuenta</span>
                  <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Activo desde el primer inicio</span>
                </div>
              </div>

              <button
                type="submit"
                class="w-full group relative flex items-center justify-center gap-4 px-8 py-5 rounded-[2rem] font-black text-white shadow-2xl transition-all hover:scale-[1.01] active:scale-95 disabled:opacity-50 overflow-hidden"
                style="background: #9F223C;"
                :disabled="form.processing"
              >
                <span class="relative z-10 text-xs uppercase tracking-[0.2em]">
                  {{ form.processing ? 'Sincronizando...' : 'Confirmar Registro' }}
                </span>
                <svg v-if="!form.processing" class="w-5 h-5 relative z-10 transition-transform group-hover:translate-x-2"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7-7 7"/>
                </svg>
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
* { font-family: 'Plus Jakarta Sans', sans-serif; }

.label-style {
  @apply block text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-2 mb-1;
}

.input-style {
  @apply w-full border-slate-200 dark:border-slate-700 rounded-2xl p-4 text-sm font-bold text-slate-700 dark:text-white dark:bg-slate-800 focus:ring-8 focus:ring-slate-50 dark:focus:ring-slate-700/50 focus:border-slate-400 transition-all placeholder:text-slate-300;
}

.error-msg {
  @apply text-[10px] font-black uppercase tracking-tight mt-1 ml-2;
}

/* Transiciones */
.scale-enter-active, .scale-leave-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.scale-enter-from, .scale-leave-to { opacity: 0; transform: scale(0.9) translateY(-10px); }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
</style>