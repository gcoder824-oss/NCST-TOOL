<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    accounts: Array,
});

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const statusFilter = ref('active'); // active, suspended, deleted

// Form state
const isModalOpen = ref(false);
const isEditing = ref(false);
const form = useForm({
    id: null,
    name: '',
    email: '',
    school_id: '',
    role: 'student',
    password: '',
});

const openModal = (user = null) => {
    isEditing.value = !!user;
    if (user) {
        form.id = user.id;
        form.name = user.name;
        form.email = user.email;
        form.school_id = user.school_id;
        form.role = user.role;
        form.password = ''; // Don't pre-fill password for editing
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

const saveUser = () => {
    if (isEditing.value) {
        form.put(`/users/${form.id}`, {
            onSuccess: () => closeModal()
        });
    } else {
        form.post('/users', {
            onSuccess: () => closeModal()
        });
    }
};

const userToView = ref(null);

const viewUser = (user) => {
    userToView.value = user;
};

const closeViewModal = () => {
    userToView.value = null;
};

const userToDelete = ref(null);

const confirmDelete = (user) => {
    userToDelete.value = user;
};

const cancelDelete = () => {
    userToDelete.value = null;
};

const executeDelete = () => {
    if (userToDelete.value) {
        const deleteForm = useForm({});
        deleteForm.delete(`/users/${userToDelete.value.id}`, {
            onSuccess: () => cancelDelete()
        });
    }
};

const restoreUser = (id) => {
    const restoreForm = useForm({});
    restoreForm.post(`/users/${id}/restore`);
};

const filteredAccounts = computed(() => {
    return props.accounts.filter(account => {
        let matchesStatus = false;
        if (statusFilter.value === 'all') {
            matchesStatus = true;
        } else if (statusFilter.value === 'deleted') {
            matchesStatus = !!account.deleted_at;
        } else if (statusFilter.value === 'suspended') {
            matchesStatus = !account.deleted_at && account.is_suspended;
        } else {
            // active
            matchesStatus = !account.deleted_at && !account.is_suspended;
        }
        
        if (!matchesStatus) return false;
        
        return account.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
               account.email.toLowerCase().includes(searchQuery.value.toLowerCase());
    });
});

const totalPages = computed(() => Math.ceil(filteredAccounts.value.length / itemsPerPage) || 1);

const paginatedAccounts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredAccounts.value.slice(start, start + itemsPerPage);
});
</script>

