<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const props = defineProps({
    defaultRole: String,
    reservations: Array
});

const searchQuery = ref('');
const roleFilter = ref('all');

// Modal state
const isReturnModalOpen = ref(false);
const selectedReservation = ref(null);

const returnForm = useForm({
    condition: 'Good',
    remarks: '',
    photo: null
});

const filteredReservations = computed(() => {
    let list = props.reservations || [];
    
    if (roleFilter.value === 'student') {
        list = list.filter(res => res.user?.role === 'student');
    } else if (roleFilter.value === 'faculty') {
        list = list.filter(res => res.user?.role === 'faculty' || res.user?.role === 'staff');
    }
    
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(res => 
            (res.user?.name || '').toLowerCase().includes(q) ||
            (res.user?.school_id || '').toLowerCase().includes(q) ||
            (res.resource?.name || '').toLowerCase().includes(q) ||
            (res.resource?.asset_code || '').toLowerCase().includes(q)
        );
    }
    
    return list;
});

const openReturnModal = (reservation) => {
    selectedReservation.value = reservation;
    returnForm.reset();
    returnForm.condition = 'Good';
    isReturnModalOpen.value = true;
};

const closeReturnModal = () => {
    isReturnModalOpen.value = false;
    selectedReservation.value = null;
    returnForm.reset();
};

const handlePhotoUpload = (e) => {
    if (e.target.files && e.target.files[0]) {
        returnForm.photo = e.target.files[0];
    }
};

const submitReturn = () => {
    returnForm.post(`/transaction/${selectedReservation.value.id}/return`, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Item Returned', 'The item has been successfully returned.');
            closeReturnModal();
        },
        onError: () => {
            alert.error('Error', 'An error occurred while returning the item.');
        }
    });
};
</script>

