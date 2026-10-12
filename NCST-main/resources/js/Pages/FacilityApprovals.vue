<script setup>
import { ref, computed } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';
import SignaturePad from '@/Components/SignaturePad.vue';

const props = defineProps({
    reservations: { type: Array, default: () => [] }
});

const alert = useAlert();
const page = usePage();
const user = computed(() => page.props.auth.user);

const isRejectModalOpen = ref(false);
const rejectRemarks = ref('');
const activeTransaction = ref(null);

const isDetailsModalOpen = ref(false);
const isSignatureModalOpen = ref(false);
const activeSignatureRole = ref('');
const activeFormDetails = ref(null);

const openDetailsModal = (res) => {
    activeTransaction.value = res;
    if (res.remarks) {
        try {
            activeFormDetails.value = JSON.parse(res.remarks);
        } catch (e) {
            activeFormDetails.value = { error: "Failed to parse form details", raw: res.remarks };
        }
    } else {
        activeFormDetails.value = {};
    }
    isDetailsModalOpen.value = true;
};

const openRejectModal = (res) => {
    activeTransaction.value = res;
    rejectRemarks.value = '';
    isRejectModalOpen.value = true;
};

const submitReject = () => {
    if (!rejectRemarks.value) {
        alert.error('Error', 'Please provide a reason for rejecting.');
        return;
    }
    if (!activeTransaction.value) return;

    router.post(`/transaction/${activeTransaction.value.id}/decline`, {
        remarks: rejectRemarks.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Declined', 'Facility reservation declined.');
            isRejectModalOpen.value = false;
        }
    });
};

const signDocument = (role) => {
    activeSignatureRole.value = role;
    isSignatureModalOpen.value = true;
};

const hasCurrentUserSigned = computed(() => {
    if (!activeFormDetails.value?.signatures || !user.value) return false;
    if (user.value.role === 'admin') return false;
    const dept = user.value.department?.toLowerCase();
    const role = user.value.role?.toLowerCase();
    return !!(activeFormDetails.value.signatures[dept] || activeFormDetails.value.signatures[role]);
});

const hasSigned = (res) => {
    if (!res.remarks || !user.value) return false;
    try {
        const details = JSON.parse(res.remarks);
        if (!details.signatures) return false;
        const dept = user.value.department?.toLowerCase();
        const role = user.value.role?.toLowerCase();
        return !!(details.signatures[dept] || details.signatures[role]);
    } catch (e) {
        return false;
    }
};

const submitSignature = (signatureDataUri) => {
    if (!activeTransaction.value) return;
    const role = activeSignatureRole.value;
    const printedName = user.value?.name || '';
    
    router.post(`/transaction/${activeTransaction.value.id}/sign`, { role, signature: signatureDataUri, printed_name: printedName }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Signed', `Digitally signed as ${role.toUpperCase()}`);
            isSignatureModalOpen.value = false;
            if (activeFormDetails.value && activeFormDetails.value.signatures) {
                activeFormDetails.value.signatures[role] = { data: signatureDataUri, name: printedName };
            } else if (activeFormDetails.value) {
                activeFormDetails.value.signatures = { [role]: { data: signatureDataUri, name: printedName } };
            }
        }
    });
};

