<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, useForm, router, Head } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';
import SignaturePad from '@/Components/SignaturePad.vue';

const isSignatureModalOpen = ref(false);

const props = defineProps({
    facilities: {
        type: Array,
        default: () => []
    },
    equipment: {
        type: Array,
        default: () => []
    },
    facilityReservations: {
        type: Array,
        default: () => []
    },
    currentUser: {
        type: Object,
        default: () => ({})
    }
});

const alert = useAlert();
const page = usePage();
const isSubmitted = ref(false);
const user = computed(() => page.props.auth?.user || {});

const today = computed(() => new Date().toISOString().split('T')[0]);

const form = useForm({
    // Personal Info
    first_name: user.value.name ? user.value.name.split(' ')[0] : '',
    surname: user.value.name ? user.value.name.split(' ').slice(1).join(' ') : '',
    middle_name: '',
    address: '',
    department: user.value.department || '',
    company: '',
    contact_no: '',
    
    // Event Info
    date_of_event: '',
    no_of_hours: '',
    starting_time: '',
    actual_time_start: '',
    time_ended: '',
    actual_time_ended: '',
    name_of_activity: '',
    places_of_event: [], // array of resource_ids
    other_place_of_event: '',
    
    // Set-up Needed (Equipments)
    equipments: [], // array of objects { name: string, quantity: 1 }
    other_equipments: '',
    
    // Signatures
    signatures: {
        requestor: false,
        requestor_name: '',
        fmo: false,
        osa: false,
        oss: false,
        ocm: false
    },
    
    // Upon Approval
    with_charge: false,
    with_charge_hours: '',
    with_charge_lighting: false,
    with_charge_electricity: false,
    without_charge: false,
    without_charge_hours: '',
    without_charge_lighting: false,
    without_charge_electricity: false,
    total_amount_words: '',
    total_amount: '',
    
    // Other request
    other_request: ''
});

const hardcodedEquipments = [
    { name: 'Laptop/Computer', hasQty: true },
    { name: 'Printer', hasQty: false },
    { name: 'LCD Projector', hasQty: false },
    { name: 'Screen', hasQty: false },
    { name: 'Internet', hasQty: false },
    { name: 'Sound System', hasQty: false },
    { name: 'Microphone', hasQty: true },
    { name: 'LED TV', hasQty: false },
    { name: 'Extension Cord(s)', hasQty: true },
    { name: 'Podium', hasQty: false },
    { name: 'Table cloth/Linens/Curtains', hasQty: false },
    { name: 'Long table(s)', hasQty: true },
    { name: 'Monoblock Chair(s)', hasQty: true },
    { name: 'Arm chair(s)', hasQty: true },
    { name: 'Institutional Mace', hasQty: false },
    { name: 'Flag Pole(s)', hasQty: false },
    { name: 'NCST Flag', hasQty: false },
    { name: 'Philippine Flag', hasQty: false },
    { name: 'SHS Flag', hasQty: false },
    { name: 'NASTECH Flag', hasQty: false },
    { name: 'IIRT Flag', hasQty: false },
    { name: 'House of Heroes Banner', hasQty: false },
];

function numberToWords(num) {
    if (!num || isNaN(num) || num <= 0) return '';
    const a = ['','one ','two ','three ','four ', 'five ','six ','seven ','eight ','nine ','ten ','eleven ','twelve ','thirteen ','fourteen ','fifteen ','sixteen ','seventeen ','eighteen ','nineteen '];
    const b = ['', '', 'twenty','thirty','forty','fifty', 'sixty','seventy','eighty','ninety'];

    const numStr = num.toString().replace(/[\, ]/g, '');
    let n = parseInt(numStr, 10);
    if (n === 0) return 'zero pesos';
    if (n > 9999999) return num; // Too large for this simple function

    let words = '';
    
    if (Math.floor(n / 1000000) > 0) {
        words += a[Math.floor(n / 1000000)] + 'million ';
        n %= 1000000;
    }
    if (Math.floor(n / 1000) > 0) {
        if (Math.floor(n / 1000) < 20) {
            words += a[Math.floor(n / 1000)] + 'thousand ';
        } else {
            words += b[Math.floor(Math.floor(n / 1000) / 10)] + ' ';
            if (Math.floor(n / 1000) % 10 > 0) {
                words += a[Math.floor(n / 1000) % 10];
            }
            words += 'thousand ';
        }
        n %= 1000;
    }
    if (Math.floor(n / 100) > 0) {
        words += a[Math.floor(n / 100)] + 'hundred ';
        n %= 100;
    }
    if (n > 0) {
        if (n < 20) words += a[n];
        else {
            words += b[Math.floor(n / 10)] + ' ';
            if (n % 10 > 0) words += a[n % 10];
        }
    }
    
    return words.trim() + ' pesos';
}

