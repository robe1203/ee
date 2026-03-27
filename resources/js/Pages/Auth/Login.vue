<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

// Assets
const bgImage = new URL('../../../image/img/conta5.png', import.meta.url).href
const logoIcon = new URL('../../../image/icono.png', import.meta.url).href

defineProps({ canResetPassword: Boolean, status: String })

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), { onFinish: () => form.reset('password') })
}
</script>

<template>
  <Head title="Iniciar sesión" />

  <div class="min-h-screen relative overflow-hidden flex items-center justify-center font-sans selection:bg-teal-500/30 p-4 sm:p-6">
    
    <div
      class="absolute inset-0 bg-cover bg-center scale-110 animate-subtle-zoom pointer-events-none"
      :style="{ backgroundImage: `url(${bgImage})` }"
    ></div>

    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-[2px]"></div>
    <div
      class="absolute inset-0 pointer-events-none"
      style="background: radial-gradient(circle at 50% 50%, rgba(20,184,166,0.15), transparent 80%);"
    ></div>

    <div class="relative z-10 w-full max-w-[420px] mx-auto animate-fade-in-up">
      
      <div class="glass-card overflow-hidden">
        <div class="p-6 sm:p-8 pb-4 text-center">
          <div class="perspective-container flex justify-center mb-4 sm:mb-6">
            <div class="logo-3d-box floating-icon">
              <img :src="logoIcon" alt="Logo" class="w-8 h-8 sm:w-10 sm:h-10 object-contain relative z-10" />
              <div class="shine-effect"></div>
            </div>
          </div>
          
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-none mb-2">
            Conta<span class="text-teal-400">Sync</span>
          </h1>
          <p class="text-slate-300/70 text-[10px] sm:text-sm font-medium tracking-widest uppercase">Sistema Contable Profesional</p>
        </div>

        <div class="px-6 sm:px-8 pb-8 sm:pb-10">
          <div
            v-if="status"
            class="mb-6 text-xs sm:text-sm font-bold text-emerald-300 bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-3 sm:p-4 text-center animate-pulse"
          >
            {{ status }}
          </div>

          <form @submit.prevent="submit" class="space-y-4 sm:space-y-5">
            <div class="group">
              <InputLabel for="email" value="Correo Electrónico" class="!text-slate-300 !text-[10px] sm:!text-xs !font-black !uppercase !tracking-widest !ml-1 !mb-2" />
              <div class="relative">
                <TextInput
                  id="email"
                  type="email"
                  class="login-input pl-11 sm:pl-12"
                  v-model="form.email"
                  required
                  autofocus
                  autocomplete="username"
                  placeholder="ejemplo@empresa.com"
                />
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg sm:text-xl group-focus-within:scale-110 transition-transform">✉️</span>
              </div>
              <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="group">
              <InputLabel for="password" value="Contraseña" class="!text-slate-300 !text-[10px] sm:!text-xs !font-black !uppercase !tracking-widest !ml-1 !mb-2" />
              <div class="relative">
                <TextInput
                  id="password"
                  type="password"
                  class="login-input pl-11 sm:pl-12"
                  v-model="form.password"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                />
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg sm:text-xl group-focus-within:scale-110 transition-transform">🔒</span>
              </div>
              <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex flex-col xs:flex-row items-start xs:items-center justify-between gap-3 px-1">
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer group">
                <Checkbox v-model:checked="form.remember" class="!rounded-md !bg-white/10 !border-white/20 !text-teal-500" />
                <span class="text-xs sm:text-sm text-slate-300 group-hover:text-white transition-colors">Recordarme</span>
              </label>

              <Link v-if="canResetPassword" :href="route('password.request')" class="text-[10px] sm:text-xs font-bold text-teal-400 hover:text-teal-300 transition-colors uppercase tracking-tighter">
                ¿Olvidaste tu acceso?
              </Link>
            </div>

            <PrimaryButton
              class="login-btn group"
              :class="{ 'opacity-50 pointer-events-none': form.processing }"
              :disabled="form.processing"
            >
              <span class="relative z-10">ACCEDER AL PORTAL</span>
              <div class="btn-shine"></div>
            </PrimaryButton>

            <div class="pt-6 border-t border-white/10">
              <div class="flex items-center justify-center gap-3 mb-4">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></div>
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-tighter">Sync Offline Habilitado</span>
              </div>
              
              <Link
                :href="route('welcome')"
                class="block text-center text-xs sm:text-sm font-bold text-white/50 hover:text-white transition-all uppercase tracking-[0.2em]"
              >
                ← Volver al inicio
              </Link>
            </div>
          </form>
        </div>
      </div>

      <p class="mt-6 sm:mt-8 text-center text-[9px] sm:text-xs font-bold text-slate-400/60 uppercase tracking-[0.3em] px-4">
        © {{ new Date().getFullYear() }} ContaSync · UTECAN
      </p>
    </div>
  </div>
</template>

<style scoped>
/* Glassmorphism Card Adaptativo */
.glass-card {
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 1.5rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
}
@media (min-width: 640px) {
  .glass-card { border-radius: 2rem; }
}

/* Inputs Premium */
.login-input {
  @apply block w-full !py-3 sm:!py-4 !rounded-xl sm:!rounded-2xl !border-white/10 !bg-white/5 text-white text-sm placeholder:text-slate-500
         focus:!border-teal-400/50 focus:!ring-teal-400/20 transition-all duration-300;
}

/* Botón adaptable */
.login-btn {
  @apply w-full justify-center !py-3.5 sm:!py-4 !rounded-xl sm:!rounded-2xl font-black text-[11px] sm:text-sm tracking-[0.2em] relative overflow-hidden transition-all duration-500 active:scale-[0.98];
  background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
  box-shadow: 0 10px 20px -5px rgba(20, 184, 166, 0.4);
}

.btn-shine {
  @apply absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transform: skewX(-20deg) translateX(-150%);
  animation: shine-btn 3s infinite;
}

@keyframes shine-btn {
  20%, 100% { transform: skewX(-20deg) translateX(150%); }
}

/* Logo 3D Adaptativo */
.perspective-container { perspective: 1000px; }
.logo-3d-box {
  @apply h-16 w-16 sm:h-20 sm:w-20 rounded-[1.5rem] sm:rounded-[2rem] flex items-center justify-center relative overflow-hidden;
  background: linear-gradient(135deg, #14b8a6 0%, #0f172a 100%);
  box-shadow: 0 15px 30px -10px rgba(20, 184, 166, 0.5);
  transform-style: preserve-3d;
}

.floating-icon {
  animation: float 4s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0) rotateX(0); }
  50% { transform: translateY(-8px) rotateX(10deg); }
}

.animate-subtle-zoom {
  animation: subtleZoom 20s infinite alternate ease-in-out;
}

@keyframes subtleZoom {
  from { transform: scale(1.05); }
  to { transform: scale(1.15) rotate(1deg); }
}

.animate-fade-in-up {
  animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Ajustes para pantallas con poco espacio vertical (Landscape en móviles) */
@media (max-height: 600px) {
  .logo-3d-box { @apply h-12 w-12 rounded-xl mb-2; }
  h1 { @apply text-xl; }
  .p-6 { @apply py-4; }
  .space-y-5 > * + * { @apply mt-3; }
}
</style>