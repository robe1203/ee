<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'

const props = defineProps({
  show: Boolean,
  modelValue: { type: [Number, String], default: 0 },
})

const emit = defineEmits(['close', 'apply'])

const display = ref('0')
const history = ref('') // Para mostrar la operación previa

// Reiniciar estado al abrir
watch(() => props.show, (v) => {
  if (v) {
    display.value = String(props.modelValue ?? 0)
    if (!display.value || display.value === 'NaN') display.value = '0'
    history.value = ''
  }
})

function appendChar(ch) {
  const isOp = (c) => ['+', '-', '*', '/'].includes(c)
  const lastChar = display.value.slice(-1)

  // Si el valor es '0' y no es un punto, reemplazar
  if (display.value === '0' && ch !== '.') {
    if (!isOp(ch)) {
      display.value = ch
      return
    }
  }

  // Evitar operadores dobles
  if (isOp(lastChar) && isOp(ch)) {
    display.value = display.value.slice(0, -1) + ch
    return
  }

  // Lógica de decimales por segmento
  if (ch === '.') {
    const segments = display.value.split(/[+\-*/]/)
    const currentSegment = segments[segments.length - 1]
    if (currentSegment.includes('.')) return
  }

  display.value += ch
}

function calculate() {
  try {
    // Sanitización básica antes de evaluar
    const expr = display.value.replace(/[^-()\d/*+.]/g, '')
    // eslint-disable-next-line no-new-func
    const result = Function(`"use strict"; return (${expr})`)()
    
    if (!isFinite(result)) throw new Error()
    
    history.value = display.value + ' ='
    display.value = String(Number(result.toFixed(4))) // Hasta 4 decimales en pantalla
  } catch {
    display.value = 'Error'
    setTimeout(() => (display.value = '0'), 1000)
  }
}

function backspace() {
  display.value = display.value.length > 1 ? display.value.slice(0, -1) : '0'
}

function pressKey(val) {
  if (val === '=') calculate()
  else if (val === 'C') { display.value = '0'; history.value = ''; }
  else if (val === '⌫') backspace()
  else appendChar(val)
}

function apply() {
  calculate()
  const finalValue = parseFloat(display.value)
  emit('apply', isNaN(finalValue) ? 0 : Number(finalValue.toFixed(2)))
  emit('close')
}

// Global Keyboard Listeners
function onKeydown(e) {
  if (!props.show) return
  const k = e.key
  if (k === 'Escape') emit('close')
  else if (k === 'Enter') { e.preventDefault(); apply(); }
  else if (k === 'Backspace') { e.preventDefault(); backspace(); }
  else if (k === 'Delete') { e.preventDefault(); display.value = '0'; }
  else if (/[0-9.+\-*/]/.test(k)) { e.preventDefault(); appendChar(k); }
  else if (k === '=') { e.preventDefault(); calculate(); }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Transition name="fade">
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="$emit('close')"></div>

      <div class="relative bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 w-full max-w-[340px] overflow-hidden animate-pop">
        
        <div class="p-5 pb-2 flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Calculadora Auxiliar</span>
          <button @click="$emit('close')" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="px-6 py-2">
          <div class="bg-slate-50 rounded-3xl p-4 border border-slate-100 shadow-inner">
            <div class="h-5 text-right text-xs font-bold text-slate-400 mb-1 overflow-hidden font-mono uppercase">
              {{ history }}
            </div>
            <div class="text-right text-3xl font-black text-slate-900 overflow-x-auto whitespace-nowrap font-mono tracking-tighter">
              {{ display }}
            </div>
          </div>
        </div>

        <div class="p-6 grid grid-cols-4 gap-2">
          <button @click="pressKey('C')" class="btn-calc bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white">C</button>
          <button @click="pressKey('⌫')" class="btn-calc bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white">⌫</button>
          <button @click="pressKey('/')" class="btn-calc bg-maroon-50 text-maroon-700 font-black">÷</button>
          <button @click="pressKey('*')" class="btn-calc bg-maroon-50 text-maroon-700 font-black">×</button>

          <button v-for="n in ['7','8','9','-','4','5','6','+','1','2','3']" :key="n" 
            @click="pressKey(n)" 
            :class="['btn-calc', isNaN(n) ? 'bg-maroon-50 text-maroon-700 font-black' : 'bg-white border border-slate-100 hover:border-slate-300 text-slate-700 font-bold shadow-sm']">
            {{ n }}
          </button>
          
          <button @click="calculate" class="btn-calc text-white font-black" style="background: #9F223C">=</button>

          <button @click="pressKey('0')" class="col-span-2 btn-calc bg-white border border-slate-100 font-bold shadow-sm">0</button>
          <button @click="pressKey('.')" class="btn-calc bg-white border border-slate-100 font-bold shadow-sm">.</button>
          <button @click="apply" class="btn-calc bg-emerald-500 text-white shadow-lg shadow-emerald-200" title="Aplicar valor">
            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </button>
        </div>

        <div class="px-6 pb-6 flex gap-3">
            <button @click="$emit('close')" class="flex-1 py-3 text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-colors">
                Cancelar
            </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.btn-calc {
  @apply h-14 rounded-2xl flex items-center justify-center text-lg transition-all active:scale-90 select-none;
}

.text-maroon-700 { color: #9F223C; }
.bg-maroon-50 { background-color: rgba(159, 34, 60, 0.05); }

.animate-pop {
  animation: pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pop {
  from { opacity: 0; transform: scale(0.9) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>