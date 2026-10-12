<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    resource: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    resource_id: '',
    start_time: '',
    end_time: '',
});

// Update form when resource changes
const currentResource = computed(() => props.resource);

watch(() => props.resource, (newResource) => {
    if (newResource) {
        form.resource_id = newResource.id;
    }
}, { immediate: true });

const submit = () => {
    form.post(route('transaction.request'), {
        onSuccess: () => {
            form.reset();
            emit('close');
            window.location.href = route('history');
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
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <!-- Backdrop -->
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="close"></div>

                    <!-- This element is to trick the browser into centering the modal contents. -->
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <!-- Modal Panel -->
                    <div class="inline-block align-bottom bg-white rounded-2xl border border-blue-600/20 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <form @submit.prevent="submit">
                            <div class="px-6 pt-6 pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                        <h3 class="text-xl leading-6 font-bold text-gray-900 mb-2" id="modal-title">
                                            Reserve {{ currentResource?.name }}
                                        </h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-900/50 mb-6">
                                                Please select the date and time you wish to reserve this {{ currentResource?.type }}.
                                            </p>

                                            <!-- Start Time -->
                                            <div class="mb-4 text-left">
                                                <label for="start_time" class="block text-sm font-medium text-gray-900/70 mb-1">Start Time</label>
                                                <input 
                                                    type="datetime-local" 
                                                    id="start_time" 
                                                    v-model="form.start_time" 
                                                    class="focus:ring-ncst-gold focus:border-blue-600 block w-full shadow-sm sm:text-sm border-gray-200 bg-gray-50 text-gray-900 rounded-xl p-3 border transition-colors"
                                                    required
                                                />
                                                <div v-if="form.errors.start_time" class="text-red-400 text-xs mt-1">{{ form.errors.start_time }}</div>
                                            </div>

                                            <!-- End Time -->
                                            <div class="mb-4 text-left">
                                                <label for="end_time" class="block text-sm font-medium text-gray-900/70 mb-1">End Time</label>
                                                <input 
                                                    type="datetime-local" 
                                                    id="end_time" 
                                                    v-model="form.end_time" 
                                                    class="focus:ring-ncst-gold focus:border-blue-600 block w-full shadow-sm sm:text-sm border-gray-200 bg-gray-50 text-gray-900 rounded-xl p-3 border transition-colors"
                                                    required
                                                />
                                                <div v-if="form.errors.end_time" class="text-red-400 text-xs mt-1">{{ form.errors.end_time }}</div>
                                            </div>

                                            <!-- Global Error Message (e.g. Overlapping or Overdue) -->
                                            <div v-if="form.errors.error" class="bg-red-500/10 border border-red-500/20 p-3 rounded-xl text-red-400 text-sm mt-4">
                                                {{ form.errors.error }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-white/5">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-[0_0_12px_rgba(245,196,0,0.3)] px-6 py-2.5 bg-blue-600 text-base font-bold text-ncst-navy hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 transition-all"
                                >
                                    Confirm Reservation
                                </button>
                                <button 
                                    type="button" 
                                    @click="close"
                                    class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-200 shadow-sm px-6 py-2.5 bg-transparent text-base font-medium text-gray-900/70 hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white/20 focus:ring-offset-ncst-navy sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all"
                                >
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
