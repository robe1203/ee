<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount } from 'vue'

const route = window.route

const slides = ref([
  { src: new URL('../../image/img/conta1.jpg', import.meta.url).href, title: 'Dashboard Inteligente', desc: 'Control total de tus finanzas en tiempo real.' },
  { src: new URL('../../image/img/conta2.jpg', import.meta.url).href, title: 'Gestión de Pólizas', desc: 'Captura ágil con validación automática de saldos.' },
  { src: new URL('../../image/img/conta3.jpg', import.meta.url).href, title: 'Reportes Fiscales', desc: 'Genera Balanzas y Estados Financieros al instante.' },
  { src: new URL('../../image/img/conta4.jpg', import.meta.url).href, title: 'Catálogo de Cuentas', desc: 'Estructura flexible compatible con niveles del SAT.' },
])

const bgImage = new URL('../../image/img/conta5.png', import.meta.url).href
const logoIcon = new URL('../../image/icono.png', import.meta.url).href

const current = ref(0)
const intervalMs = 5000
let timer = null
const hovering = ref(false)

const next = () => (current.value = (current.value + 1) % slides.value.length)
const prev = () => (current.value = (current.value - 1 + slides.value.length) % slides.value.length)
const goTo = (i) => (current.value = i)

const start = () => {
  stop()
  timer = setInterval(() => {
    if (!hovering.value) next()
  }, intervalMs)
}

const stop = () => { if (timer) { clearInterval(timer); timer = null; } }

onMounted(start)
onBeforeUnmount(stop)
</script>

<template>
  <Head title="Bienvenido a ContaSync" />

  <div class="min-h-screen relative overflow-x-hidden bg-slate-950 font-sans selection:bg-teal-500/30 text-slate-200 flex flex-col">
    
    <div
      class="absolute inset-0 bg-cover bg-center opacity-30 sm:opacity-40 scale-105 pointer-events-none"
      :style="{ backgroundImage: `url(${bgImage})` }"
    ></div>

    <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-950/95 to-teal-900/20 pointer-events-none"></div>
    
    <div class="absolute -top-20 -right-20 h-64 w-64 sm:h-[600px] sm:w-[600px] rounded-full bg-teal-500/10 blur-[80px] sm:blur-[120px]"></div>
    <div class="absolute top-1/2 -left-20 h-48 w-48 sm:h-[400px] sm:w-[400px] rounded-full bg-blue-500/10 blur-[80px] sm:blur-[100px]"></div>

    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-12 flex-grow w-full flex flex-col">
      
      <nav class="flex justify-between items-center mb-10 sm:mb-16">
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="logo-box">
            <img :src="logoIcon" alt="Logo" class="w-6 h-6 sm:w-7 sm:h-7 object-contain" />
          </div>
          <div>
            <h2 class="text-lg sm:text-xl font-black text-white tracking-tight leading-none">CONTA<span class="text-teal-400">SYNC</span></h2>
            <p class="text-[8px] sm:text-[9px] font-bold text-teal-500/60 uppercase tracking-[0.2em]">Accounting Software</p>
          </div>
        </div>
        <Link :href="route('login')" class="text-[10px] sm:text-sm font-bold text-white/70 hover:text-teal-400 transition-colors uppercase tracking-widest border-b border-transparent hover:border-teal-400/50 pb-1">
          Acceso <span class="hidden xs:inline">Clientes</span>
        </Link>
      </nav>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center my-auto">
        
        <div class="lg:col-span-6 space-y-8 sm:space-y-10 text-center lg:text-left order-2 lg:order-1">
          <div class="space-y-4 sm:space-y-6">
            <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 bg-white/5 border border-white/10 backdrop-blur-md">
              <span class="flex h-2 w-2 rounded-full bg-teal-500 animate-pulse shadow-[0_0_10px_#14b8a6]"></span>
              <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-teal-400">Sync Offline Habilitado</span>
            </div>

            <h1 class="text-4xl xs:text-5xl sm:text-6xl lg:text-7xl font-black text-white leading-[1.1] tracking-tighter">
              Tu contabilidad, <br class="hidden sm:block" />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-teal-400 via-emerald-400 to-teal-200">
                sin interrupciones.
              </span>
            </h1>

            <p class="text-base sm:text-lg text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
              La plataforma profesional para contadores modernos. Gestiona pólizas, catálogos y reportes con o sin conexión a internet. 
            </p>
          </div>

          <div class="flex flex-col sm:flex-row justify-center lg:justify-start gap-4">
            <Link :href="route('login')" class="btn-primary group w-full sm:w-auto">
              <span>INGRESAR AL PORTAL</span>
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </Link>
          </div>

          <div class="grid grid-cols-2 gap-4 sm:gap-6 pt-6 border-t border-white/5">
            <div>
              <p class="text-white font-bold text-xs sm:text-sm mb-1 uppercase tracking-tighter">Multiplataforma</p>
              <p class="text-slate-500 text-[10px] sm:text-xs">Accede desde cualquier dispositivo.</p>
            </div>
            <div>
              <p class="text-white font-bold text-xs sm:text-sm mb-1 uppercase tracking-tighter">Sincronía Total</p>
              <p class="text-slate-500 text-[10px] sm:text-xs">Respaldo automático en la nube.</p>
            </div>
          </div>
        </div>

        <div class="lg:col-span-6 relative order-1 lg:order-2">
          <div 
            class="slider-frame group"
            @mouseenter="hovering = true"
            @mouseleave="hovering = false"
          >
            <div class="relative aspect-video sm:aspect-[16/10] lg:aspect-video overflow-hidden rounded-xl sm:rounded-2xl bg-slate-900">
              <transition-group name="fade">
                <div 
                  v-for="(s, i) in slides" 
                  v-show="i === current" 
                  :key="s.src"
                  class="absolute inset-0"
                >
                  <img :src="s.src" :alt="s.title" class="w-full h-full object-cover" />
                  <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent"></div>
                  
                  <div class="absolute bottom-0 left-0 p-4 sm:p-8 w-full">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-1">{{ s.title }}</h3>
                    <p class="text-slate-300 text-xs sm:text-sm line-clamp-1 sm:line-clamp-none">{{ s.desc }}</p>
                  </div>
                </div>
              </transition-group>

              <button @click="prev" class="slider-nav-btn left-2 sm:left-4 hidden sm:flex">‹</button>
              <button @click="next" class="slider-nav-btn right-2 sm:right-4 hidden sm:flex">›</button>
            </div>

            <div class="flex justify-center gap-2 mt-4 sm:mt-6">
              <button
                v-for="(_, i) in slides"
                :key="i"
                @click="goTo(i)"
                class="dot"
                :class="{ 'active': i === current }"
                :aria-label="'Slide ' + (i + 1)"
              ></button>
            </div>
          </div>
        </div>
      </div>

      <footer class="mt-auto pt-8 pb-4 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4">
        <p class="text-[9px] sm:text-[10px] font-bold text-slate-600 uppercase tracking-[0.4em] text-center sm:text-left">
          © 2026 ContaSync <span class="hidden xs:inline">· UTECAN Professional Edition</span>
        </p>
        <div class="flex gap-6 sm:gap-8 text-[9px] sm:text-[10px] font-bold text-slate-600 uppercase tracking-widest">
          <a href="#" class="hover:text-teal-400 transition-colors">Seguridad</a>
          <a href="#" class="hover:text-teal-400 transition-colors">Privacidad</a>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
