<script setup>
import { Head, router } from '@inertiajs/vue3';
import { UserIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    doctors: Array,
});

function selectDoctor(doctorId) {
    router.visit(`/appointments/calendar?doctor=${doctorId}`);
}
</script>

<template>
    <Head title="Selecciona tu Médico" />

    <div class="min-h-screen bg-gradient-to-br from-green-50 to-teal-50">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="container mx-auto px-4 py-6">
                <div class="flex justify-between items-center">
                    <a href="/" class="flex items-center space-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-gradient-to-r from-green-500 to-teal-500 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-green-700">Nutritive Agent</span>
                    </a>
                    <a href="/" class="text-gray-600 hover:text-green-600 transition-colors">
                        ← Volver al inicio
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-12">
            <div class="max-w-4xl mx-auto">
                <!-- Title Section -->
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">
                        Selecciona tu Médico
                    </h1>
                    <p class="text-xl text-gray-600">
                        Elige el especialista con el que deseas agendar tu cita
                    </p>
                </div>

                <!-- Doctors Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <button
                        v-for="doctor in doctors"
                        :key="doctor.id"
                        @click="selectDoctor(doctor.id)"
                        class="bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 p-8 text-left border-2 border-transparent hover:border-green-500 group"
                    >
                        <div class="flex items-start space-x-4">
                            <!-- Doctor Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                    <UserIcon class="w-8 h-8 text-white" />
                                </div>
                            </div>

                            <!-- Doctor Info -->
                            <div class="flex-1">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2 group-hover:text-green-600 transition-colors">
                                    {{ doctor.name }}
                                </h3>
                                <p class="text-gray-600 mb-3 flex items-center">
                                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    {{ doctor.speciality }}
                                </p>
                                <div v-if="doctor.shift" class="text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2 inline-block">
                                    🕐 {{ doctor.shift.start }} - {{ doctor.shift.end }}
                                </div>
                            </div>

                            <!-- Arrow Icon -->
                            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="!doctors || doctors.length === 0" class="text-center py-12">
                    <div class="inline-block w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <UserIcon class="w-8 h-8 text-gray-400" />
                    </div>
                    <p class="text-gray-500 text-lg">No hay médicos disponibles en este momento</p>
                </div>
            </div>
        </div>
    </div>
</template>