watch(() => form.total_amount, (newVal) => {
    if (!newVal) {
        form.total_amount_words = '';
        return;
    }
    let cleaned = newVal.toString().replace(/,/g, '');
    if (!isNaN(cleaned)) {
        form.total_amount_words = numberToWords(cleaned);
    }
});


const hasEquipment = (eqName) => form.equipments.some(e => e.name === eqName);

const toggleEquipment = (eqName) => {
    if (hasEquipment(eqName)) {
        form.equipments = form.equipments.filter(e => e.name !== eqName);
    } else {
        form.equipments.push({ name: eqName, quantity: 1 });
    }
};

const getEqQuantity = (eqName) => {
    const eq = form.equipments.find(e => e.name === eqName);
    return eq ? eq.quantity : 1;
};

const reservationsOnSelectedDate = computed(() => {
    if (!form.date_of_event) return [];
    
    return props.facilityReservations.filter(res => {
        // duration is saved as "YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM"
        return res.due_date?.includes(form.date_of_event) || 
               res.duration?.includes(form.date_of_event) ||
               (res.remarks && res.remarks.includes(form.date_of_event));
    });
});

const unavailableFacilityIds = computed(() => {
    return reservationsOnSelectedDate.value.map(res => String(res.resource_id));
});

watch(unavailableFacilityIds, (newIds) => {
    // Automatically uncheck disabled facilities
    form.places_of_event = form.places_of_event.filter(id => !newIds.includes(String(id)));
});

const isFacilityDisabled = (id) => {
    if (unavailableFacilityIds.value.includes(String(id))) return true;
    if (form.places_of_event.length > 0 && !form.places_of_event.includes(String(id))) return true;
    return false;
};

watch([() => form.starting_time, () => form.time_ended], ([start, end]) => {
    if (start && end) {
        const startTime = new Date(`2000-01-01T${start}`);
        const endTime = new Date(`2000-01-01T${end}`);
        let diffMs = endTime - startTime;
        if (diffMs < 0) {
            diffMs += 24 * 60 * 60 * 1000;
        }
        const hours = diffMs / (1000 * 60 * 60);
        form.no_of_hours = Math.round(hours * 10) / 10;
    }
});

const convertNumberToWords = (amount) => {
    const words = [];
    const units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Million', 'Billion'];

    let num = parseFloat(String(amount).replace(/,/g, ''));
    if (isNaN(num) || num === 0) return '';

    let isNegative = num < 0;
    num = Math.abs(num);
    let integerPart = Math.floor(num);
    
    if (integerPart === 0) {
        words.push('Zero');
    } else {
        let scaleIdx = 0;
        let intWords = [];
        while (integerPart > 0) {
            let chunk = integerPart % 1000;
            if (chunk > 0) {
                let chunkWords = [];
                let h = Math.floor(chunk / 100);
                let remainder = chunk % 100;
                
                if (h > 0) chunkWords.push(units[h] + ' Hundred');
                
                if (remainder > 0) {
                    if (remainder < 20) {
                        chunkWords.push(units[remainder]);
                    } else {
                        let t = Math.floor(remainder / 10);
                        let u = remainder % 10;
                        chunkWords.push(tens[t] + (u > 0 ? ' ' + units[u] : ''));
                    }
                }
                
                if (scaleIdx > 0) chunkWords.push(scales[scaleIdx]);
                intWords.unshift(chunkWords.join(' '));
            }
            integerPart = Math.floor(integerPart / 1000);
            scaleIdx++;
        }
        words.push(intWords.join(' '));
    }
    
    let result = words.join(' ') + ' Pesos';
    if (isNegative) result = 'Negative ' + result;
    return result;
};

watch(() => form.total_amount, (newVal) => {
    if (newVal) {
        form.total_amount_words = convertNumberToWords(newVal);
    } else {
        form.total_amount_words = '';
    }
});

