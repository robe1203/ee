<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  user: Object,
  role: String,
  roles: Array,
  quarters: Array,
})

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  password: '',
  role: props.role || 'alumno',
  quarter: props.user.quarter ?? 1,
  is_active: !!props.user.is_active,
})

function submit() {
  form.put(route('admin.users.update', props.user.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('password'),
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-6xl mx-auto p-4 md:p-8 animate-fade-in">
      
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
        <div>
          <Link 
            :href="route('admin.users.index')" 
            class="group flex items-center gap-2 text-slate-400 hover:text-slate-900 transition-all font-black text-[10px] uppercase tracking-[0.2em] mb-3"
          >
            <div class="p-1.5 rounded-lg bg-slate-100 group-hover:bg-slate-900 group-hover:text-white transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
            </div>
            Volver al listado
          </Link>
          <h1 class="text-4xl font-black text-slate-900 tracking-tighter">Configurar Perfil</h1>
        </div>

        <div :class="['px-6 py-3 rounded-2xl border font-black text-[10px] uppercase tracking-[0.15em] shadow-sm transition-all flex items-center gap-3', 
          form.is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100']">
          <div :class="['w-2 h-2 rounded-full', form.is_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500']"></div>
          {{ form.is_active ? 'Cuenta Activa' : 'Cuenta Suspendida' }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-[3rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 overflow-hidden">
        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12">
          
          <div class="lg:col-span-4 bg-slate-50/50 dark:bg-slate-900/30 p-10 border-b lg:border-b-0 lg:border-r border-slate-100 dark:border-slate-700 text-center lg:text-left flex flex-col justify-between">
            <div>
              <div class="w-32 h-32 bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-xl border border-slate-100 dark:border-slate-700 mx-auto lg:mx-0 flex items-center justify-center text-5xl font-black text-slate-200 dark:text-slate-600 mb-8 uppercase transition-transform hover:scale-105">
                {{ form.name.charAt(0) }}
              </div>
              <h2 class="font-black text-slate-900 dark:text-white text-2xl leading-tight break-words mb-2">{{ form.name }}</h2>
              <p class="text-slate-400 font-medium text-sm mb-8 tracking-tight">{{ form.email }}</p>
              
              <div class="space-y-3">
                <div class="p-5 bg-white dark:bg-slate-800 rounded-[1.5rem] border border-slate-100 dark:border-slate-700 shadow-sm">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Rol Asignado</p>
                  <p class="text-xs font-black text-indigo-600 uppercase tracking-widest">{{ form.role }}</p>
                </div>
              </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-200/50 hidden lg:block">
              <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">ID de Sistema</p>
              <p class="text-[10px] font-mono font-bold text-slate-400">USR-{{ props.user.id.toString().padStart(5, '0') }}</p>
            </div>
          </div>

          <div class="lg:col-span-8 p-8 md:p-12 space-y-8">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-2">
                <label class="label-style">Nombre Completo</label>
                <input v-model="form.name" type="text" class="input-style" :class="{'border-rose-300': form.errors.name}" />
                <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
              </div>

              <div class="space-y-2">
                <label class="label-style">Correo Electrónico</label>
                <input v-model="form.email" type="email" class="input-style" :class="{'border-rose-300': form.errors.email}" />
                <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
              </div>
            </div>

            <div class="space-y-3 bg-amber-50/30 dark:bg-amber-900/10 p-6 rounded-[2rem] border border-amber-100/50 dark:border-amber-900/30">
              <div class="flex items-center gap-2 mb-2">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <label class="label-style !text-amber-700 dark: !mb-0">Seguridad</label>
              </div>
              <input 
                v-model="form.password" 
                type="password" 
                placeholder="Escribe una nueva contraseña solo si deseas cambiarla"
                class="input-style border-amber-100 bg-white/50 focus:ring-amber-100 focus:border-amber-300" 
              />
              <p v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
              <div class="space-y-2">
                <label class="label-style">Rol del Usuario</label>
                <select v-model="form.role" class="input-style bg-white appearance-none">
                  <option v-for="r in roles" :key="r" :value="r">{{ r.toUpperCase() }}</option>
                </select>
                <p v-if="form.errors.role" class="error-msg">{{ form.errors.role }}</p>
              </div>

              <div class="space-y-2">
                <label class="label-style">Cuatrimestre</label>
                <transition name="scale" mode="out-in">
                  <select v-if="form.role === 'alumno'" v-model="form.quarter" class="input-style bg-white border-indigo-100">
                    <option v-for="q in quarters" :key="q" :value="q">{{ q }}° Cuatrimestre</option>
                  </select>
                  <div v-else class="input-style bg-slate-100/50 text-slate-400 italic flex items-center border-dashed">
                    Inhabilitado para {{ form.role }}
                  </div>
                </transition>
              </div>
            </div>

            <div class="pt-4">
              <label class="flex items-center gap-5 p-6 rounded-[2rem] border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-all cursor-pointer group shadow-sm">
                <div class="relative inline-flex h-7 w-12 items-center rounded-full transition-all focus:outline-none"
                     :class="form.is_active ? 'bg-emerald-500 shadow-lg shadow-emerald-100' : 'bg-slate-200'">
                  <span :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                        class="inline-block h-5 w-5 transform rounded-full bg-white transition-transform shadow-sm" />
                  <input type="checkbox" v-model="form.is_active" class="hidden" />
                </div>
                <div class="flex flex-col">
                  <span class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Estatus de Acceso</span>
                  <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Permitir la entrada al panel de control</span>
                </div>
              </label>
            </div>

            <div class="pt-10 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-6">
              <Link :href="route('admin.users.index')" class="px-4 py-2 font-black text-slate-400 hover:text-rose-500 transition-colors uppercase text-[10px] tracking-[0.2em]">
                Cancelar Cambios
              </Link>
              <button
                type="submit"
                class="px-12 py-5 rounded-[2rem] font-black text-white shadow-2xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 group flex items-center gap-3"
                style="background: #9F223C;"
                :disabled="form.processing"
              >
                <span>{{ form.processing ? 'Guardando...' : 'Actualizar Perfil' }}</span>
                <svg v-if="!form.processing" class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
              </button>
            </div>
          </div>
        </form>
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
  @apply text-[10px] font-black text-rose-500 uppercase tracking-tight mt-2 ml-2;
}

.animate-fade-in {
  animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.scale-enter-active, .scale-leave-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.scale-enter-from, .scale-leave-to { opacity: 0; transform: scale(0.9) translateY(-5px); }
</style>