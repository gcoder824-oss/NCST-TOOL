<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';

const alert = useAlert();
const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const borrowedItems = computed(() => page.props.borrowedItems || []);
const now = ref(new Date());
let catalogTicker = null;

onMounted(() => {
    catalogTicker = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (catalogTicker) clearInterval(catalogTicker);
});

const overdueItem = computed(() => {
    return (borrowedItems.value || []).find(item => {
        if (!item.due_date || item.status !== 'BORROWED') return false;
        const diffSeconds = (new Date(item.due_date).getTime() - now.value.getTime()) / 1000;
        return diffSeconds <= 0;
    });
});

const isOverdueBlocked = computed(() => !!overdueItem.value);
const isSuspended = computed(() => !!user.value.is_suspended || isOverdueBlocked.value);
const suspensionNotice = computed(() => {
    if (!isSuspended.value) return null;
    if (overdueItem.value) {
        return `Item "${overdueItem.value.resource?.name || 'Equipment'}" is overdue. Borrowing and reservations are disabled until all overdue items are returned.`;
    }
    return page.props.suspensionNotice || "Your borrowing privileges have been disabled due to an overdue item. Please return it to restore access.";
});
const today = computed(() => new Date().toISOString().split('T')[0]);

const props = defineProps({
    resources: {
        type: Array,
        default: () => []
    },
    catalogType: {
        type: String,
        default: 'equipment'
    }
});

const searchQuery = ref('');
const activeCategory = ref('all');

// Derive categories dynamically based on the available resources (excluding facility if student)
const categories = computed(() => {
    const cats = new Set(['all']);
    props.resources.forEach(r => {
        if (user.value.role === 'student' && r.type === 'facility') return;
        cats.add(r.type);
    });
    return Array.from(cats);
});

// Sorting state
const sortKey = ref('name');
const sortOrder = ref('asc');

const filteredResources = computed(() => {
    let result = props.resources.filter(resource => {
        const matchesSearch = resource.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              resource.asset_code.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = activeCategory.value === 'all' || resource.type === activeCategory.value;
        return matchesSearch && matchesCategory;
    });

    // Sort the result
    result = result.sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];
        
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();

        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });

    return result;
});

const currentPage = ref(1);
const itemsPerPage = 8; // Adjust based on grid layout (4 columns = 2 rows)

const totalPages = computed(() => Math.ceil(filteredResources.value.length / itemsPerPage));

const paginatedResources = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredResources.value.slice(start, end);
});

watch([searchQuery, activeCategory, sortKey, sortOrder], () => {
    currentPage.value = 1;
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value++;
};

const prevPage = () => {
    if (currentPage.value > 1) currentPage.value--;
};

const goToPage = (pageNumber) => {
    currentPage.value = pageNumber;
};

const reserveForm = useForm({
    resource_id: '',
    quantity: 1,
    purpose: '',
    other_purpose: '',
    duration: 'Auto-recorded at checkout',
    processing: false
});

const processModal = ref({
    isOpen: false,
    step: 1, // 1: confirm, 2: qr
    resource: null,
    reservationId: null
});

watch(() => processModal.value.isOpen, (isOpen) => {
    if (isOpen) {
        // default purpose is selected later via UI
    }
});

const purposeOptions = computed(() => {
    if (processModal.value.resource?.type === 'facility') {
        return ['Academic Event', 'Department Meeting', 'Student Assembly', 'Seminar / Workshop', 'Other'];
    }
    return ['Class Project', 'Event', 'Personal Study', 'Club Activity', 'Other'];
});

const openProcessModal = (resource) => {
    processModal.value = {
        isOpen: true,
        step: 1,
        resource: resource,
        reservationId: null
    };
};

const closeProcessModal = () => {
    processModal.value.isOpen = false;
    setTimeout(() => {
        if (processModal.value.step === 2) {
            // refresh data implicitly if needed or they just close it
        }
        processModal.value.step = 1;
        processModal.value.resource = null;
        reserveForm.reset();
        reserveForm.clearErrors();
    }, 300);
};

const confirmReservation = () => {
    if (isSuspended.value) {
        alert.error('Borrowing Disabled', 'Your borrowing privileges are disabled due to overdue items. Please return them first.');
        processModal.value.isOpen = false;
        return;
    }
    reserveForm.resource_id = processModal.value.resource.id;
    
    let finalPurpose = reserveForm.purpose === 'Other' ? reserveForm.other_purpose : reserveForm.purpose;
    
    if (!reserveForm.quantity || !finalPurpose) {
        alert.warning('Incomplete Form', "Please complete all fields (Quantity, Purpose) before submitting.");
        return;
    }

    reserveForm.processing = true;

    router.post('/transaction/request', {
        resource_id: reserveForm.resource_id,
        quantity: reserveForm.quantity,
        purpose: finalPurpose,
        duration: reserveForm.duration
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            reserveForm.processing = false;
            processModal.value.step = 2; // Move to Success step
        },
        onError: (errors) => {
            reserveForm.processing = false;
            console.error('Reservation error:', errors);
            if (Object.keys(errors).length === 0) {
                alert.error('Error', 'An unknown network error occurred while submitting your reservation. Please try again.');
            } else {
                reserveForm.setError(errors);
            }
        },
        onFinish: () => {
            reserveForm.processing = false;
        }
    });
};
</script>