const updateEqQuantity = (eqName, qty) => {
    const eq = form.equipments.find(e => e.name === eqName);
    if (eq) {
        eq.quantity = parseInt(qty) || 1;
    }
};

const submitForm = () => {
    if (form.places_of_event.length === 0 || !form.date_of_event || !form.starting_time || !form.time_ended || !form.name_of_activity) {
        alert.warning('Incomplete Form', 'Please complete all required fields (Place of Event, Date, Times, and Name of Activity).');
        return;
    }

    const duration = `${form.date_of_event} ${form.starting_time} to ${form.date_of_event} ${form.time_ended}`;
    const purpose = `${form.name_of_activity} (Facility Form)`;
    
    // Pick the primary facility id if selected, else null (we could handle multiple but backend expects one)
    const primaryResourceId = form.places_of_event.length > 0 ? form.places_of_event[0] : null;

    form.processing = true;
    router.post('/transaction/request', {
        resource_id: primaryResourceId,
        quantity: 1,
        purpose: purpose,
        duration: duration,
        places_of_event: form.places_of_event,
        other_place_of_event: form.other_place_of_event,
        equipments_needed: form.equipments,
        other_equipments: form.other_equipments,
        form_data: form // Pass the entire form state to save in remarks
    }, {
        onSuccess: () => {
            alert.success('Reservation Submitted', 'Your facility reservation request has been successfully submitted and is pending approval.');
            isSubmitted.value = true;
            form.processing = false;
        },
        onError: () => {
            alert.error('Error', 'Failed to submit reservation. Please check your inputs.');
            form.processing = false;
        }
    });
};
</script>