/* Contenedor del Logo ajustable */
.logo-box {
  @apply h-9 w-9 sm:h-11 sm:w-11 rounded-lg sm:rounded-xl flex items-center justify-center bg-gradient-to-br from-teal-500 to-slate-900 shadow-lg shadow-teal-500/20;
}

/* Botón Principal */
.btn-primary {
  @apply inline-flex items-center justify-center gap-3 px-6 py-3 sm:px-8 sm:py-4 bg-teal-500 text-slate-950 font-black text-[10px] sm:text-xs tracking-[0.2em]
         rounded-lg sm:rounded-xl transition-all duration-300 hover:bg-teal-400 hover:shadow-[0_0_30px_rgba(20,184,166,0.3)]
         active:scale-95;
}

/* Slider Frame con padding dinámico */
.slider-frame {
  @apply p-1.5 sm:p-2 rounded-[1.5rem] sm:rounded-[2rem] bg-white/5 border border-white/10 backdrop-blur-xl shadow-2xl;
}

.slider-nav-btn {
  @apply absolute top-1/2 -translate-y-1/2 h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-black/40 backdrop-blur-md border border-white/10 
         items-center justify-center text-white text-xl opacity-0 group-hover:opacity-100 transition-all hover:bg-teal-500 hover:border-teal-400;
}

.dot {
  @apply h-1 sm:h-1.5 rounded-full bg-white/10 transition-all duration-500;
  width: 8px;
}
@media (min-width: 640px) {
  .dot { width: 12px; }
}

.dot.active {
  @apply bg-teal-500 shadow-[0_0_10px_#14b8a6];
  width: 24px;
}
@media (min-width: 640px) {
  .dot.active { width: 32px; }
}

/* Transición de Imágenes */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.6s ease-in-out, transform 0.6s ease-in-out;
}
.fade-enter-from { opacity: 0; transform: scale(1.03); }
.fade-leave-to { opacity: 0; transform: scale(0.97); }

/* Soporte para pantallas muy pequeñas */
@container (max-width: 350px) {
  h1 { @apply text-3xl; }
}
</style>