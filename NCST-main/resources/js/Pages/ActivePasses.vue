<script setup>
import { usePage, Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const reservations = computed(() => page.props.reservations || []);

import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useAlert } from '@/utils/alert';

const alert = useAlert();
const selectedPass = ref(null);
const returnPass = ref(null);
const savedScreenshots = ref([]);
const now = ref(new Date());
let timerInterval;

const hasAlerted1Min = ref(new Set());
const hasAlertedOverdue = ref(new Set());
const hasAlertedSuspended = ref(new Set());

watch(now, () => {
    reservations.value.filter(r => r.status === 'BORROWED').forEach(r => {
        if (!r.due_date) return;
        const diffSecs = (new Date(r.due_date) - now.value) / 1000;
        
        if (diffSecs <= 60 && diffSecs > 0 && !hasAlerted1Min.value.has(r.id)) {
            alert.warning('Warning', `Item ${r.resource?.name} is due in less than 1 minute!`);
            hasAlerted1Min.value.add(r.id);
        } else if (diffSecs <= 0 && !hasAlertedSuspended.value.has(r.id)) {
            alert.error('Suspended', `Your account is suspended due to overdue item: ${r.resource?.name}.`);
            hasAlertedSuspended.value.add(r.id);
        }
    });
});

onMounted(() => {
    savedScreenshots.value = JSON.parse(localStorage.getItem('saved_screenshots') || '[]');
    timerInterval = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(timerInterval);
});

const isOneMinuteLeft = (dueDate) => {
    if (!dueDate) return false;
    const dueTime = new Date(dueDate).getTime();
    const currentTime = now.value.getTime();
    const diffSeconds = (dueTime - currentTime) / 1000;
    // Alert if between 0 and 60 seconds remaining
    return diffSeconds > 0 && diffSeconds <= 60;
};

const isOverdue = (dueDate) => {
    if (!dueDate) return false;
    const dueTime = new Date(dueDate).getTime();
    const currentTime = now.value.getTime();
    return currentTime > dueTime;
};

const markScreenshotSaved = (id) => {
    if (!savedScreenshots.value.includes(id)) {
        savedScreenshots.value.push(id);
        localStorage.setItem('saved_screenshots', JSON.stringify(savedScreenshots.value));
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
    });
};

const getTimeLeftString = (dueDate) => {
    if (!dueDate) return '';
    const diff = new Date(dueDate) - now.value;
    if (diff <= 0) {
        const overdueSecs = Math.abs(diff / 1000);
        if (overdueSecs > 30) return 'Suspended';
        return `Overdue (-${Math.floor(overdueSecs)}s)`;
    }
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return `${m}m ${s}s left`;
};
</script>

