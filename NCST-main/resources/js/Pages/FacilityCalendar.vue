<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    reservations: { type: Array, default: () => [] }
});

const currentDate = ref(new Date());
const selectedDate = ref(new Date());

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const days = new Date(year, month + 1, 0).getDate();
    return Array.from({ length: days }, (_, i) => i + 1);
});

const firstDayOfMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    return new Date(year, month, 1).getDay();
});

const monthName = computed(() => {
    return currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const previousMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};

const selectDate = (day) => {
    selectedDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day);
};

const isToday = (day) => {
    const today = new Date();
    return today.getDate() === day && today.getMonth() === currentDate.value.getMonth() && today.getFullYear() === currentDate.value.getFullYear();
};

const isSelected = (day) => {
    return selectedDate.value.getDate() === day && selectedDate.value.getMonth() === currentDate.value.getMonth() && selectedDate.value.getFullYear() === currentDate.value.getFullYear();
};

const formatDateString = (dateObj) => {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

// Map reservations by date
const reservationsByDate = computed(() => {
    const map = {};
    props.reservations.forEach(res => {
        let dateStr = null;
        
        // Try parsing remarks for date_of_event
        try {
            if (res.remarks) {
                const remarks = typeof res.remarks === 'string' ? JSON.parse(res.remarks) : res.remarks;
                if (remarks && remarks.date_of_event) {
                    // Assuming date_of_event is YYYY-MM-DD format
                    dateStr = remarks.date_of_event;
                }
            }
        } catch(e) {
            console.error('Error parsing remarks', e);
        }

        if (!dateStr) {
            if (res.duration && res.duration.includes(' to ')) {
                // Duration format expected: "YYYY-MM-DD HH:mm to YYYY-MM-DD HH:mm"
                const parts = res.duration.split(' to ');
                if (parts.length > 0) {
                    dateStr = parts[0].split(' ')[0]; // Extract YYYY-MM-DD
                }
            } else if (res.borrowed_at) {
                dateStr = res.borrowed_at.split('T')[0];
            } else if (res.duration) {
                dateStr = res.duration.split(' ')[0];
            } else {
                dateStr = res.created_at.split('T')[0];
            }
        }
        
        if (dateStr) {
            if (!map[dateStr]) {
                map[dateStr] = [];
            }
            map[dateStr].push(res);
        }
    });
    return map;
});

const hasReservations = (day) => {
    const dateStr = formatDateString(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day));
    return reservationsByDate.value[dateStr] && reservationsByDate.value[dateStr].length > 0;
};

const selectedDateReservations = computed(() => {
    const dateStr = formatDateString(selectedDate.value);
    return reservationsByDate.value[dateStr] || [];
});

const getStatusColor = (status) => {
    if (status === 'APPROVED' || status === 'ACTIVE') return 'text-green-400 bg-green-400/10 border-green-400/20';
    if (status === 'PENDING_APPROVAL') return 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20';
    return 'text-gray-600 bg-gray-100 border-gray-300';
};
</script>

<template>
    <Head title="Facility Calendar" />

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                Facility Calendar
            </h1>
            <p class="text-gray-600 mt-1">View scheduled events and facility availability.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Calendar Section -->
            <div class="lg:col-span-2 bg-gray-100 border border-gray-300 rounded-3xl p-6 md:p-8">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-2xl font-bold text-gray-900">{{ monthName }}</h2>
                    <div class="flex gap-2">
                        <button @click="previousMonth" class="p-2 rounded-xl bg-gray-100 hover:bg-white/10 text-gray-900 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button @click="nextMonth" class="p-2 rounded-xl bg-gray-100 hover:bg-white/10 text-gray-900 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-2 md:gap-4 mb-2">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                        {{ day }}
                    </div>
                </div>
                
                <div class="grid grid-cols-7 gap-2 md:gap-4">
                    <!-- Empty slots for first day of month -->
                    <div v-for="empty in firstDayOfMonth" :key="'empty-'+empty" class="aspect-square"></div>
                    
                    <!-- Days -->
                    <button 
                        v-for="day in daysInMonth" 
                        :key="day"
                        @click="selectDate(day)"
                        class="aspect-square rounded-2xl flex flex-col items-center justify-center relative transition-all group"
                        :class="[
                            isSelected(day) ? 'bg-ncst-gold text-black shadow-[0_0_20px_rgba(252,211,77,0.4)] scale-105 z-10' : 'bg-gray-100 hover:bg-white/10 text-gray-900',
                            isToday(day) && !isSelected(day) ? 'border-2 border-blue-200' : 'border border-transparent'
                        ]"
                    >
                        <span class="text-lg font-bold">{{ day }}</span>
                        
                        <!-- Event Indicator -->
                        <div v-if="hasReservations(day)" class="absolute bottom-2 flex gap-1">
                            <div class="w-1.5 h-1.5 rounded-full bg-current" :class="isSelected(day) ? 'bg-black/50' : 'bg-ncst-gold'"></div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Side Panel (Selected Date Details) -->
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-white to-gray-50 border border-gray-300 rounded-3xl p-6 md:p-8 relative overflow-hidden">
                    <div class="absolute -right-12 -top-12 w-40 h-40 bg-ncst-gold/10 rounded-full blur-3xl"></div>
                    
                    <h3 class="text-sm font-bold text-blue-600 uppercase tracking-widest mb-1">Schedule For</h3>
                    <div class="text-3xl font-black text-gray-900 mb-8">
                        {{ selectedDate.toLocaleString('default', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                    </div>

                    <div v-if="selectedDateReservations.length === 0" class="text-center py-12">
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <p class="text-gray-600 text-sm font-medium">No reservations for this date.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="res in selectedDateReservations" :key="res.id" class="bg-black/30 rounded-2xl p-4 border border-gray-300 relative group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-2xl" :class="res.status === 'APPROVED' || res.status === 'ACTIVE' ? 'bg-green-500' : 'bg-yellow-500'"></div>
                            
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-gray-900 pr-4 leading-tight">{{ res.purpose }}</h4>
                                <span class="px-2 py-0.5 rounded text-[9px] font-black tracking-widest uppercase border whitespace-nowrap" :class="getStatusColor(res.status)">
                                    {{ res.status === 'PENDING_APPROVAL' ? 'Pending' : 'Approved' }}
                                </span>
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center gap-2 text-gray-500">
                                    <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    <span class="font-medium truncate">{{ res.resource?.name }}</span>
                                </div>
                                
                                <div class="flex items-center gap-2 text-gray-500">
                                    <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span class="text-xs">{{ res.duration ? res.duration : 'TBA' }}</span>
                                </div>

                                <div class="flex items-center gap-2 text-gray-500 pt-2 border-t border-gray-300 mt-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    <span class="text-xs truncate">Req. by {{ res.user?.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
