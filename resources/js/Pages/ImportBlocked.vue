<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// Mensaje del backend
const message = computed(() => 
    page.props.flash?.message ?? 
    'Intentaste importar una empresa que ya existe en el sistema.'
)

// GIFS
const gifCopiar = new URL('../../image/gif/copiar.gif', import.meta.url).href
const gifShrek = new URL('../../image/gif/shrek.gif', import.meta.url).href

// Lista de gifs
const gifs = [gifCopiar, gifShrek]

// Elegir uno aleatorio
const randomGif = gifs[Math.floor(Math.random() * gifs.length)]
</script>

<template>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-200 p-4">
    
    <div class="bg-white shadow-2xl rounded-3xl overflow-hidden max-w-lg w-full transform transition-all">
        
        <div class="h-2 bg-red-500"></div>

        <div class="p-8 md:p-12 text-center">

            <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 text-red-600 rounded-full mb-6">
                <span class="text-4xl">🚫</span>
            </div>

            <h1 class="text-3xl font-extrabold text-gray-800 mb-4 tracking-tight">
                Copiar <span class="text-red-600">no es</span> aprender
            </h1>

            <div class="space-y-4 mb-8">
                <p class="text-gray-700 font-medium text-lg leading-relaxed">
                    {{ message }}
                </p>

                <p class="text-gray-500 leading-relaxed">
                    Este sistema está diseñado para que desarrolles tus habilidades contables. 
                    <span class="block mt-2 font-semibold italic text-gray-400">
                        "El éxito es la suma de pequeños esfuerzos repetidos día tras día."
                    </span>
                </p>
            </div>

            <!-- GIF ALEATORIO -->
            <div class="flex justify-center mb-8">
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-red-400 to-blue-400 rounded-2xl blur opacity-25 group-hover:opacity-50 transition duration-1000"></div>

                    <img 
                        :src="randomGif"
                        alt="No copiar"
                        class="relative rounded-xl w-80 shadow-md object-cover border border-gray-100"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <Link
                    href="/app/company"
                    class="inline-flex items-center justify-center px-8 py-4 bg-gray-900 text-white rounded-2xl font-bold text-lg hover:bg-red-600 transform hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-red-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver al sistema
                </Link>
            </div>
        </div>

        <div class="bg-gray-50 py-4 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">
              JAJAJAJAJAJAJAJAJAJAJAJAJAJAJ
            </p>
        </div>

    </div>

</div>
</template>

<style scoped>

div {
animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
from { opacity: 0; transform: translateY(10px); }
to { opacity: 1; transform: translateY(0); }
}

</style>