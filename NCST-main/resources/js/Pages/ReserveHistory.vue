<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAlert } from '@/utils/alert';

const page = usePage();
const reservations = computed(() => page.props.reservations || []);
const pageTitle = computed(() => page.props.pageTitle || 'Facility Reserve History');
const pageDescription = computed(() => page.props.pageDescription || 'Review your facility reservation history and full details.');
const alert = useAlert();

const deleteReservation = (id) => {
    if (confirm('Are you sure you want to delete this reservation?')) {
        router.delete(`/transaction/${id}`, {
            preserveScroll: true,
            onSuccess: () => alert.success('Deleted', 'Reservation deleted successfully.')
        });
    }
};

const isEditModalOpen = ref(false);
const editingReservation = ref(null);
const editForm = ref({ purpose: '' });

const openEditModal = (res) => {
    editingReservation.value = res;
    editForm.value.purpose = res.purpose;
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    router.put(`/transaction/${editingReservation.value.id}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Updated', 'Reservation purpose updated.');
            isEditModalOpen.value = false;
        }
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
    });
};

const selectedReservation = ref(null);

const parseRemarks = (remarksStr) => {
    try {
        if (!remarksStr) return {};
        const parsed = JSON.parse(remarksStr);
        return typeof parsed === 'object' ? parsed : { message: remarksStr };
    } catch(e) {
        return { message: remarksStr };
    }
};

const getEquipmentsList = (remarksObj) => {
    const list = [];
    if (remarksObj.equipments_needed && Array.isArray(remarksObj.equipments_needed)) {
        list.push(...remarksObj.equipments_needed);
    }
    if (remarksObj.other_equipments) {
        list.push(remarksObj.other_equipments);
    }
    return list;
};
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p class="text-gray-700 font-bold text-xl">No History Found</p>
                <p class="text-gray-500 mt-2 max-w-md mx-auto">You have not reserved any facilities yet.</p>
            </div>

            <!-- History List -->
            <div v-else class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="text-xs uppercase bg-gray-100 text-gray-600 border-b border-gray-300">
                            <tr>
                                <th class="px-6 py-4 font-semibold tracking-wider">Facility</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Date Requested</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Purpose</th>
                                <th class="px-6 py-4 font-semibold tracking-wider">Status</th>
                                <th class="px-6 py-4 font-semibold tracking-wider text-center">Signatures</th>
                                <th class="px-6 py-4 font-semibold tracking-wider text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="reservation in reservations" :key="reservation.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ reservation.resource?.name || 'Unknown Facility' }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-700 whitespace-nowrap">
                                    {{ formatDate(reservation.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ reservation.purpose || 'N/A' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border whitespace-nowrap"
                                        :class="{
                                            'bg-yellow-500/10 text-yellow-400 border-yellow-500/20': reservation.status === 'PENDING_APPROVAL',
                                            'bg-blue-500/10 text-blue-400 border-blue-500/20': reservation.status === 'APPROVED_AWAITING_PICKUP',
                                            'bg-purple-500/10 text-purple-400 border-purple-500/20': reservation.status === 'BORROWED',
                                            'bg-green-500/10 text-green-400 border-green-500/20': reservation.status === 'RETURNED' || reservation.status === 'DISPENSED' || reservation.status === 'ACTIVE',
                                            'bg-red-500/10 text-red-400 border-red-500/20': reservation.status === 'REJECTED' || reservation.status === 'DECLINED'
                                        }"
                                    >
                                        {{ reservation.status === 'ACTIVE' ? 'APPROVED' : reservation.status.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-1.5" v-if="parseRemarks(reservation.remarks)?.signatures">
                                        <span v-for="role in ['osa', 'fmo', 'ocm', 'oss']" :key="role" 
                                            class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border"
                                            :class="parseRemarks(reservation.remarks).signatures[role] ? 'bg-green-500/20 border-green-500/30 text-green-400' : 'bg-gray-100 border-gray-300 text-gray-400'"
                                            :title="parseRemarks(reservation.remarks).signatures[role] ? 'Signed by ' + role.toUpperCase() : 'Pending ' + role.toUpperCase()"
                                        >
                                            {{ role }}
                                        </span>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 text-center italic">N/A</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a 
                                            v-if="reservation.status === 'ACTIVE'"
                                            :href="`/transaction/${reservation.id}/print`"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-ncst-gold/20 text-blue-600 border border-gray-200 rounded-lg text-xs font-bold hover:bg-ncst-gold/30 transition-colors whitespace-nowrap"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                            Print
                                        </a>
                                        <button 
                                            @click="selectedReservation = reservation"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-900 border border-gray-300 rounded-lg text-xs font-bold hover:bg-white/10 transition-colors whitespace-nowrap"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View
                                        </button>
                                        <button v-if="['PENDING_APPROVAL', 'DECLINED', 'REJECTED'].includes(reservation.status)"
                                            @click="openEditModal(reservation)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-lg text-xs font-bold hover:bg-blue-500/20 transition-colors whitespace-nowrap"
                                            title="Edit Purpose"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Edit
                                        </button>
                                        <button v-if="['PENDING_APPROVAL', 'DECLINED', 'REJECTED'].includes(reservation.status)"
                                            @click="deleteReservation(reservation.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500/10 text-red-500 border border-red-500/20 rounded-lg text-xs font-bold hover:bg-red-500/20 transition-colors whitespace-nowrap"
                                            title="Delete Reservation"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Details Modal Overlay -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="selectedReservation !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="selectedReservation = null">
                        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                        <div class="relative bg-gradient-to-b from-white to-gray-50 border border-gray-200 rounded-3xl p-8 w-full max-w-3xl shadow-2xl z-10 flex flex-col text-left overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent opacity-70"></div>
                            
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-widest">Reservation Details</h3>
                                    <p class="text-xs text-blue-600/70 mt-1">Full information of your requested facility</p>
                                </div>
                                <button @click="selectedReservation = null" class="text-gray-600 hover:text-gray-900 transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div v-if="parseRemarks(selectedReservation.remarks) && parseRemarks(selectedReservation.remarks).first_name" class="space-y-6 bg-gray-100 p-5 rounded-2xl border border-gray-300 mb-6 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                
                                <!-- Personal Info -->
                                <div>
                                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Requester Information</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm bg-black/20 p-4 rounded-xl border border-gray-300">
                                        <div><span class="text-gray-500 block text-xs">Name</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).first_name }} {{ parseRemarks(selectedReservation.remarks).middle_name }} {{ parseRemarks(selectedReservation.remarks).surname }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">Department</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).department || 'N/A' }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">Contact</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).contact_no || 'N/A' }}</span></div>
                                        <div class="col-span-2"><span class="text-gray-500 block text-xs">Address</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).address || 'N/A' }}</span></div>
                                    </div>
                                </div>

                                <!-- Event Info -->
                                <div>
                                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Event Details</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm bg-black/20 p-4 rounded-xl border border-gray-300">
                                        <div class="col-span-2 md:col-span-4"><span class="text-gray-500 block text-xs">Activity/Event Name</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).name_of_activity }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">Date</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).date_of_event }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">Hours</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).no_of_hours || 'N/A' }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">Start Time</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).starting_time }}</span></div>
                                        <div><span class="text-gray-500 block text-xs">End Time</span><span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).time_ended }}</span></div>
                                        <div class="col-span-2 md:col-span-4 mt-2">
                                            <span class="text-gray-500 block text-xs">Other Places Mentioned</span>
                                            <span class="text-gray-900 font-medium">{{ parseRemarks(selectedReservation.remarks).other_place_of_event || 'None' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Equipment -->
                                <div>
                                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Setup / Equipment Needed</h4>
                                    <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                                        <ul v-if="parseRemarks(selectedReservation.remarks).equipments && parseRemarks(selectedReservation.remarks).equipments.length > 0" class="list-disc list-inside text-sm text-gray-900 space-y-1">
                                            <li v-for="eq in parseRemarks(selectedReservation.remarks).equipments" :key="eq.name">
                                                {{ eq.name }} (Qty: {{ eq.quantity }})
                                            </li>
                                        </ul>
                                        <p v-else class="text-sm text-gray-500 italic">No specific equipment selected.</p>
                                        
                                        <div class="mt-3 pt-3 border-t border-gray-300" v-if="parseRemarks(selectedReservation.remarks).other_equipments">
                                            <span class="text-gray-500 block text-xs">Other Equipment</span>
                                            <span class="text-gray-900 text-sm">{{ parseRemarks(selectedReservation.remarks).other_equipments }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Charges -->
                                <div v-if="parseRemarks(selectedReservation.remarks).with_charge || parseRemarks(selectedReservation.remarks).without_charge">
                                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Billing & Charges</h4>
                                    <div class="bg-black/20 p-4 rounded-xl border border-gray-300 text-sm">
                                        <div v-if="parseRemarks(selectedReservation.remarks).with_charge" class="mb-4">
                                            <span class="text-red-400 font-bold block mb-1">WITH CHARGE</span>
                                            <ul class="list-disc list-inside text-gray-700 space-y-1 ml-2">
                                                <li>Hours: {{ parseRemarks(selectedReservation.remarks).with_charge_hours || '0' }}</li>
                                                <li v-if="parseRemarks(selectedReservation.remarks).with_charge_lighting">Lighting</li>
                                                <li v-if="parseRemarks(selectedReservation.remarks).with_charge_electricity">Electricity</li>
                                            </ul>
                                        </div>
                                        <div v-if="parseRemarks(selectedReservation.remarks).without_charge" class="mb-4">
                                            <span class="text-green-400 font-bold block mb-1">WITHOUT CHARGE</span>
                                            <ul class="list-disc list-inside text-gray-700 space-y-1 ml-2">
                                                <li>Hours: {{ parseRemarks(selectedReservation.remarks).without_charge_hours || '0' }}</li>
                                                <li v-if="parseRemarks(selectedReservation.remarks).without_charge_lighting">Lighting</li>
                                                <li v-if="parseRemarks(selectedReservation.remarks).without_charge_electricity">Electricity</li>
                                            </ul>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-gray-300 flex justify-between items-center" v-if="parseRemarks(selectedReservation.remarks).total_amount">
                                            <div>
                                                <span class="text-gray-500 block text-xs">Amount in Words</span>
                                                <span class="text-gray-900 italic">{{ parseRemarks(selectedReservation.remarks).total_amount_words || 'N/A' }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-gray-500 block text-xs">Total Amount</span>
                                                <span class="text-blue-600 font-bold text-lg border-b-2 border-ncst-gold">₱ {{ parseRemarks(selectedReservation.remarks).total_amount }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Signatures (Read-Only) -->
                                <div v-if="parseRemarks(selectedReservation.remarks)?.signatures">
                                    <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Signatures</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-8 gap-x-4 bg-black/20 p-6 rounded-xl border border-gray-300">
                                        <div v-for="role in ['osa', 'fmo', 'ocm', 'oss']" :key="role" class="flex flex-col items-center">
                                            <div class="h-12 w-full flex items-center justify-center mb-1 border-b border-gray-300">
                                                <template v-if="parseRemarks(selectedReservation.remarks).signatures[role]">
                                                    <img v-if="typeof parseRemarks(selectedReservation.remarks).signatures[role] === 'string'" :src="parseRemarks(selectedReservation.remarks).signatures[role]" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                    <template v-else>
                                                        <img :src="parseRemarks(selectedReservation.remarks).signatures[role].data" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                        <span class="text-[8px] font-bold text-gray-900 uppercase ml-1">{{ parseRemarks(selectedReservation.remarks).signatures[role].name }}</span>
                                                    </template>
                                                </template>
                                                <span v-else class="text-xs text-gray-400 italic">Pending...</span>
                                            </div>
                                            <p class="text-gray-900 text-[10px] font-bold uppercase tracking-wider mt-1">{{ role === 'osa' ? 'Student Affairs (OSA)' : (role === 'fmo' ? 'Facilities Mgt (FMO)' : (role === 'ocm' ? 'Campus Ministry (OCM)' : 'Safety & Security (OSS)')) }}</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div v-else class="space-y-4 bg-gray-100 p-5 rounded-2xl border border-gray-300 mb-6 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                <!-- Fallback for older reservations without full form data -->
                                <div>
                                    <label class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Facility Name</label>
                                    <p class="text-gray-900 text-sm font-medium">{{ selectedReservation.resource?.name || 'Unknown' }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Date Requested</label>
                                        <p class="text-gray-900 text-sm">{{ formatDate(selectedReservation.created_at) }}</p>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Status</label>
                                        <p class="text-gray-900 text-sm font-bold capitalize">{{ selectedReservation.status.replace(/_/g, ' ') }}</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Purpose / Name of Activity</label>
                                    <p class="text-gray-900 text-sm">{{ selectedReservation.purpose }}</p>
                                </div>
                            </div>
                            
                            <div class="w-full flex justify-end">
                                <button @click="selectedReservation = null" class="px-6 py-2 bg-ncst-gold hover:bg-blue-800 text-white font-bold rounded-xl transition-all shadow-md">
                                    Done
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Edit Modal Overlay -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="isEditModalOpen = false"></div>
                        <div class="relative bg-white shadow-sm border border-gray-300 border border-gray-200 rounded-3xl p-8 w-full max-w-md shadow-2xl z-10">
                            <h3 class="text-xl font-black text-gray-900 mb-2">Edit Reservation</h3>
                            <p class="text-xs text-gray-600 mb-6">Update the purpose of your reservation.</p>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-600 font-bold block mb-1">Purpose / Name of Activity</label>
                                    <input v-model="editForm.purpose" type="text" class="w-full bg-black/30 border border-gray-300 rounded-xl text-gray-900 p-3 text-sm focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold transition-colors" />
                                </div>
                                <div class="mt-4 text-[10px] text-yellow-400/80 italic">
                                    Note: To change dates, equipment, or facility choices, please delete this reservation and submit a new one.
                                </div>
                                <div class="flex gap-3 pt-4 border-t border-gray-300">
                                    <button @click="isEditModalOpen = false" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </button>
                                    <button @click="submitEdit" class="flex-1 bg-ncst-gold hover:bg-blue-800 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg active:scale-95">
                                        Save Changes
                                    </button>
                                </div>
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
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.3); }
</style>