<template>
    <Head title="User Directory" />
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">User Directory</h1>
            <p class="text-sm text-blue-600/80 mt-1">View and manage registered users.</p>
        </div>

        <!-- Controls -->
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300 shadow-sm">
            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto flex-1">
                <div class="relative w-full sm:w-96">
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input v-model="searchQuery" type="text" placeholder="Search by name or email..." class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg pl-10 pr-4 py-2.5 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors placeholder-white/30">
                </div>
                
                <div class="relative w-full sm:w-auto">
                    <select v-model="statusFilter" class="w-full sm:w-48 bg-white border border-gray-200 text-gray-900 text-sm rounded-lg pl-4 pr-10 py-2.5 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors appearance-none cursor-pointer font-medium">
                        <option value="all" class="bg-gray-50 text-gray-900">All Users</option>
                        <option value="active" class="bg-gray-50 text-gray-900">Active Users</option>
                        <option value="suspended" class="bg-gray-50 text-gray-900">Suspended Users</option>
                        <option value="deleted" class="bg-gray-50 text-gray-900">Deleted Users</option>
                    </select>
                    <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white shadow-sm border border-gray-300 rounded-xl border border-gray-300 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-blue-600/80 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Name</th>
                            <th class="px-6 py-4">Email</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Registered Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ncst-gold/10 text-gray-900/90">
                        <tr v-for="account in paginatedAccounts" :key="account.id" class="hover:bg-gray-50/30 transition-colors" :class="{'opacity-75': account.deleted_at}">
                            <td class="px-6 py-4 font-mono text-blue-600/80">#{{ account.id }}</td>
                            <td class="px-6 py-4 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0" :class="account.deleted_at ? 'bg-red-400/50' : 'bg-ncst-gold'">
                                        {{ account.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span :class="{'line-through text-gray-600': account.deleted_at}">{{ account.name }}</span>
                                        <span v-if="account.deleted_at" class="text-[10px] text-red-400 font-bold uppercase tracking-wider">Deleted</span>
                                        <span v-else-if="account.is_suspended" class="text-[10px] text-red-400 font-bold uppercase tracking-wider">Suspended</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ account.email }}</td>
                            <td class="px-6 py-4 capitalize">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium border inline-block text-center min-w-[70px]"
                                    :class="{
                                        'bg-blue-500/10 text-blue-400 border-blue-500/20': account.role === 'student',
                                        'bg-purple-500/10 text-purple-400 border-purple-500/20': account.role === 'faculty',
                                        'bg-orange-500/10 text-orange-400 border-orange-500/20': account.role === 'staff',
                                        'bg-red-500/10 text-red-400 border-red-500/20': account.role === 'admin'
                                    }">
                                    {{ account.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">
                                {{ new Date(account.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-1">
                                <template v-if="!account.deleted_at">
                                    <button @click="viewUser(account)" class="text-gray-500 hover:text-blue-400 transition-colors p-1" title="View User">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button @click="openModal(account)" class="text-gray-500 hover:text-blue-600 transition-colors p-1" title="Edit User">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(account)" class="text-gray-500 hover:text-red-400 transition-colors p-1" title="Delete User">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </template>
                                <template v-else>
                                    <button @click="restoreUser(account.id)" class="text-green-400/80 hover:text-green-400 transition-colors px-3 py-1 rounded border border-green-400/20 text-xs font-bold bg-green-400/10 hover:bg-green-400/20" title="Restore User">
                                        RESTORE
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="paginatedAccounts.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-300 flex items-center justify-between bg-gray-50/30">
                <span class="text-sm text-gray-600">
                    Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredAccounts.length) }} of {{ filteredAccounts.length }} entries
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

        <!-- View User Modal -->
        <div v-if="userToView" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-gray-50 border border-gray-200 rounded-2xl w-full max-w-6xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-300 flex justify-between items-center bg-white shadow-sm border border-gray-300 shrink-0">
                    <h3 class="text-xl font-bold text-gray-900">User Information</h3>
                    <button @click="closeViewModal" class="text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6 flex flex-col md:flex-row gap-6 overflow-y-auto">
                    <!-- Left Column: Info -->
                    <div class="flex-1 space-y-6">
                        <!-- Basic Info -->
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-2xl shrink-0 bg-ncst-gold">
                            {{ userToView.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">{{ userToView.name }}</h4>
                            <p class="text-blue-600/80">{{ userToView.email }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">ID Number</span>
                            <span class="text-gray-900 font-medium">{{ userToView.school_id }}</span>
                        </div>
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">Role</span>
                            <span class="text-gray-900 font-medium capitalize">{{ userToView.role }}</span>
                        </div>
                        
                        <!-- Account & Clearance -->
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">Account Status</span>
                            <span class="font-medium uppercase" :class="userToView.is_suspended ? 'text-red-400' : 'text-green-400'">
                                {{ userToView.is_suspended ? 'SUSPENDED' : 'ACTIVE' }}
                            </span>
                        </div>
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">Clearance</span>
                            <span class="font-medium" :class="userToView.clearance_status === 'CLEARED' ? 'text-green-400' : 'text-red-400'">
                                {{ userToView.clearance_status || 'CLEARED' }}
                            </span>
                        </div>
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300 col-span-2 flex justify-between items-center">
                            <div>
                                <span class="text-xs text-gray-500 block mb-1">Member Since</span>
                                <span class="text-gray-900 font-medium">{{ new Date(userToView.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Student Specific Info -->
                    <div v-if="userToView.role === 'student'" class="grid grid-cols-2 gap-4">
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">Course</span>
                            <span class="text-gray-900 font-medium">{{ userToView.course || 'N/A' }}</span>
                        </div>
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                            <span class="text-xs text-gray-500 block mb-1">Department</span>
                            <span class="text-gray-900 font-medium">{{ userToView.department || 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Active Loans -->
                    <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider font-bold">Active Borrowed Items</span>
                            <span class="text-blue-600 font-bold bg-ncst-gold/10 px-3 py-1 rounded-full text-xs">
                                {{ userToView.reservations?.length || 0 }} Items
                            </span>
                        </div>
                        <div v-if="userToView.reservations?.length" class="space-y-2 mt-3 max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                            <div v-for="res in userToView.reservations" :key="res.id" class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-300 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ res.resource?.name || 'Unknown Item' }}</span>
                                    <span class="text-[10px] text-gray-500">Borrowed: {{ new Date(res.created_at).toLocaleDateString() }}</span>
                                </div>
                                <span class="text-xs bg-ncst-gold/20 text-blue-600 px-2 py-1 rounded font-mono">x{{ res.quantity }}</span>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-400 text-center py-4 bg-gray-50 rounded-lg border border-gray-300 mt-3">
                            No active borrowed items.
                        </div>
                    </div>
                    </div>

                    <!-- Right Column: Media Section -->
                    <div class="w-full md:w-7/12 shrink-0 flex flex-col md:flex-row gap-4">
                        <!-- Face Photo Section -->
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300 flex flex-col flex-1">
                            <span class="text-sm font-semibold text-gray-900 block mb-3">Facial Recognition Verified Image</span>
                            
                            <div v-if="userToView.face_photo" class="rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center bg-black/50 aspect-[4/3] w-full">
                                <img :src="userToView.face_photo" alt="Face Verification" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-gray-300 flex flex-col items-center justify-center bg-black/20 aspect-[4/3] w-full p-6 text-center">
                                <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="text-sm text-gray-500 font-medium">No verified image on record</span>
                                <span class="text-xs text-gray-400 mt-1">This user was registered without facial data.</span>
                            </div>

                            <p v-if="userToView.face_photo" class="text-xs text-gray-600 mt-4 text-center">This image was used for identity verification.</p>
                        </div>
                        
                        <!-- ID Card Section -->
                        <div class="bg-white shadow-sm border border-gray-300 p-4 rounded-xl border border-gray-300 flex flex-col flex-1">
                            <span class="text-sm font-semibold text-gray-900 block mb-3">Uploaded Valid ID</span>
                            
                            <div v-if="userToView.photo_path" class="rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center bg-black/50 aspect-[4/3] w-full">
                                <img :src="userToView.photo_path.startsWith('http') || userToView.photo_path.startsWith('data:') ? userToView.photo_path : '/storage/' + userToView.photo_path" alt="ID Card" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-gray-300 flex flex-col items-center justify-center bg-black/20 aspect-[4/3] w-full p-6 text-center">
                                <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                                <span class="text-sm text-gray-500 font-medium">No ID uploaded</span>
                                <span class="text-xs text-gray-400 mt-1">This user has not provided an ID.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-300 bg-white shadow-sm border border-gray-300 flex justify-end shrink-0">
                    <button @click="closeViewModal" class="px-4 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-800 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white shadow-sm border border-gray-300 rounded-xl border border-gray-200 shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-300 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">{{ isEditing ? 'Edit User' : 'Add New User' }}</h3>
                    <button @click="closeModal" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="saveUser" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-blue-600/80 mb-1">Name</label>
                        <input v-model="form.name" type="text" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" required>
                        <div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-blue-600/80 mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" required>
                        <div v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-blue-600/80 mb-1">School ID / Employee ID</label>
                        <input v-model="form.school_id" type="text" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" :placeholder="form.role === 'student' ? 'e.g. 0000-00000' : 'Employee ID'" required>
                        <div v-if="form.errors.school_id" class="text-red-400 text-xs mt-1">{{ form.errors.school_id }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600/80 mb-1">Role</label>
                            <select v-model="form.role" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold">
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="staff">Staff (Custodian)</option>
                                <option value="admin">Administrator (Superadmin)</option>

                            </select>
                            <div v-if="form.errors.role" class="text-red-400 text-xs mt-1">{{ form.errors.role }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600/80 mb-1">
                                Password 
                                <span v-if="isEditing" class="text-gray-500 text-xs font-normal">(Leave blank to keep)</span>
                            </label>
                            <input v-model="form.password" type="password" class="w-full bg-white border border-gray-200 text-gray-900 rounded-lg px-4 py-2 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30" :required="!isEditing">
                            <div v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</div>
                        </div>
                    </div>
                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition-colors disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Save User' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Delete Confirmation Modal -->
        <div v-if="userToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white shadow-sm border border-gray-300 rounded-xl border border-red-500/20 shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
                <div class="p-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4 text-red-500 border border-red-500/20 shadow-inner">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete User</h3>
                    <p class="text-gray-600 text-sm">
                        Are you sure you want to delete <strong class="text-gray-900">{{ userToDelete.name }}</strong>? This action cannot be undone.
                    </p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-red-500/10 flex justify-end gap-3">
                    <button @click="cancelDelete" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors bg-gray-100 hover:bg-white/10 rounded-lg">
                        Cancel
                    </button>
                    <button @click="executeDelete" class="bg-red-500 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-red-600 transition-colors shadow-lg shadow-red-500/20">
                        Yes, Delete User
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px #0B1B3D inset !important;
    -webkit-text-fill-color: white !important;
    transition: background-color 5000s ease-in-out 0s;
}
</style>