<template>
    <Head title="Facility Reservation Form" />
    <div class="py-10 px-4 sm:px-6 lg:px-8 space-y-10 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="border-b border-gray-200 pb-8 mb-4 relative">
            <!-- Control Number -->
            <div class="absolute top-0 right-0 hidden lg:block text-right">
                <div class="text-gray-500 text-xs font-mono mb-1">CONTROL NO.</div>
                <div class="text-red-500 font-bold text-2xl font-mono tracking-wider">{{ Math.floor(Math.random() * 9000 + 1000) }}</div>
            </div>

            <div class="flex flex-col md:flex-row items-center justify-center gap-6 md:gap-8 pt-4 md:pt-0">
                <!-- Colored Logo -->
                <img src="/ncst-logo.png" alt="NCST Logo" class="w-24 h-24 md:w-32 md:h-32 object-contain shrink-0" />
                
                <!-- School Info -->
                <div class="text-center md:text-left">
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-extrabold text-gray-900 uppercase tracking-tight">National College of Science and Technology</h1>
                    <p class="text-gray-700 text-sm mt-2">Amafel Building Aguinaldo Highway Dasmariñas City, Cavite 4114</p>
                    <p class="text-gray-700 text-sm">Telephone Nos.: (02) 529-8117 Manila - (046) 416-0166 Cavite</p>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <h2 class="text-2xl font-bold text-gray-900 uppercase tracking-wider underline underline-offset-8 decoration-2 decoration-ncst-gold inline-block">Facility Reservation Form</h2>
            </div>
        </div>

        <form v-if="!isSubmitted" @submit.prevent="submitForm" class="space-y-8">
            
            <!-- SECTION 1: PERSONAL INFORMATION -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-300 flex items-center gap-3">
                    <span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs">1</span>
                    <h2 class="text-lg font-bold text-gray-900 uppercase tracking-wider">Personal Information</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <!-- Row 1 -->
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">First Name</label>
                                <input v-model="form.first_name" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Surname</label>
                                <input v-model="form.surname" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Middle Name</label>
                                <input v-model="form.middle_name" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <label class="text-xs text-gray-600 font-medium">Address</label>
                                <input v-model="form.address" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                        </div>
                        
                        <!-- Row 2 -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Department (Internal)</label>
                                <input v-model="form.department" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Company <span class="text-gray-400 italic text-[10px]">(Optional)</span></label>
                                <input v-model="form.company" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Contact No.</label>
                                <input v-model="form.contact_no" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: EVENT INFORMATION -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-300 flex items-center gap-3">
                    <span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs">2</span>
                    <h2 class="text-lg font-bold text-gray-900 uppercase tracking-wider">Event Information</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-8">
                        <!-- Row 1: Time fields -->
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-6">
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Date(s) of Event</label>
                                <input v-model="form.date_of_event" required type="date" :min="today" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">No. of Hours</label>
                                <input v-model="form.no_of_hours" type="text" readonly tabindex="-1" placeholder="--" class="w-full bg-white border border-gray-300 rounded-lg text-gray-600 text-sm px-4 py-2.5 cursor-not-allowed focus:outline-none" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Starting Time</label>
                                <input v-model="form.starting_time" required type="time" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Actual Time Start</label>
                                <input v-model="form.actual_time_start" type="time" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Time Ended</label>
                                <input v-model="form.time_ended" required type="time" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Actual Time Ended</label>
                                <input v-model="form.actual_time_ended" type="time" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-2.5 transition-colors" />
                            </div>
                        </div>
                        
                        <!-- Availability Display -->
                        <div v-if="form.date_of_event" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-2">
                            <h3 class="text-sm font-bold text-blue-800 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Calendar Availability for {{ form.date_of_event }}
                            </h3>
                            <div v-if="reservationsOnSelectedDate.length === 0" class="text-xs text-blue-600 font-medium">
                                All facilities are available on this date.
                            </div>
                            <div v-else class="space-y-2">
                                <div class="text-xs text-gray-700 mb-2 font-medium">Current Reservations (Unavailable):</div>
                                <div v-for="res in reservationsOnSelectedDate" :key="res.id" class="text-xs bg-white p-2.5 rounded-lg flex justify-between items-center border border-gray-200 shadow-sm">
                                    <span class="font-bold text-red-600">{{ res.resource?.name }}</span>
                                    <span class="text-gray-500 font-medium">{{ res.duration }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Name & Place -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t border-gray-300">
                            <!-- Left: Name of Activity -->
                            <div class="space-y-1">
                                <label class="text-xs text-gray-600 font-medium">Name of Activity</label>
                                <textarea v-model="form.name_of_activity" required rows="4" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-3 transition-colors resize-none"></textarea>
                            </div>
                            
                            <!-- Right: Place of Event -->
                            <div class="space-y-4">
                                <label class="text-xs text-gray-600 font-medium block">Place of Event (Select Facility)</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="flex items-center gap-3 cursor-pointer group" :class="{'opacity-40 cursor-not-allowed': isFacilityDisabled('108')}">
                                        <div class="relative flex items-center justify-center shrink-0">
                                            <input type="checkbox" v-model="form.places_of_event" value="108" :disabled="isFacilityDisabled('108')" class="appearance-none w-5 h-5 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer disabled:cursor-not-allowed disabled:bg-gray-200" />
                                            <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">Multipurpose Hall 2/F</span>
                                        <span v-if="unavailableFacilityIds.includes('108')" class="text-[10px] text-red-400 font-bold ml-1">(Unavailable)</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer group" :class="{'opacity-40 cursor-not-allowed': isFacilityDisabled('109')}">
                                        <div class="relative flex items-center justify-center shrink-0">
                                            <input type="checkbox" v-model="form.places_of_event" value="109" :disabled="isFacilityDisabled('109')" class="appearance-none w-5 h-5 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer disabled:cursor-not-allowed disabled:bg-gray-200" />
                                            <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">HRM Bar</span>
                                        <span v-if="unavailableFacilityIds.includes('109')" class="text-[10px] text-red-400 font-bold ml-1">(Unavailable)</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer group" :class="{'opacity-40 cursor-not-allowed': isFacilityDisabled('106')}">
                                        <div class="relative flex items-center justify-center shrink-0">
                                            <input type="checkbox" v-model="form.places_of_event" value="106" :disabled="isFacilityDisabled('106')" class="appearance-none w-5 h-5 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer disabled:cursor-not-allowed disabled:bg-gray-200" />
                                            <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">NCST Basketball Court</span>
                                        <span v-if="unavailableFacilityIds.includes('106')" class="text-[10px] text-red-400 font-bold ml-1">(Unavailable)</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer group" :class="{'opacity-40 cursor-not-allowed': isFacilityDisabled('107')}">
                                        <div class="relative flex items-center justify-center shrink-0">
                                            <input type="checkbox" v-model="form.places_of_event" value="107" :disabled="isFacilityDisabled('107')" class="appearance-none w-5 h-5 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer disabled:cursor-not-allowed disabled:bg-gray-200" />
                                            <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">NCST Field 1</span>
                                        <span v-if="unavailableFacilityIds.includes('107')" class="text-[10px] text-red-400 font-bold ml-1">(Unavailable)</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer group" :class="{'opacity-40 cursor-not-allowed': isFacilityDisabled('45')}">
                                        <div class="relative flex items-center justify-center shrink-0">
                                            <input type="checkbox" v-model="form.places_of_event" value="45" :disabled="isFacilityDisabled('45')" class="appearance-none w-5 h-5 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer disabled:cursor-not-allowed disabled:bg-gray-200" />
                                            <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                        <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors">Library</span>
                                        <span v-if="unavailableFacilityIds.includes('45')" class="text-[10px] text-red-400 font-bold ml-1">(Unavailable)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: SET-UP NEEDED -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-300 flex items-center gap-3">
                    <span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs">3</span>
                    <h2 class="text-lg font-bold text-gray-900 uppercase tracking-wider">Set-up Needed (Equipments)</h2>
                </div>
                <div class="p-6">
                    <div class="columns-1 sm:columns-2 lg:columns-3 gap-x-6">
                        <div v-for="(eq, index) in hardcodedEquipments" :key="index" class="flex items-center gap-2 mb-4 break-inside-avoid">
                            <span class="text-gray-600 text-xs w-5 text-right shrink-0">{{ index + 1 }}.</span>
                            <div class="relative flex items-center justify-center shrink-0">
                                <input type="checkbox" :checked="hasEquipment(eq.name)" @change="toggleEquipment(eq.name)" class="appearance-none w-4 h-4 border border-gray-300 rounded bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700 truncate">{{ eq.name }}<span v-if="eq.hasQty" class="text-gray-600"> (Qty.)</span></span>
                            
                            <input v-if="eq.hasQty" type="number" min="1" :value="getEqQuantity(eq.name)" @input="e => updateEqQuantity(eq.name, e.target.value)" class="w-10 border-b border-gray-300 bg-transparent text-center text-sm text-gray-900 focus:border-ncst-gold focus:outline-none py-0 px-1 appearance-none ml-auto" />
                        </div>
                    </div>
                    
                    <div class="mt-8 flex items-center gap-3">
                        <span class="text-sm text-gray-700 whitespace-nowrap">Others:</span>
                        <input v-model="form.other_equipments" type="text" class="flex-1 border-b border-gray-300 bg-transparent text-sm text-gray-900 focus:border-ncst-gold focus:outline-none py-1 px-2" />
                    </div>
                </div>
            </section>

            <!-- SECTION 4: APPROVAL & CHARGES -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-300">
                    <h2 class="text-lg font-bold text-gray-900 uppercase tracking-wider">Upon Approval of Reservation Schedule</h2>
                </div>
                
                <!-- Side by side blocks -->
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <!-- Left Block: With Charge -->
                    <div class="p-6 border-b md:border-b-0 md:border-r border-gray-300 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.with_charge" class="appearance-none w-5 h-5 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-gray-900/90 font-medium">With Charge</span>
                            </label>
                            
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-600 whitespace-nowrap">No. of usage hours:</span>
                                <input v-model="form.with_charge_hours" type="text" class="w-16 h-8 bg-transparent border border-gray-300 text-gray-900 text-center focus:border-ncst-gold focus:outline-none" />
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-8 pl-8">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.with_charge_lighting" class="appearance-none w-4 h-4 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-sm text-gray-700">Lighting</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.with_charge_electricity" class="appearance-none w-4 h-4 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-sm text-gray-700">Electricity</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Right Block: Without Charge -->
                    <div class="p-6 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.without_charge" class="appearance-none w-5 h-5 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-gray-900/90 font-medium">Without Charge</span>
                            </label>
                            
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-600 whitespace-nowrap">No. of usage hours:</span>
                                <input v-model="form.without_charge_hours" type="text" class="w-16 h-8 bg-transparent border border-gray-300 text-gray-900 text-center focus:border-ncst-gold focus:outline-none" />
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-8 pl-8">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.without_charge_lighting" class="appearance-none w-4 h-4 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-sm text-gray-700">Lighting</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <div class="relative flex items-center justify-center shrink-0">
                                    <input type="checkbox" v-model="form.without_charge_electricity" class="appearance-none w-4 h-4 border border-gray-300 bg-white checked:bg-ncst-gold checked:border-ncst-gold transition-colors peer" />
                                    <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <span class="text-sm text-gray-700">Electricity</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Total Amount Block -->
                <div v-show="form.with_charge" class="border-t border-gray-300 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="flex-1 w-full">
                        <span class="text-sm text-gray-600 block">Total amount charged</span>
                        <span class="text-xs text-gray-600 block mb-1">(amount in words):</span>
                        <input v-model="form.total_amount_words" type="text" class="w-full bg-transparent text-gray-900 font-bold text-lg uppercase focus:outline-none py-1 px-2 border-b border-gray-300 focus:border-ncst-gold transition-colors" />
                    </div>
                    <div class="flex items-center gap-3 md:w-64">
                        <span class="text-lg font-bold text-gray-700">P</span>
                        <input v-model="form.total_amount" type="text" class="flex-1 bg-transparent border-b border-gray-300 text-gray-900 font-mono text-lg text-right focus:border-ncst-gold focus:outline-none py-1 px-2" placeholder="0.00" />
                    </div>
                </div>
            </section>

            <!-- SECTION 5: OTHER REQUESTS -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg p-6 mb-8">
                <label class="text-sm text-gray-900 font-bold uppercase tracking-wider block mb-3">Other Specific Request for Event:</label>
                <div>
                    <input v-model="form.other_request" type="text" class="w-full bg-white border border-gray-300 rounded-lg text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold text-sm px-4 py-3 transition-colors" />
                </div>
            </section>

            <!-- SECTION 6: SIGNATURES -->
            <section class="bg-white shadow-sm border border-gray-300 border border-gray-300 rounded-2xl overflow-hidden shadow-lg p-6 mb-8">
                <!-- SIGNATURES BLOCK -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-y-12 gap-x-8 mb-6">
                    <!-- SENDER -->
                    <div class="flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-900 uppercase mb-16">Sender's Signature:</p>
                            <div class="w-[85%] relative text-center">
                                <!-- Signature Above Line -->
                                <div class="absolute bottom-full left-0 right-0 flex justify-center mb-1">
                                    <button v-if="!form.signatures.requestor" type="button" @click.prevent="isSignatureModalOpen = true" class="bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 border border-blue-500/50 text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1 animate-pulse">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        Please sign here digitally
                                    </button>
                                    <div v-else class="h-16 flex items-center justify-center rounded px-2">
                                        <img :src="form.signatures.requestor" class="h-full object-contain filter invert brightness-0" alt="Signature" />
                                    </div>
                                </div>
                                <!-- Printed Line -->
                                <div class="border-t border-gray-300 pt-1">
                                    <input v-model="form.signatures.requestor_name" placeholder="Requesting Department" class="w-full bg-transparent text-gray-900 font-bold text-sm text-center border-none focus:outline-none focus:ring-0 p-0 m-0" />
                                    <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                </div>
                                <!-- Status Below -->
                                <div v-if="form.signatures.requestor" class="mt-3 flex justify-center">
                                    <div class="bg-green-500/20 text-green-400 border border-green-500/50 text-xs px-3 py-1 rounded-full font-bold flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        ✔ Signed & Verified
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-20">
                            <div class="w-[85%] relative text-center">
                                <div class="border-t border-gray-300 pt-1">
                                    <p class="text-gray-900 font-bold text-sm">Office of the Student Affairs</p>
                                    <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- APPROVED / RECEIVED -->
                    <div class="flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-900 uppercase mb-16">Approved By:</p>
                            <div class="w-[85%] relative text-center">
                                <div class="border-t border-gray-300 pt-1">
                                    <p class="text-gray-900 font-bold text-sm">Facilities Management Office</p>
                                    <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                </div>
                                
                                <div v-if="currentUser?.department === 'FMO' || currentUser?.school_id === 'FMO-Admin'" class="mt-3 flex justify-center">
                                    <button type="button" @click.prevent="alert.success('Signed', 'Digitally signed by FMO Admin')" class="bg-green-500/20 text-green-400 hover:bg-green-500/30 border border-green-500/50 text-xs px-3 py-1 rounded-full transition-colors font-bold shadow-lg flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        Sign as FMO
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-20">
                            <p class="text-xs font-bold text-gray-900 uppercase mb-16">Received By:</p>
                            <div class="w-[85%] relative text-center">
                                <div class="border-t border-gray-300 pt-1">
                                    <p class="text-gray-900 font-bold text-sm">Office of the Safety and Security</p>
                                    <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NOTED BY -->
                    <div class="flex flex-col">
                        <div>
                            <p class="text-xs font-bold text-gray-900 uppercase mb-16">Noted By:</p>
                            <div class="w-[85%] relative text-center">
                                <div class="border-t border-gray-300 pt-1">
                                    <p class="text-gray-900 font-bold text-sm">Office of the Campus Manager</p>
                                    <p class="text-[10px] text-gray-600 italic">(Signature over printed name)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FOOTER -->
                <div class="flex flex-wrap items-center justify-between text-xs text-gray-500 italic mt-8 pt-4">
                    <span>Original - FMO</span>
                    <span>Duplicate - OSS</span>
                    <span>Triplicate - Requestor</span>
                    <span>Quad - Collecting Office</span>
                </div>
            </section>

            <!-- Submit Button -->
            <div class="flex flex-col items-end pt-6 border-t border-gray-300 gap-3">
                <div v-if="!form.signatures.requestor" class="text-xs text-red-400 font-bold bg-red-900/20 px-4 py-2 rounded-lg border border-red-500/30">
                    ⚠️ You must digitally sign as the Requesting Department before submitting.
                </div>
                <button type="submit" :disabled="form.processing || !form.signatures.requestor" class="bg-ncst-gold hover:bg-blue-800 text-white px-8 py-3.5 rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-[0_0_20px_rgba(252,192,16,0.3)] disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none flex items-center gap-3">
                    <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Submit Reservation Form</span>
                    <svg v-if="!form.processing" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
            
        </form>

        <!-- Progress Tracker -->
        <div v-else class="bg-white shadow-sm border border-gray-300 border border-gray-200 rounded-2xl p-10 text-center shadow-2xl max-w-2xl mx-auto mt-10">
            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-green-500/30">
                <svg class="w-10 h-10 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h2 class="text-3xl font-black text-gray-900 mb-2">Form Submitted Successfully!</h2>
            <p class="text-gray-500 mb-8">Your facility reservation request has been forwarded and is now <span class="text-yellow-400 font-bold">PENDING APPROVALS</span>.</p>
            
            <div class="bg-black/30 rounded-xl p-6 border border-gray-300 text-left">
                <h3 class="text-xs font-bold text-gray-600 uppercase tracking-widest mb-4">Awaiting Signatures From Higher Offices</h3>
                <div class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-white/20 before:to-transparent">
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border border-gray-3000 bg-black/50 text-gray-600 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                            <span class="text-xs font-bold">1</span>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-300 bg-gray-100 shadow">
                            <div class="flex items-center justify-between space-x-2 mb-1">
                                <div class="font-bold text-gray-900 text-sm">Office of the Student Affairs</div>
                            </div>
                            <div class="text-gray-600 text-xs">Awaiting signature...</div>
                        </div>
                    </div>
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border border-gray-3000 bg-black/50 text-gray-600 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                            <span class="text-xs font-bold">2</span>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-300 bg-gray-100 shadow">
                            <div class="flex items-center justify-between space-x-2 mb-1">
                                <div class="font-bold text-gray-900 text-sm">Office of the Campus Manager</div>
                            </div>
                            <div class="text-gray-600 text-xs">Awaiting signature...</div>
                        </div>
                    </div>
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border border-gray-3000 bg-black/50 text-gray-600 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                            <span class="text-xs font-bold">3</span>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-300 bg-gray-100 shadow">
                            <div class="flex items-center justify-between space-x-2 mb-1">
                                <div class="font-bold text-gray-900 text-sm">Office of the Safety and Security</div>
                            </div>
                            <div class="text-gray-600 text-xs">Awaiting signature...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8">
                <button @click="isSubmitted = false; form.reset(); form.signatures.requestor = false;" class="text-sm font-bold text-blue-600 hover:text-yellow-300 transition-colors">
                    ← Submit Another Reservation
                </button>
            </div>
        </div>
    </div>

    <SignaturePad 
        v-if="isSignatureModalOpen" 
        title="Requesting Department Signature" 
        @close="isSignatureModalOpen = false" 
        @update:modelValue="(val) => { form.signatures.requestor = val; isSignatureModalOpen = false; }"
        @update:printedName="(val) => form.signatures.requestor_name = val"
    />
</template>