<template>
    <Head :title="defaultRole === 'combined' ? 'Walk-in Returns' : `Walk-in Return - ${defaultRole === 'student' ? 'Student' : 'Faculty/Staff'}`" />

    <div class="h-[calc(100vh-2rem)] flex flex-col space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center bg-white shadow-sm border border-gray-300 p-6 rounded-2xl shadow-xl border border-gray-200 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-ncst-gold/20 flex items-center justify-center border border-gray-200">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 capitalize">{{ defaultRole === 'combined' ? 'Return Items' : `${defaultRole} Returns` }}</h2>
                    <p class="text-xs text-gray-600">Process returning items for walk-in {{ defaultRole === 'combined' ? 'borrowers' : `${defaultRole}s` }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 h-full flex flex-col">
            <!-- Search & Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <!-- Filter Tabs -->
                <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-200">
                    <button 
                        @click="roleFilter = 'all'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="roleFilter === 'all' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
                    >
                        All Returns
                    </button>
                    <button 
                        @click="roleFilter = 'student'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="roleFilter === 'student' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
                    >
                        Student
                    </button>
                    <button 
                        @click="roleFilter = 'faculty'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="roleFilter === 'faculty' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
                    >
                        Faculty / Staff
                    </button>
                </div>

                <div class="relative w-full sm:w-96">
                    <input 
                        type="text" 
                        v-model="searchQuery"
                        class="w-full bg-white border border-gray-300 rounded-xl pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold transition-colors"
                        placeholder="Search user, ID, item, or asset code..."
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden flex-1 flex flex-col">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-100 text-gray-900/90 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-4">Borrower</th>
                                <th class="px-6 py-4">Item</th>
                                <th class="px-6 py-4">Borrowed At</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="res in filteredReservations" :key="res.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-ncst-gold/20 flex items-center justify-center text-blue-600 font-bold">
                                            {{ res.user?.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900">{{ res.user?.name }}</p>
                                            <p class="text-xs text-gray-600">{{ res.user?.school_id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900">{{ res.resource?.name }}</p>
                                    <p class="text-xs text-gray-600 font-mono">Q: {{ res.quantity }} &middot; {{ res.resource?.asset_code }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>{{ new Date(res.updated_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-500/20 text-blue-400">
                                        {{ res.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button 
                                        @click="openReturnModal(res)"
                                        class="px-4 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-800 transition-colors inline-flex items-center gap-2"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z" /></svg>
                                        Return Item
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredReservations.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 mb-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                        No active borrowed items found.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Return Item Modal -->
        <div v-if="isReturnModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeReturnModal"></div>
            <div class="relative bg-gray-50 border border-gray-200 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="p-6 border-b border-gray-300 flex items-center justify-between shrink-0 bg-gray-100">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-ncst-gold/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        Process Return
                    </h3>
                    <button @click="closeReturnModal" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <div class="mb-6 bg-gray-100 p-4 rounded-xl border border-gray-300">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-ncst-gold/10 flex items-center justify-center border border-gray-200 shrink-0">
                                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 text-lg">{{ selectedReservation?.resource?.name }}</h4>
                                <div class="text-sm text-gray-500 space-y-1 mt-1">
                                    <p>Asset Code: <span class="font-mono text-blue-600">{{ selectedReservation?.resource?.asset_code }}</span></p>
                                    <p>Quantity Borrowed: <span class="text-gray-900 font-medium">{{ selectedReservation?.quantity }}</span></p>
                                    <p>Borrower: <span class="text-gray-900">{{ selectedReservation?.user?.name }} ({{ selectedReservation?.user?.school_id }})</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Condition Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Item Condition</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="returnForm.condition" value="Good" class="peer sr-only" name="condition" />
                                <div class="p-4 rounded-xl border border-gray-300 bg-gray-100 hover:bg-white/10 peer-checked:border-ncst-gold peer-checked:bg-ncst-gold/10 transition-all text-center">
                                    <div class="w-8 h-8 mx-auto mb-2 rounded-full bg-green-500/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" /></svg>
                                    </div>
                                    <div class="font-bold text-gray-900">Good Condition</div>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="returnForm.condition" value="Damaged" class="peer sr-only" name="condition" />
                                <div class="p-4 rounded-xl border border-gray-300 bg-gray-100 hover:bg-white/10 peer-checked:border-red-500 peer-checked:bg-red-500/10 transition-all text-center">
                                    <div class="w-8 h-8 mx-auto mb-2 rounded-full bg-red-500/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    </div>
                                    <div class="font-bold text-gray-900">Damaged / Bad</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Damaged Item Fields -->
                    <div v-if="returnForm.condition === 'Damaged'" class="space-y-4 animate-fade-in">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Remarks / Description <span class="text-red-400">*</span></label>
                            <textarea 
                                v-model="returnForm.remarks" 
                                rows="3" 
                                class="w-full bg-black/40 border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-white/30 focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors"
                                placeholder="Describe the damage or missing parts..."
                                required
                            ></textarea>
                            <p class="text-[10px] text-gray-500 mt-1.5">Please provide specific details about the condition.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Photo Evidence</label>
                            <div class="relative group cursor-pointer">
                                <input type="file" @change="handlePhotoUpload" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div class="w-full border-2 border-dashed border-gray-300 rounded-xl p-6 text-center group-hover:border-blue-200 transition-colors bg-black/20">
                                    <div v-if="returnForm.photo" class="flex flex-col items-center">
                                        <svg class="w-8 h-8 text-green-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <p class="text-sm font-medium text-gray-900 truncate max-w-xs">{{ returnForm.photo.name }}</p>
                                        <p class="text-xs text-gray-600 mt-1">Click or drag to replace</p>
                                    </div>
                                    <div v-else class="flex flex-col items-center">
                                        <svg class="w-8 h-8 text-gray-400 mb-2 group-hover:text-blue-600/50 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <p class="text-sm font-medium text-gray-600">Click or drag image to upload</p>
                                        <p class="text-[10px] text-gray-500 mt-1">Max size: 5MB</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-6 border-t border-gray-300 shrink-0 bg-gray-100 flex items-center justify-end gap-3">
                    <button type="button" @click="closeReturnModal" class="px-5 py-2.5 rounded-lg text-sm font-bold text-gray-600 hover:text-gray-900 hover:bg-white/10 transition-colors">
                        Cancel
                    </button>
                    <button 
                        @click="submitReturn" 
                        :disabled="returnForm.processing || (returnForm.condition === 'Damaged' && !returnForm.remarks)"
                        class="px-6 py-2.5 rounded-lg text-sm font-bold bg-blue-600 text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ returnForm.processing ? 'Processing...' : 'Confirm Return' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