<template>
    <Head title="Active QR Passes" />

    <div class="py-10 space-y-8">
        <!-- Header -->
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Active QR Passes</h1>
            <p class="text-gray-600 text-sm mt-1">Here are your approved items. Present these QR codes at the counter to pick them up.</p>
        </div>

        <!-- Empty State -->
        <div v-if="reservations.length === 0" class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-16 text-center shadow-lg">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            <p class="text-gray-700 font-bold text-xl">No Active Passes</p>
            <p class="text-gray-500 mt-2 max-w-md mx-auto">You don't have any approved equipment ready for pickup right now.</p>
        </div>

        <!-- Passes Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="reservation in reservations" 
                :key="reservation.id"
                @click="selectedPass = reservation"
                class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 overflow-hidden shadow-lg flex flex-col cursor-pointer hover:border-blue-200 hover:shadow-[0_0_20px_rgba(245,196,0,0.15)] transition-all group relative"
            >
                <div class="absolute inset-0 bg-ncst-gold/5 opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none"></div>

                <!-- Alert for 1 minute remaining -->
                <div v-if="reservation.status === 'BORROWED' && isOneMinuteLeft(reservation.due_date)" class="bg-red-500/20 border-b border-red-500/50 p-2 text-center relative z-20">
                    <p class="text-red-400 text-xs font-bold animate-pulse">⚠️ Due in less than 1 minute! Please return soon.</p>
                </div>
                <!-- Alert for overdue -->
                <div v-if="reservation.status === 'OVERDUE' || (reservation.status === 'BORROWED' && isOverdue(reservation.due_date))" class="bg-red-500/80 border-b border-red-500 p-2 text-center relative z-20">
                    <p class="text-gray-900 text-xs font-bold">⚠️ OVERDUE! Please return immediately.</p>
                </div>

                <div class="p-6 bg-gray-100 border-b border-gray-300 text-center relative z-0">
                    <h3 class="font-bold text-gray-900 text-lg">{{ reservation.resource?.name }}</h3>
                    <p class="text-blue-600 text-xs font-mono mt-1">{{ reservation.resource?.asset_code }}</p>
                </div>
                
                <div class="p-6 flex flex-col items-center justify-center flex-1 bg-white relative z-0">
                    <!-- QR Code -->
                    <img :src="`/qr/${reservation.id}`" alt="QR Code" class="w-48 h-48 object-contain transition-transform group-hover:scale-105">
                    <p class="text-gray-400 text-[10px] mt-4 font-mono group-hover:text-white transition-colors">Click to view full details</p>
                </div>

                <div class="p-4 bg-white shadow-sm border border-gray-300 border-t border-gray-300 space-y-2 relative z-0">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-600">Requested On:</span>
                        <span class="text-gray-900 font-medium">{{ formatDate(reservation.created_at) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-600">Status:</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border whitespace-nowrap bg-blue-500/10 text-blue-400 border-blue-500/20">
                            {{ reservation.status.replace(/_/g, ' ') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="selectedPass" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="selectedPass = null">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-gray-50 border border-gray-200 rounded-2xl p-0 w-full max-w-md shadow-2xl z-10 flex flex-col overflow-hidden">
                        
                        <!-- Header -->
                        <div class="p-6 bg-gray-100 border-b border-gray-300 flex justify-between items-start">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ selectedPass.resource?.name }}</h3>
                                <p class="text-blue-600 text-sm font-mono mt-1">{{ selectedPass.resource?.asset_code }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border whitespace-nowrap bg-blue-500/10 text-blue-400 border-blue-500/20">
                                {{ selectedPass.status.replace(/_/g, ' ') }}
                            </span>
                        </div>

                        <!-- Content -->
                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4 bg-black/20 p-4 rounded-xl border border-gray-300">
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Requested On</p>
                                    <p class="text-sm text-gray-900">{{ formatDate(selectedPass.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Approved On</p>
                                    <p class="text-sm text-gray-900">{{ formatDate(selectedPass.approved_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Borrowed On</p>
                                    <p class="text-sm text-gray-900">{{ selectedPass.borrowed_at ? formatDate(selectedPass.borrowed_at) : 'Not picked up yet' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-blue-600/70 uppercase tracking-wider font-bold mb-1">Due Date & Timer</p>
                                    <p class="text-sm text-blue-600 font-bold">{{ selectedPass.due_date ? formatDate(selectedPass.due_date) : 'Pending pickup' }}</p>
                                    <p v-if="selectedPass.due_date" class="text-xs font-mono font-bold mt-1" :class="{'text-red-400': isOverdue(selectedPass.due_date), 'text-yellow-400': isOneMinuteLeft(selectedPass.due_date) && !isOverdue(selectedPass.due_date), 'text-green-400': !isOneMinuteLeft(selectedPass.due_date) && !isOverdue(selectedPass.due_date)}">
                                        {{ getTimeLeftString(selectedPass.due_date) }}
                                    </p>
                                </div>
                            </div>
                            
                            <div class="bg-black/20 p-4 rounded-xl border border-gray-300 space-y-3">
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Requested Duration</p>
                                    <p class="text-sm text-gray-900 font-medium">{{ selectedPass.duration }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Purpose / Reason</p>
                                    <p class="text-sm text-gray-700 italic">"{{ selectedPass.purpose || 'No specific purpose provided.' }}"</p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-4 border-t border-gray-300 bg-black/20 flex gap-3">
                            <button @click="selectedPass = null" class="flex-1 py-3 bg-white/10 hover:bg-white/20 text-gray-900 font-bold rounded-xl transition-all shadow-md">
                                Close Details
                            </button>
                            <button v-if="selectedPass.status === 'BORROWED' && selectedPass.resource?.type?.toLowerCase() !== 'consumable' && !savedScreenshots.includes(selectedPass.id)" 
                                @click="returnPass = selectedPass; selectedPass = null" 
                                class="flex-1 py-3 bg-blue-600 text-white hover:bg-blue-700 font-bold rounded-xl transition-all shadow-md">
                                Return Item
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Return QR Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="returnPass" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="returnPass = null">
                    <div class="fixed inset-0 bg-gray-100 backdrop-blur-md"></div>
                    <div class="relative bg-gradient-to-b from-white to-gray-50 border border-gray-200 rounded-3xl p-8 w-full max-w-sm shadow-2xl text-center z-10 overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent opacity-70"></div>
                        
                        <h3 class="text-xl font-black text-gray-900 mb-1 uppercase tracking-widest">Return Item</h3>
                        <p class="text-xs text-blue-600/70 mb-6">Present this code to the custodian, please take a picture</p>

                        <!-- QR Code -->
                        <div class="bg-white p-4 rounded-2xl mb-6 shadow-[0_0_20px_rgba(245,196,0,0.15)] border-4 border-blue-200 inline-block">
                            <img :src="`/qr/${returnPass.id}`" alt="Return QR Code" class="w-48 h-48 object-contain mix-blend-multiply">
                        </div>

                        <p class="font-bold text-gray-900">{{ returnPass.resource?.name }}</p>
                        <p class="text-[10px] text-gray-600 mt-1 mb-6 font-mono">Token: {{ returnPass.pass_qr_token }}</p>

                        <div class="w-full flex flex-col gap-3">
                            <a :href="`/qr/${returnPass.id}`" download="NCST_Return_QR.png" @click="markScreenshotSaved(returnPass.id)" class="w-full flex justify-center items-center py-3 bg-blue-600 text-white font-bold text-sm rounded-xl hover:bg-blue-800 transition-all shadow-md">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Save Screenshot
                            </a>
                            <button @click="returnPass = null"
                                class="w-full py-3 bg-white/10 text-gray-900 font-bold text-sm rounded-xl hover:bg-white/20 transition-all shadow-md">
                                Done
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
