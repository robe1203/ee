<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen relative overflow-hidden">
    <!-- Fondo (gradient + decoraciones) -->
    <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950"></div>
    <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-cyan-400/20 blur-3xl"></div>
    <div class="absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>

    <div class="relative z-10 min-h-screen flex flex-col items-center justify-center px-4 py-10">
      <!-- Encabezado -->
      <div class="w-full max-w-md text-center">
        <Link href="/" class="inline-flex items-center justify-center gap-3">
          <div
            class="h-14 w-14 rounded-2xl grid place-items-center shadow-lg text-slate-950 font-black"
            style="background: linear-gradient(135deg, #22d3ee, #818cf8);"
          >
            CS
          </div>

          <!-- Si quieres usar el logo de Breeze, déjalo visible -->
          <ApplicationLogo class="hidden w-10 h-10 fill-current text-white/80" />

          <div class="text-left">
            <p class="text-xs text-white/70 leading-none">Sistema Contable</p>
            <p class="text-xl font-extrabold text-white leading-none">ContaSync</p>
          </div>
        </Link>

        <p class="mt-3 text-sm text-white/70">
          Acceso seguro • Offline-first • Multiplataforma
        </p>
      </div>

      <!-- Card -->
      <div class="w-full max-w-md mt-6">
        <div
          class="bg-white/10 backdrop-blur-xl border border-white/12 shadow-2xl rounded-3xl p-6 sm:p-7"
        >
          <slot />
        </div>

        <p class="mt-5 text-center text-xs text-white/60">
          © {{ new Date().getFullYear() }} ContaSync. Todos los derechos reservados.
        </p>
      </div>
    </div>
  </div>
</template>
