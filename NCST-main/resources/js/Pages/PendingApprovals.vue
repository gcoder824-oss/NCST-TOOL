<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const props = defineProps({
    reservations: {
        type: Array,
        default: () => []
    }
});

const isDeclineModalOpen = ref(false);
const declineRemarks = ref('');
const transactionToDecline = ref(null);
const selectedRequest = ref(null);

const approveRequest = (id) => {
    router.post(`/transaction/${id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => alert.success('Success', 'Request approved. It is now awaiting pickup.')
    });
};

const declineRequest = (id) => {
    transactionToDecline.value = id;
    declineRemarks.value = '';
    isDeclineModalOpen.value = true;
};

const submitDecline = () => {
    if (!declineRemarks.value.trim()) {
        alert.warning('Warning', "Please provide a reason for declining.");
        return;
    }
    
    router.post(`/transaction/${transactionToDecline.value}/decline`, {
        remarks: declineRemarks.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isDeclineModalOpen.value = false;
            transactionToDecline.value = null;
            declineRemarks.value = '';
            alert.success('Declined', 'Request declined successfully.');
        }
    });
};

const currentPage = ref(1);
const itemsPerPage = 5;

const totalPages = computed(() => Math.ceil(props.reservations.length / itemsPerPage) || 1);

const paginatedReservations = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return props.reservations.slice(start, start + itemsPerPage);
});
</script>

<template>
    <Head title="Pending Approvals" />

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Pending Online Reservations</h1>
            <p class="text-gray-500 mt-2">Review and approve requests from students and faculty.</p>
        </div>

        <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 overflow-hidden shadow-xl">
            <div class="px-6 py-5 border-b border-gray-300 flex justify-between items-center bg-gray-100">
                <h2 class="font-bold text-gray-900">Pending Requests ({{ reservations.length }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="text-xs uppercase bg-gray-100 text-gray-600">
                        <tr>
                            <th class="px-6 py-4">Student</th>
                            <th class="px-6 py-4">Equipment</th>
                            <th class="px-6 py-4">Time</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="res in paginatedReservations" :key="res.id" @click="selectedRequest = res" class="hover:bg-white/[0.05] transition-colors cursor-pointer group relative">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ res.user?.name }} <br><span class="text-xs text-blue-600">{{ res.user?.school_id }}</span></td>
                            <td class="px-6 py-4">{{ res.resource?.name }} <br><span class="text-xs opacity-50">Qty: {{ res.quantity }}</span></td>
                            <td class="px-6 py-4">{{ new Date(res.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button @click.stop="selectedRequest = res" class="px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors font-medium border border-blue-500/20 z-10 relative">View Info</button>
                                <button @click.stop="approveRequest(res.id)" class="px-3 py-1.5 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition-colors font-medium border border-green-500/20 z-10 relative">Approve</button>
                            </td>
                        </tr>
                        <tr v-if="paginatedReservations.length === 0">
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">No pending requests right now.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-300 flex items-center justify-between bg-gray-50/30">
                <span class="text-sm text-gray-600">
                    Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, reservations.length) }} of {{ reservations.length }} entries
                </span>
                <div class="flex gap-1 sm:gap-2">
                    <button 
                        @click="currentPage--" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border"
                        :class="currentPage === 1 ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed' : 'bg-gray-50 text-gray-600 border-gray-300 hover:border-ncst-gold hover:text-blue-600'"
                    >
                        Prev
                    </button>
                    
                    <!-- Page Numbers -->
                    <div class="hidden sm:flex gap-1">
                        <button 
                            v-for="page in totalPages" 
                            :key="page"
                            @click="currentPage = page"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border"
                            :class="currentPage === page ? 'bg-ncst-gold/20 text-blue-600 border-ncst-gold' : 'bg-gray-50 text-gray-600 border-gray-300 hover:border-ncst-gold hover:text-blue-600'"
                        >
                            {{ page }}
                        </button>
                    </div>
                    <div class="sm:hidden flex items-center px-2 text-sm text-gray-600">
                        {{ currentPage }} / {{ totalPages }}
                    </div>

                    <button 
                        @click="currentPage++" 
                        :disabled="currentPage === totalPages"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border"
                        :class="currentPage === totalPages ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed' : 'bg-gray-50 text-gray-600 border-gray-300 hover:border-ncst-gold hover:text-blue-600'"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isDeclineModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="isDeclineModalOpen = false"></div>
                <div class="relative bg-white shadow-sm border border-gray-300 border border-red-500/30 rounded-2xl p-6 w-full max-w-md shadow-2xl z-10">
                    <h3 class="text-xl font-bold text-gray-900 mb-2 text-red-400">Decline Request</h3>
                    <p class="text-sm text-gray-600 mb-4">Please provide a reason for declining this request. The student will be notified.</p>
                    
                    <textarea v-model="declineRemarks" rows="3" placeholder="e.g. Item is currently under maintenance..."
                        class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 mb-6"></textarea>
                    
                    <div class="flex gap-3">
                        <button @click="isDeclineModalOpen = false" class="flex-1 py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">
                            Cancel
                        </button>
                        <button @click="submitDecline" class="flex-1 py-3 bg-red-500/20 hover:bg-red-500/30 text-red-400 font-bold rounded-xl transition-all border border-red-500/30">
                            Confirm Decline
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Request Details Modal -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="selectedRequest = null"></div>
                <div class="relative bg-white shadow-sm border border-gray-300 border border-gray-200 rounded-2xl p-0 w-full max-w-lg shadow-2xl z-10 flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="p-6 bg-gray-100 border-b border-gray-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ selectedRequest.user?.name }}</h3>
                                <p class="text-blue-600 text-sm font-mono mt-1">{{ selectedRequest.user?.school_id }} - {{ selectedRequest.user?.role }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border whitespace-nowrap bg-yellow-500/10 text-yellow-400 border-yellow-500/20">
                                Pending
                            </span>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                            <h4 class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-3 border-b border-gray-300 pb-2">Equipment Details</h4>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-900 font-medium">{{ selectedRequest.resource?.name }}</span>
                                <span class="text-xs text-blue-600/70 uppercase tracking-wider">{{ selectedRequest.resource?.type }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-sm">Asset Code: {{ selectedRequest.resource?.asset_code }}</span>
                                <span class="text-gray-500 text-sm">Qty: <span class="text-gray-900 font-bold">{{ selectedRequest.quantity }}</span></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Requested Duration</p>
                                <p class="text-sm text-gray-900 font-medium">{{ selectedRequest.duration }}</p>
                            </div>
                            <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Date & Time</p>
                                <p class="text-sm text-gray-900 font-medium">{{ new Date(selectedRequest.created_at).toLocaleString() }}</p>
                            </div>
                        </div>

                        <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                            <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Purpose / Reason</p>
                            <p class="text-sm text-gray-700 italic mt-2">"{{ selectedRequest.purpose || 'No specific purpose provided.' }}"</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-gray-300 bg-black/20 flex gap-3">
                        <button @click="selectedRequest = null" class="flex-1 py-3 bg-white/10 hover:bg-white/20 text-gray-900 font-bold rounded-xl transition-all shadow-md">
                            Close
                        </button>
                        <button @click="approveRequest(selectedRequest.id); selectedRequest = null" class="flex-1 py-3 bg-green-500/20 hover:bg-green-500/30 text-green-400 font-bold rounded-xl transition-all border border-green-500/30">
                            Approve
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
