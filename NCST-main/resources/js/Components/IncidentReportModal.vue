<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    reservationId: [Number, String],
});

const emit = defineEmits(['close', 'submitted']);

const form = useForm({
    reservation_id: '',
    severity: 'low',
    description: '',
    photo_evidence: null,
});

const handlePhotoUpload = (event) => {
    form.photo_evidence = event.target.files[0];
};

const submit = () => {
    form.reservation_id = props.reservationId;
    form.post('/api/incidents', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            emit('submitted');
            emit('close');
        },
    });
};

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="close"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    
                    <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <form @submit.prevent="submit">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mt-3 text-center sm:mt-0 sm:text-left">
                                    <h3 class="text-xl leading-6 font-bold text-gray-900 mb-2">Report Incident</h3>
                                    <p class="text-sm text-gray-500 mb-6">Log damage, missing parts, or late penalties. The asset will be flagged for maintenance.</p>
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                                        <select v-model="form.severity" class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 py-2 px-3 border">
                                            <option value="low">Low (Minor scratches)</option>
                                            <option value="medium">Medium (Missing accessory)</option>
                                            <option value="high">High (Broken/Unusable)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                        <textarea 
                                            v-model="form.description" 
                                            rows="4" 
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 border p-2"
                                            placeholder="Describe the issue in detail..."
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Photo Evidence</label>
                                        <input 
                                            type="file" 
                                            @change="handlePhotoUpload" 
                                            accept="image/*"
                                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100"
                                        />
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                                <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-500 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm">
                                    Submit Report & Flag Maintenance
                                </button>
                                <button type="button" @click="close" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
