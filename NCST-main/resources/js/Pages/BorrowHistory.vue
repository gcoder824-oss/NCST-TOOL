<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useAlert } from '@/utils/alert';

const alert = useAlert();
const page = usePage();
const reservations = computed(() => page.props.reservations || []);
const pageTitle = computed(() => page.props.pageTitle || 'Borrow History');
const pageDescription = computed(() => page.props.pageDescription || 'Review your past and current equipment borrowing history.');

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
    });
};

const selectedQrPass = ref(null);

const deleteReservation = (id) => {
    alert.confirm('Delete Record', 'Are you sure you want to delete this borrowing history record? This cannot be undone.').then((result) => {
        if (result.isConfirmed) {
            router.delete(`/transaction/${id}`, {
                preserveScroll: true,
                onSuccess: () => alert.success('Deleted', 'Record has been removed from your history.'),
                onError: (err) => alert.error('Error', err.error || 'Failed to delete record.')
            });
        }
    });
};

let pollInterval;
onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ only: ['reservations'], preserveState: true, preserveScroll: true });
    }, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="py-10 space-y-8">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ pageTitle }}</h1>
                <p class="text-gray-600 text-sm mt-1">{{ pageDescription }}</p>
            </div>

            <!-- Empty State -->
            <div v-if="reservations.length === 0" class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-16 text-center shadow-lg">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-gray-700 font-bold text-xl">No History Found</p>
                <p class="text-gray-500 mt-2 max-w-md mx-auto">You have not borrowed any equipment yet.</p>
            </div>

            <!-- History List -->
            <div v-else class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="text-xs uppercase bg-gray-100 text-gray-600 border-b border-gray-300">
                            <tr>
                                <th class="px-6 py-4 font-semibold tracking-wider">Item</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Asset Code</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Date Requested</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Due/Return Date</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Status</th>
                                <th class="px-6 py-4 font-semibold tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="reservation in reservations" :key="reservation.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ reservation.resource?.name || 'Unknown Item' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5 capitalize">{{ reservation.resource?.type || 'Unknown' }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-500">
                                    {{ reservation.resource?.asset_code || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 whitespace-nowrap">
                                    {{ formatDate(reservation.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 whitespace-nowrap">
                                    <div v-if="reservation.status === 'RETURNED' || reservation.status === 'DISPENSED'">
                                        <span class="text-green-400">{{ reservation.status === 'DISPENSED' ? 'Dispensed' : 'Returned' }}: {{ formatDate(reservation.status === 'DISPENSED' ? reservation.borrowed_at : reservation.returned_at) }}</span>
                                        <p class="text-xs text-gray-500 mt-0.5">Condition: {{ reservation.remarks || 'Good' }}</p>
                                    </div>
                                    <span v-else-if="['BORROWED', 'APPROVED_AWAITING_PICKUP'].includes(reservation.status) && reservation.due_date" class="text-yellow-400">Due: {{ formatDate(reservation.due_date) }}</span>
                                    <span v-else class="text-gray-500">N/A</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border whitespace-nowrap"
                                        :class="{
                                            'bg-yellow-500/10 text-yellow-400 border-yellow-500/20': reservation.status === 'PENDING_APPROVAL',
                                            'bg-blue-500/10 text-blue-400 border-blue-500/20': reservation.status === 'APPROVED_AWAITING_PICKUP',
                                            'bg-purple-500/10 text-purple-400 border-purple-500/20': reservation.status === 'BORROWED',
                                            'bg-green-500/10 text-green-400 border-green-500/20': reservation.status === 'RETURNED' || reservation.status === 'DISPENSED',
                                            'bg-red-500/10 text-red-400 border-red-500/20': reservation.status === 'REJECTED' || reservation.status === 'DECLINED'
                                        }"
                                    >
                                        {{ reservation.status.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            v-if="['APPROVED_AWAITING_PICKUP', 'BORROWED'].includes(reservation.status)"
                                            @click="selectedQrPass = reservation.id"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 border border-gray-200 rounded-lg text-xs font-bold hover:bg-ncst-gold hover:text-white transition-colors whitespace-nowrap"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                            </svg>
                                            View QR Pass
                                        </button>
                                        
                                        <button 
                                            v-if="reservation.status === 'PENDING_APPROVAL'"
                                            @click="deleteReservation(reservation.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-yellow-500/10 text-yellow-500 border border-yellow-500/30 rounded-lg text-xs font-bold hover:bg-yellow-500 hover:text-white transition-colors whitespace-nowrap"
                                            title="Cancel Request"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Cancel
                                        </button>
                                        
                                        <button 
                                            v-if="['RETURNED', 'DECLINED', 'REJECTED'].includes(reservation.status)"
                                            @click="deleteReservation(reservation.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg text-xs font-bold hover:bg-red-500 hover:text-white transition-colors whitespace-nowrap"
                                            title="Delete Record"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- QR Modal Overlay -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="selectedQrPass !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="selectedQrPass = null">
                        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                        <div class="relative bg-gradient-to-b from-white to-gray-50 border border-gray-200 rounded-3xl p-8 w-full max-w-sm shadow-2xl z-10 flex flex-col items-center text-center overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent opacity-70"></div>
                            
                            <h3 class="text-xl font-black text-gray-900 mb-1 uppercase tracking-widest">QR Pass</h3>
                            <p class="text-xs text-blue-600/70 mb-6">Present this code to the property custodian</p>
                            
                            <div class="bg-white p-4 rounded-2xl mb-6 shadow-[0_0_20px_rgba(245,196,0,0.15)] border-4 border-blue-200">
                                <img :src="`/qr/${selectedQrPass}`" alt="QR Code" class="w-48 h-48 object-contain mix-blend-multiply">
                            </div>
                            
                            <div class="w-full flex flex-col gap-3">
                                <a :href="`/qr/${selectedQrPass}`" download="NCST_QR_Pass.png" class="w-full flex justify-center items-center py-3 bg-ncst-gold hover:bg-blue-800 text-white font-bold rounded-xl transition-all shadow-md">
                                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Save Screenshot
                                </a>
                                <button @click="selectedQrPass = null" class="w-full py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">
                                    Close
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