const approveTransaction = (id) => {
    router.post(`/transaction/${id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Approved', 'Facility reservation approved successfully.');
        }
    });
};
</script>

<template>
    <Head title="Facility Approvals" />

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <!-- Header -->
        <div>
                <h1 class="text-3xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                    {{ $page.props.auth.user.department || 'FMO' }} Approvals
                    <span class="px-3 py-1 rounded-full bg-ncst-gold/20 text-blue-600 text-sm font-bold border border-gray-200">
                        {{ props.reservations.length }} Pending
                    </span>
                </h1>
                <p class="text-gray-600 mt-1">Review and approve facility reservation requests.</p>
            </div>

            <!-- List -->
            <div v-if="props.reservations.length === 0" class="bg-gray-100 border border-gray-300 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">All Caught Up</h3>
                <p class="text-gray-600 text-sm">There are no pending facility reservations at the moment.</p>
            </div>

            <div v-else class="grid gap-4">
                <div v-for="res in props.reservations" :key="res.id" class="bg-gray-100 border border-gray-300 rounded-2xl p-6 flex flex-col md:flex-row gap-6 md:items-center justify-between group hover:bg-white/10 transition-colors">
                    <div class="flex-1 space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ res.resource?.name }}</h3>
                                <div class="text-sm text-blue-600 font-medium mt-1">{{ res.purpose }}</div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase bg-yellow-500/10 text-yellow-500 border border-yellow-500/20">
                                Pending Approval
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-black/20 rounded-lg p-3 border border-gray-300">
                                <div class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Requested By</div>
                                <div class="font-medium text-gray-900">{{ res.user?.name }}</div>
                                <div class="text-xs text-gray-600 mt-0.5">{{ res.user?.department || res.user?.role }}</div>
                            </div>
                            <div class="bg-black/20 rounded-lg p-3 border border-gray-300">
                                <div class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Date & Time</div>
                                <div class="font-medium text-gray-900">{{ res.duration }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 shrink-0">
                        <button @click="openDetailsModal(res)" class="w-full md:w-32 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 px-4 py-2.5 rounded-xl font-bold text-sm transition-all active:scale-95 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            View Form
                        </button>
                        <div v-if="!hasSigned(res)" class="flex gap-2">
                            <button @click="openRejectModal(res)" class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-500 hover:text-red-400 border border-red-500/20 px-4 py-2.5 rounded-xl font-bold text-sm transition-all active:scale-95 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                Decline
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="isRejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="isRejectModalOpen = false"></div>
            <div class="relative bg-white shadow-sm border border-gray-300 rounded-2xl w-full max-w-md border border-gray-300 shadow-2xl p-6">
                <h3 class="text-xl font-black text-gray-900 mb-2">Decline Reservation</h3>
                <p class="text-sm text-gray-500 mb-6">Please provide a reason for declining this facility reservation request.</p>
                <div class="space-y-4">
                    <textarea v-model="rejectRemarks" rows="4" class="w-full bg-black/30 border border-gray-300 rounded-xl text-gray-900 p-3 text-sm focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold transition-colors resize-none placeholder:text-gray-400" placeholder="Enter reason for declining..."></textarea>
                    <div class="flex gap-3 pt-2">
                        <button @click="isRejectModalOpen = false" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="submitReject" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg active:scale-95">
                            Confirm Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Details Modal -->
        <div v-if="isDetailsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="isDetailsModalOpen = false"></div>
            <div class="relative bg-white shadow-sm border border-gray-300 rounded-2xl w-full max-w-3xl border border-gray-300 shadow-2xl p-6 max-h-[90vh] overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                <div class="flex justify-between items-center mb-6 border-b border-gray-300 pb-4">
                    <h3 class="text-xl font-black text-gray-900">Facility Reservation Form Details</h3>
                    <button @click="isDetailsModalOpen = false" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div v-if="activeFormDetails && activeFormDetails.first_name" class="space-y-6">
                    <!-- Personal Info -->
                    <div>
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Requester Information</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm bg-black/20 p-4 rounded-xl border border-gray-300">
                            <div><span class="text-gray-500 block text-xs">Name</span><span class="text-gray-900 font-medium">{{ activeFormDetails.first_name }} {{ activeFormDetails.middle_name }} {{ activeFormDetails.surname }}</span></div>
                            <div><span class="text-gray-500 block text-xs">Department</span><span class="text-gray-900 font-medium">{{ activeFormDetails.department || 'N/A' }}</span></div>
                            <div><span class="text-gray-500 block text-xs">Contact</span><span class="text-gray-900 font-medium">{{ activeFormDetails.contact_no || 'N/A' }}</span></div>
                            <div class="col-span-2"><span class="text-gray-500 block text-xs">Address</span><span class="text-gray-900 font-medium">{{ activeFormDetails.address || 'N/A' }}</span></div>
                        </div>
                    </div>

                    <!-- Event Info -->
                    <div>
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Event Details</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm bg-black/20 p-4 rounded-xl border border-gray-300">
                            <div class="col-span-2 md:col-span-4"><span class="text-gray-500 block text-xs">Activity/Event Name</span><span class="text-gray-900 font-medium">{{ activeFormDetails.name_of_activity }}</span></div>
                            <div><span class="text-gray-500 block text-xs">Date</span><span class="text-gray-900 font-medium">{{ activeFormDetails.date_of_event }}</span></div>
                            <div><span class="text-gray-500 block text-xs">Hours</span><span class="text-gray-900 font-medium">{{ activeFormDetails.no_of_hours || 'N/A' }}</span></div>
                            <div><span class="text-gray-500 block text-xs">Start Time</span><span class="text-gray-900 font-medium">{{ activeFormDetails.starting_time }}</span></div>
                            <div><span class="text-gray-500 block text-xs">End Time</span><span class="text-gray-900 font-medium">{{ activeFormDetails.time_ended }}</span></div>
                            <div class="col-span-2 md:col-span-4 mt-2">
                                <span class="text-gray-500 block text-xs">Other Places Mentioned</span>
                                <span class="text-gray-900 font-medium">{{ activeFormDetails.other_place_of_event || 'None' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Equipment -->
                    <div>
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Setup / Equipment Needed</h4>
                        <div class="bg-black/20 p-4 rounded-xl border border-gray-300">
                            <ul v-if="activeFormDetails.equipments && activeFormDetails.equipments.length > 0" class="list-disc list-inside text-sm text-gray-900 space-y-1">
                                <li v-for="eq in activeFormDetails.equipments" :key="eq.name">
                                    {{ eq.name }} (Qty: {{ eq.quantity }})
                                </li>
                            </ul>
                            <p v-else class="text-sm text-gray-500 italic">No specific equipment selected.</p>
                            
                            <div class="mt-3 pt-3 border-t border-gray-300" v-if="activeFormDetails.other_equipments">
                                <span class="text-gray-500 block text-xs">Other Equipment</span>
                                <span class="text-gray-900 text-sm">{{ activeFormDetails.other_equipments }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Charges -->
                    <div v-if="activeFormDetails.with_charge || activeFormDetails.without_charge">
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">Billing & Charges</h4>
                        <div class="bg-black/20 p-4 rounded-xl border border-gray-300 text-sm">
                            <div v-if="activeFormDetails.with_charge" class="mb-4">
                                <span class="text-red-400 font-bold block mb-1">WITH CHARGE</span>
                                <ul class="list-disc list-inside text-gray-700 space-y-1 ml-2">
                                    <li>Hours: {{ activeFormDetails.with_charge_hours || '0' }}</li>
                                    <li v-if="activeFormDetails.with_charge_lighting">Lighting</li>
                                    <li v-if="activeFormDetails.with_charge_electricity">Electricity</li>
                                </ul>
                            </div>
                            <div v-if="activeFormDetails.without_charge" class="mb-4">
                                <span class="text-green-400 font-bold block mb-1">WITHOUT CHARGE</span>
                                <ul class="list-disc list-inside text-gray-700 space-y-1 ml-2">
                                    <li>Hours: {{ activeFormDetails.without_charge_hours || '0' }}</li>
                                    <li v-if="activeFormDetails.without_charge_lighting">Lighting</li>
                                    <li v-if="activeFormDetails.without_charge_electricity">Electricity</li>
                                </ul>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-300 flex justify-between items-center" v-if="activeFormDetails.total_amount">
                                <div>
                                    <span class="text-gray-500 block text-xs">Amount in Words</span>
                                    <span class="text-gray-900 italic">{{ activeFormDetails.total_amount_words || 'N/A' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-gray-500 block text-xs">Total Amount</span>
                                    <span class="text-blue-600 font-bold text-lg border-b-2 border-ncst-gold">₱ {{ activeFormDetails.total_amount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SIGNATURES -->
                    <div>
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-6">Signatures</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-12 gap-x-8 mb-6 bg-black/20 p-6 rounded-xl border border-gray-300">
                            
                            <!-- FMO Signature -->
                            <div class="flex flex-col">
                                <p class="text-xs font-bold text-gray-600 uppercase mb-16">Approved By:</p>
                                <div class="w-full relative text-center">
                                    <div class="absolute bottom-full left-0 right-0 flex justify-center mb-1">
                                        <button v-if="user?.department === 'FMO' || user?.school_id === 'FMO-Admin' || user?.role === 'admin'" 
                                                v-show="!activeFormDetails?.signatures?.fmo" 
                                                type="button" 
                                                @click.prevent="activeFormDetails?.signatures?.osa || user?.role === 'admin' ? signDocument('fmo') : null" 
                                                :disabled="!activeFormDetails?.signatures?.osa && user?.role !== 'admin'"
                                                :class="(activeFormDetails?.signatures?.osa || user?.role === 'admin') ? 'bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 border-blue-500/50 animate-pulse cursor-pointer' : 'bg-gray-500/20 text-gray-400 border-gray-3000/50 cursor-not-allowed opacity-50'"
                                                class="border text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            {{ activeFormDetails?.signatures?.osa || user?.role === 'admin' ? 'Sign as FMO' : 'Waiting for OSA' }}
                                        </button>
                                        <div v-if="activeFormDetails?.signatures?.fmo" class="h-16 flex flex-col items-center justify-center bg-white/10 rounded px-2 overflow-hidden">
                                            <img v-if="typeof activeFormDetails.signatures.fmo === 'string'" :src="activeFormDetails.signatures.fmo" class="h-full object-contain filter invert brightness-0" alt="Signature" />
                                            <template v-else>
                                                <img :src="activeFormDetails.signatures.fmo.data" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                <span class="text-[9px] font-bold text-gray-900 uppercase truncate w-full px-1">{{ activeFormDetails.signatures.fmo.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-300 pt-1">
                                        <p class="text-gray-900 font-bold text-sm">Facilities Management Office</p>
                                        <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                    </div>
                                    <div v-if="activeFormDetails?.signatures?.fmo" class="mt-3 flex justify-center">
                                        <div class="bg-green-500/20 text-green-400 border border-green-500/50 text-xs px-3 py-1 rounded-full font-bold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            ✔ Signed & Verified
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OCM Signature -->
                            <div class="flex flex-col">
                                <p class="text-xs font-bold text-gray-600 uppercase mb-16">Noted By:</p>
                                <div class="w-full relative text-center">
                                    <div class="absolute bottom-full left-0 right-0 flex justify-center mb-1">
                                        <button v-if="user?.role === 'ocm' || user?.department === 'OCM' || user?.role === 'admin'" 
                                                v-show="!activeFormDetails?.signatures?.ocm" 
                                                type="button" 
                                                @click.prevent="activeFormDetails?.signatures?.fmo || user?.role === 'admin' ? signDocument('ocm') : null" 
                                                :disabled="!activeFormDetails?.signatures?.fmo && user?.role !== 'admin'"
                                                :class="(activeFormDetails?.signatures?.fmo || user?.role === 'admin') ? 'bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 border-blue-500/50 animate-pulse cursor-pointer' : 'bg-gray-500/20 text-gray-400 border-gray-3000/50 cursor-not-allowed opacity-50'"
                                                class="border text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            {{ activeFormDetails?.signatures?.fmo || user?.role === 'admin' ? 'Sign as OCM' : 'Waiting for FMO' }}
                                        </button>
                                        <div v-if="activeFormDetails?.signatures?.ocm" class="h-16 flex flex-col items-center justify-center bg-white/10 rounded px-2 overflow-hidden">
                                            <img v-if="typeof activeFormDetails.signatures.ocm === 'string'" :src="activeFormDetails.signatures.ocm" class="h-full object-contain filter invert brightness-0" alt="Signature" />
                                            <template v-else>
                                                <img :src="activeFormDetails.signatures.ocm.data" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                <span class="text-[9px] font-bold text-gray-900 uppercase truncate w-full px-1">{{ activeFormDetails.signatures.ocm.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-300 pt-1">
                                        <p class="text-gray-900 font-bold text-sm">Office of the Campus Manager</p>
                                        <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                    </div>
                                    <div v-if="activeFormDetails?.signatures?.ocm" class="mt-3 flex justify-center">
                                        <div class="bg-green-500/20 text-green-400 border border-green-500/50 text-xs px-3 py-1 rounded-full font-bold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            ✔ Signed & Verified
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OSA Signature -->
                            <div class="flex flex-col">
                                <p class="text-xs font-bold text-gray-600 uppercase mb-16">Sender's Signature:</p>
                                <div class="w-full relative text-center">
                                    <div class="absolute bottom-full left-0 right-0 flex justify-center mb-1">
                                        <button v-if="user?.role === 'osa' || user?.department === 'OSA' || user?.role === 'admin'" 
                                                v-show="!activeFormDetails?.signatures?.osa" 
                                                type="button" 
                                                @click.prevent="signDocument('osa')" 
                                                class="bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 border border-blue-500/50 text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1 animate-pulse">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            Sign as OSA
                                        </button>
                                        <div v-if="activeFormDetails?.signatures?.osa" class="h-16 flex flex-col items-center justify-center bg-white/10 rounded px-2 overflow-hidden">
                                            <img v-if="typeof activeFormDetails.signatures.osa === 'string'" :src="activeFormDetails.signatures.osa" class="h-full object-contain filter invert brightness-0" alt="Signature" />
                                            <template v-else>
                                                <img :src="activeFormDetails.signatures.osa.data" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                <span class="text-[9px] font-bold text-gray-900 uppercase truncate w-full px-1">{{ activeFormDetails.signatures.osa.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-300 pt-1">
                                        <p class="text-gray-900 font-bold text-sm">Office of the Student Affairs</p>
                                        <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                    </div>
                                    <div v-if="activeFormDetails?.signatures?.osa" class="mt-3 flex justify-center">
                                        <div class="bg-green-500/20 text-green-400 border border-green-500/50 text-xs px-3 py-1 rounded-full font-bold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            ✔ Signed & Verified
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OSS Signature -->
                            <div class="flex flex-col">
                                <p class="text-xs font-bold text-gray-600 uppercase mb-16">Received By:</p>
                                <div class="w-full relative text-center">
                                    <div class="absolute bottom-full left-0 right-0 flex justify-center mb-1">
                                        <button v-if="user?.role === 'oss' || user?.department === 'OSS' || user?.role === 'admin'" 
                                                v-show="!activeFormDetails?.signatures?.oss" 
                                                type="button" 
                                                @click.prevent="activeFormDetails?.signatures?.ocm || user?.role === 'admin' ? signDocument('oss') : null" 
                                                :disabled="!activeFormDetails?.signatures?.ocm && user?.role !== 'admin'"
                                                :class="(activeFormDetails?.signatures?.ocm || user?.role === 'admin') ? 'bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 border-blue-500/50 animate-pulse cursor-pointer' : 'bg-gray-500/20 text-gray-400 border-gray-3000/50 cursor-not-allowed opacity-50'"
                                                class="border text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            {{ activeFormDetails?.signatures?.ocm || user?.role === 'admin' ? 'Sign as OSS' : 'Waiting for OCM' }}
                                        </button>
                                        <div v-if="activeFormDetails?.signatures?.oss" class="h-16 flex flex-col items-center justify-center bg-white/10 rounded px-2 overflow-hidden">
                                            <img v-if="typeof activeFormDetails.signatures.oss === 'string'" :src="activeFormDetails.signatures.oss" class="h-full object-contain filter invert brightness-0" alt="Signature" />
                                            <template v-else>
                                                <img :src="activeFormDetails.signatures.oss.data" class="h-10 object-contain filter invert brightness-0" alt="Signature" />
                                                <span class="text-[9px] font-bold text-gray-900 uppercase truncate w-full px-1">{{ activeFormDetails.signatures.oss.name }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-300 pt-1">
                                        <p class="text-gray-900 font-bold text-sm">Office of the Safety and Security</p>
                                        <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                    </div>
                                    <div v-if="activeFormDetails?.signatures?.oss" class="mt-3 flex justify-center">
                                        <div class="bg-green-500/20 text-green-400 border border-green-500/50 text-xs px-3 py-1 rounded-full font-bold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            ✔ Signed & Verified
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-600 text-sm text-center py-8">
                    Detailed form data is not available for this older reservation.
                </div>
                
                <div class="mt-8 pt-4 border-t border-gray-300 flex justify-end gap-3">
                    <button @click="isDetailsModalOpen = false" class="px-4 py-2 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-50 transition-colors">
                        Close
                    </button>
                    <a v-if="activeTransaction?.status === 'ACTIVE'" :href="`/transaction/${activeTransaction.id}/print`" target="_blank" class="bg-blue-600 text-white hover:bg-blue-800 px-6 py-2 rounded-xl text-sm font-bold shadow-lg transition-all active:scale-95 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print Approved Form
                    </a>
                    <button v-else-if="hasCurrentUserSigned" disabled class="bg-green-500/20 text-green-400 px-6 py-2 rounded-xl text-sm font-bold border border-green-500/20 cursor-default flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Approved
                    </button>
                    <button v-else @click="openRejectModal(activeTransaction); isDetailsModalOpen = false" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 px-6 py-2 rounded-xl text-sm font-bold border border-red-500/20 transition-all active:scale-95">
                        Decline
                    </button>
                </div>
            </div>
        </div>
    
    <SignaturePad 
        v-if="isSignatureModalOpen" 
        :title="`Sign as ${activeSignatureRole.toUpperCase()}`" 
        @close="isSignatureModalOpen = false" 
        @update:modelValue="submitSignature" 
    />
</template>
