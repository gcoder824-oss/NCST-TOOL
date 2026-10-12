<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    resources: Array,
});

const searchQuery = ref('');
const filterType = ref('all');

const currentPage = ref(1);
const itemsPerPage = 7;

// Form state
const isModalOpen = ref(false);
const isEditing = ref(false);
const form = useForm({
    id: null,
    asset_code: '',
    name: '',
    type: 'equipment',
    category: '',
    stock: 0,
});

const openModal = (item = null) => {
    isEditing.value = !!item;
    if (item) {
        form.id = item.id;
        form.asset_code = item.asset_code;
        form.name = item.name;
        form.type = item.type;
        form.category = item.category || '';
        form.stock = item.stock;
    } else {
        form.reset();
        form.id = null;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const saveItem = () => {
    if (isEditing.value) {
        form.put(`/inventory/${form.id}`, {
            onSuccess: () => closeModal()
        });
    } else {
        form.post('/inventory', {
            onSuccess: () => closeModal()
        });
    }
};

const deleteItem = (id) => {
    if (confirm('Are you sure you want to delete this item?')) {
        const deleteForm = useForm({});
        deleteForm.delete(`/inventory/${id}`);
    }
};

const filteredResources = computed(() => {
    return props.resources.filter(resource => {
        if (resource.type === 'facility') return false;
        
        const matchesSearch = resource.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                              resource.asset_code.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesType = filterType.value === 'all' || resource.type === filterType.value;
        return matchesSearch && matchesType;
    });
});

const totalPages = computed(() => Math.ceil(filteredResources.value.length / itemsPerPage) || 1);

const paginatedResources = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredResources.value.slice(start, start + itemsPerPage);
});

watch([searchQuery, filterType], () => {
    currentPage.value = 1;
});
</script>

<template>
    <Head title="Inventory Management" />
    <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Inventory Management</h1>
                <p class="text-sm text-blue-600/80 mt-1">Manage equipment and facility availability.</p>
            </div>

            <!-- Controls -->
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300 shadow-sm">
                <div class="relative w-full sm:w-96">
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input v-model="searchQuery" type="text" placeholder="Search by name or asset code..." class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg pl-10 pr-4 py-2.5 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors placeholder-white/30">
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <select v-model="filterType" class="bg-white border border-gray-200 text-gray-900 text-sm rounded-lg px-4 py-2.5 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors flex-1 sm:flex-none">
                        <option value="all">All Types</option>
                        <option value="equipment">Equipment</option>
                        <option value="consumable">Consumables</option>
                        <option value="kit">Kit</option>
                    </select>
                    <button @click="openModal()" class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-800 transition-colors shadow-lg shadow-ncst-gold/20 whitespace-nowrap">
                        + Add Item
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white shadow-sm border border-gray-300 rounded-xl border border-gray-300 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-blue-600/80 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-4">Asset Code</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Stock</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ncst-gold/10 text-gray-900/90">
                            <tr v-for="item in paginatedResources" :key="item.id" class="hover:bg-gray-50/30 transition-colors">
                                <td class="px-6 py-4 font-mono text-blue-600/80">{{ item.asset_code }}</td>
                                <td class="px-6 py-4 font-medium">{{ item.name }}</td>
                                <td class="px-6 py-4 capitalize">{{ item.type }}</td>
                                <td class="px-6 py-4">{{ item.stock }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="item.stock > 0" class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">Available</span>
                                    <span v-else class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Out of Stock</span>
                                </td>
                                <td class="px-6 py-4 text-right flex justify-end gap-1">
                                    <button @click="openModal(item)" class="text-gray-500 hover:text-blue-600 transition-colors p-1" title="Edit Item">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button @click="deleteItem(item.id)" class="text-gray-500 hover:text-red-400 transition-colors p-1" title="Delete Item">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="paginatedResources.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    No inventory items found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-300 flex items-center justify-between bg-gray-50/30">
                    <span class="text-sm text-gray-600">
                        Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredResources.length) }} of {{ filteredResources.length }} entries
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

        <!-- Add/Edit Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white shadow-sm border border-gray-300 rounded-xl border border-gray-200 shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-300 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">{{ isEditing ? 'Edit Item' : 'Add New Item' }}</h3>
                    <button @click="closeModal" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="saveItem" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-blue-600/80 mb-1">Asset Code</label>
                        <input v-model="form.asset_code" type="text" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" required>
                        <div v-if="form.errors.asset_code" class="text-red-400 text-xs mt-1">{{ form.errors.asset_code }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-blue-600/80 mb-1">Name</label>
                        <input v-model="form.name" type="text" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" required>
                        <div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600/80 mb-1">Type</label>
                            <select v-model="form.type" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold">
                                <option value="equipment">Equipment</option>
                                <option value="consumable">Consumables</option>
                                <option value="kit">Kit</option>
                                <option value="facility">Facility</option>
                            </select>
                            <div v-if="form.errors.type" class="text-red-400 text-xs mt-1">{{ form.errors.type }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600/80 mb-1">Stock</label>
                            <input v-model="form.stock" type="number" min="0" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" required>
                            <div v-if="form.errors.stock" class="text-red-400 text-xs mt-1">{{ form.errors.stock }}</div>
                        </div>
                    </div>
                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition-colors disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Save Item' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>