<template>
    <div class="py-10 px-4 sm:px-6 lg:px-8 space-y-10">
        <!-- Header -->
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    {{ props.catalogType === 'facilities' ? 'NCST Facilities Catalog' : 'NCST Equipment Catalog' }}
                </h1>
                <p class="text-gray-600 text-sm mt-1">
                    {{ props.catalogType === 'facilities' ? 'Browse and reserve available campus facilities.' : 'Browse and reserve available equipment.' }}
                </p>
            </div>

        </div>

        <!-- Suspension Notice -->
        <div v-if="isSuspended" class="bg-red-500/10 border-l-4 border-red-500 p-4 rounded-r-lg mb-6 shadow-md flex items-start">
            <svg class="h-6 w-6 text-red-500 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h3 class="text-red-400 font-bold text-sm uppercase tracking-wider">Borrowing Disabled</h3>
                <p class="text-red-300 text-sm mt-1">{{ suspensionNotice }}</p>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-5 flex flex-col md:flex-row justify-between items-center gap-4 shadow-lg">
            <div class="w-full md:w-1/2 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Search resources by name or code..." 
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-gray-300 text-gray-900 placeholder-white/30 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors shadow-inner"
                />
            </div>

            <!-- Controls (Sort & Category) -->
            <div class="flex flex-wrap items-center gap-4 w-full md:w-auto">
                <!-- Sort Dropdown -->
                <div class="flex items-center bg-gray-100 rounded-xl border border-gray-300 px-3 py-2">
                    <span class="text-gray-600 text-sm mr-2">Sort by:</span>
                    <select v-model="sortKey" class="bg-transparent text-gray-900 text-sm font-medium focus:outline-none focus:ring-0 border-none cursor-pointer">
                        <option value="name" class="bg-gray-50 text-gray-900">Name</option>
                        <option value="asset_code" class="bg-gray-50 text-gray-900">Asset Code</option>
                        <option value="status" class="bg-gray-50 text-gray-900">Status</option>
                    </select>
                    <button @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'" class="ml-2 text-gray-600 hover:text-gray-900 transition-colors" title="Toggle Sort Order">
                        <svg v-if="sortOrder === 'asc'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" /></svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" /></svg>
                    </button>
                </div>

                <!-- Category Dropdown -->
                <div class="flex items-center bg-gray-100 rounded-xl border border-gray-300 px-3 py-2">
                    <span class="text-gray-600 text-sm mr-2">Category:</span>
                    <select v-model="activeCategory" class="bg-transparent text-gray-900 text-sm font-medium focus:outline-none focus:ring-0 border-none cursor-pointer capitalize">
                        <option v-for="category in categories" :key="category" :value="category" class="bg-gray-50 text-gray-900 capitalize">
                            {{ category }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredResources.length === 0" class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-16 text-center shadow-2xl flex flex-col items-center justify-center">
            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-gray-700 font-bold text-xl">No resources found</p>
            <p class="text-gray-500 mt-2 max-w-md">Try adjusting your search terms or selecting a different category to find what you're looking for.</p>
            <button @click="searchQuery = ''; activeCategory = 'all'" class="mt-6 px-4 py-2 bg-gray-100 hover:bg-white/10 text-gray-900 rounded-lg transition-colors border border-gray-300">
                Clear Filters
            </button>
        </div>

        <!-- Data Table -->
        <div v-else class="space-y-6">
            <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 text-xs font-semibold uppercase tracking-wider border-b border-gray-300">
                                <th class="px-6 py-4">Asset Code</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Stock</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="resource in paginatedResources" :key="resource.id" class="hover:bg-gray-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="text-xs font-mono text-blue-600 bg-ncst-gold/10 px-2 py-1 rounded border border-gray-200">{{ resource.asset_code }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 group-hover:text-blue-600 transition-colors">
                                    {{ resource.name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 capitalize">
                                    {{ resource.type }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium" :class="resource.stock > 0 ? 'text-green-400' : 'text-red-400'">
                                    {{ resource.stock }}
                                </td>
                                <td class="px-6 py-4">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border"
                                        :class="{
                                            'bg-green-500/20 text-green-400 border-green-500/30': resource.status === 'available',
                                            'bg-red-500/20 text-red-400 border-red-500/30': resource.status === 'borrowed',
                                            'bg-yellow-500/20 text-yellow-400 border-yellow-500/30': resource.status === 'maintenance',
                                            'bg-purple-500/20 text-purple-400 border-purple-500/30': resource.status === 'reserved'
                                        }"
                                    >
                                        {{ resource.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button 
                                        @click="openProcessModal(resource)"
                                        :disabled="resource.status !== 'available' || isSuspended"
                                        class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                        :class="(resource.status === 'available' && !isSuspended)
                                            ? 'bg-blue-50 text-blue-600 hover:bg-ncst-gold hover:text-white border border-gray-200' 
                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-300'"
                                    >
                                        <svg v-if="resource.status === 'available'" class="w-3 h-3 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ isSuspended ? 'Disabled (Overdue)' : (resource.status === 'available' ? 'Reserve' : 'Unavailable') }}</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white shadow-sm border border-gray-300 rounded-xl border border-gray-300 p-4 mt-6">
                <span class="text-sm text-gray-600">
                    Showing <span class="font-medium text-gray-700">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to <span class="font-medium text-gray-700">{{ Math.min(currentPage * itemsPerPage, filteredResources.length) }}</span> of <span class="font-medium text-gray-700">{{ filteredResources.length }}</span> results
                </span>
                
                <div class="flex items-center gap-2">
                    <button 
                        @click="prevPage" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-all"
                        :class="currentPage === 1 ? 'border-gray-300 text-gray-400 cursor-not-allowed' : 'border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    >
                        Previous
                    </button>
                    
                    <div class="flex items-center gap-1 hidden sm:flex">
                        <button 
                            v-for="pageNum in totalPages" 
                            :key="pageNum"
                            @click="goToPage(pageNum)"
                            class="w-8 h-8 rounded-lg text-sm font-bold flex items-center justify-center transition-all"
                            :class="currentPage === pageNum ? 'bg-blue-600 text-white shadow-[0_0_10px_rgba(245,196,0,0.3)]' : 'text-white/50 hover:bg-white/10 hover:text-gray-900'"
                        >
                            {{ pageNum }}
                        </button>
                    </div>

                    <span class="sm:hidden text-gray-600 text-sm font-medium mx-2">
                        Page {{ currentPage }} of {{ totalPages }}
                    </span>
                    
                    <button 
                        @click="nextPage" 
                        :disabled="currentPage === totalPages"
                        class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-all"
                        :class="currentPage === totalPages ? 'border-gray-300 text-gray-400 cursor-not-allowed' : 'border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Stats Footer -->
        <div class="text-center text-xs text-gray-400 mt-8">
            Total database catalog: {{ resources.length }} items
        </div>

        <!-- 2-Step Reservation Process Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="processModal.isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <!-- Backdrop -->
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-900/90 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="closeProcessModal"></div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <!-- Modal Panel -->
                        <div class="inline-block align-bottom bg-gray-50 rounded-2xl border border-gray-200 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                            
                            <!-- STEP 1: Confirm Reservation -->
                            <div v-if="processModal.step === 1" class="relative">
                                <div class="px-6 pt-10 pb-6 text-center border-b border-gray-300 relative overflow-hidden bg-gray-50">
                                    <div class="absolute inset-0 z-0 opacity-20 bg-cover bg-center" :style="{ backgroundImage: `url('https://ui-avatars.com/api/?name=${encodeURIComponent(processModal.resource?.name)}&background=0d1b2a&color=f5c400&size=512')` }"></div>
                                    <div class="absolute inset-0 z-0 bg-gradient-to-t from-gray-50 to-transparent"></div>
                                    <div class="relative z-10">
                                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-ncst-gold/10 border border-gray-200 shadow-md mb-4">
                                            <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-2xl leading-7 font-extrabold text-gray-900 mb-2" id="modal-title">
                                            Reserve {{ processModal.resource?.name }}
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            Please provide the details for your reservation below.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="px-6 py-6 bg-white shadow-sm border border-gray-300 relative z-10">
                                    <div class="space-y-6 text-left">
                                        <!-- Quantity -->
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <label class="block text-sm font-medium text-gray-700">Quantity</label>
                                                <span class="text-xs text-blue-600 font-medium">{{ processModal.resource?.stock }} Available</span>
                                            </div>
                                            <input 
                                                v-model="reserveForm.quantity" 
                                                type="number" 
                                                min="1" 
                                                :max="processModal.resource?.stock"
                                                class="w-full bg-black/30 border border-gray-300 rounded-xl px-4 py-3 text-gray-900 text-lg font-medium focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold transition-colors" 
                                            />
                                            <span class="text-xs text-red-400 mt-1 block" v-if="reserveForm.errors.quantity">{{ reserveForm.errors.quantity }}</span>
                                        </div>

                                        <!-- Purpose -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Purpose of Borrowing</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button 
                                                    type="button" 
                                                    v-for="purpose in purposeOptions"
                                                    :key="purpose"
                                                    @click="reserveForm.purpose = purpose"
                                                    class="px-3 py-2.5 rounded-xl text-sm font-medium transition-all border shadow-sm"
                                                    :class="reserveForm.purpose === purpose ? 'bg-ncst-gold/20 text-blue-600 border-blue-200 shadow-[0_0_15px_rgba(245,196,0,0.15)] scale-[1.02]' : 'bg-gray-100 border-gray-300 text-gray-900 hover:bg-white/10 hover:border-gray-300'"
                                                >
                                                    {{ purpose === 'Class Project' ? 'Class Project / Lab' : purpose }}
                                                </button>
                                            </div>
                                            
                                            <!-- Other Purpose Input -->
                                            <div v-if="reserveForm.purpose === 'Other'" class="mt-3 transition-all duration-300">
                                                <input 
                                                    type="text" 
                                                    v-model="reserveForm.other_purpose"
                                                    placeholder="Please specify your purpose..."
                                                    class="w-full bg-black/40 border border-gray-300 rounded-xl px-4 py-3 text-gray-900 placeholder-white/30 focus:ring-2 focus:ring-ncst-gold/50 focus:border-blue-200 transition-all shadow-inner"
                                                />
                                            </div>
                                            
                                            <span class="text-xs text-red-400 mt-1 block" v-if="reserveForm.errors.purpose">{{ reserveForm.errors.purpose }}</span>
                                        </div>


                                        
                                        <!-- Global Error Message -->
                                        <div v-if="reserveForm.errors.error" class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl text-red-400 text-sm mt-4 text-left flex items-start">
                                            <svg class="h-5 w-5 text-red-400 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <span>{{ reserveForm.errors.error }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-100 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-300 relative z-20">
                                    <button 
                                        type="button" 
                                        @click.prevent="confirmReservation"
                                        :disabled="reserveForm.processing || !reserveForm.quantity || !reserveForm.purpose || (reserveForm.purpose === 'Other' && !reserveForm.other_purpose)"
                                        class="w-full inline-flex justify-center items-center gap-2 rounded-xl border border-transparent shadow-[0_0_15px_rgba(245,196,0,0.4)] px-6 py-3 bg-ncst-gold text-base font-bold text-white hover:bg-blue-800 hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy sm:w-auto sm:text-sm disabled:opacity-50 disabled:hover:scale-100 disabled:shadow-none transition-all duration-300"
                                    >
                                        <svg v-if="reserveForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        {{ reserveForm.processing ? 'Confirming...' : 'Confirm Reservation' }}
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="closeProcessModal"
                                        :disabled="reserveForm.processing"
                                        class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-transparent text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white/20 focus:ring-offset-ncst-navy sm:w-auto sm:text-sm transition-all disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            <!-- STEP 2: Success -->
                            <div v-else-if="processModal.step === 2" class="relative">
                                <div class="px-6 pt-10 pb-8 text-center">
                                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-500/10 border border-green-500/20 mb-6">
                                        <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-extrabold text-gray-900 mb-2">Request Submitted!</h3>
                                    <p class="text-gray-500 mb-8 max-w-sm mx-auto">
                                        Your request has been sent to the counter staff. <br><br>
                                        <strong>Once approved, a unique QR Pass Code will be automatically generated.</strong> You can find this QR code in your Borrow History page.
                                    </p>

                                    <button 
                                        @click="closeProcessModal"
                                        class="w-full inline-flex justify-center rounded-xl px-6 py-3 bg-blue-600 text-white font-bold hover:bg-blue-800 border shadow-[0_0_12px_rgba(245,196,0,0.3)] transition-all"
                                    >
                                        Got it, Thanks!
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
