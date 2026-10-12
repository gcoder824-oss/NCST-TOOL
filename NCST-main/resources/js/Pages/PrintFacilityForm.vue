<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    reservation: Object
});

const details = computed(() => {
    try {
        return typeof props.reservation.remarks === 'string' ? JSON.parse(props.reservation.remarks) : (props.reservation.remarks || {});
    } catch (e) {
        return {};
    }
});

const printPage = () => {
    window.print();
};

const hasEquipment = (name) => {
    return details.value.equipments?.some(e => e.name.toLowerCase().includes(name.toLowerCase()));
};

const getEqQty = (name) => {
    const eq = details.value.equipments?.find(e => e.name.toLowerCase().includes(name.toLowerCase()));
    return eq ? eq.quantity : '';
};
</script>

<template>
    <Head title="Print Reservation Form" />

    <div class="print-container bg-white text-black min-h-screen max-w-[800px] mx-auto p-8 font-sans relative">
        <button @click="printPage" class="no-print absolute top-4 right-4 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded shadow-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Document
        </button>

        <!-- HEADER -->
        <div class="flex items-center justify-between mb-4">
            <div class="w-[100px] flex-shrink-0">
                <img src="/ncst-logo.png" alt="NCST Logo" class="w-full h-auto object-contain" />
            </div>
            <div class="flex-1 text-center font-arial px-4">
                <h1 class="text-xl font-bold uppercase tracking-wide">National College of Science and Technology</h1>
                <p class="text-sm">Amafel Building Aguinaldo Highway Dasmariñas City, Cavite 4114</p>
                <p class="text-sm">Telephone Nos.: (02) 529-8117 Manila - (046) 416-0166 Cavite</p>
            </div>
            <div class="w-[100px] flex-shrink-0">
                <!-- Empty for balance -->
            </div>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-xl font-black underline tracking-widest uppercase">FACILITY RESERVATION FORM</h2>
        </div>

        <div class="flex justify-end mb-1">
            <div class="text-red-600 font-bold text-lg flex items-center gap-2">
                <span class="text-black text-sm">CONTROL NO.</span>
                <span class="tracking-widest">{{ reservation.id.toString().padStart(4, '0') }}</span>
            </div>
        </div>

        <!-- PERSONAL INFORMATION -->
        <div class="mb-4">
            <div class="bg-gray-200 font-bold px-2 py-1 uppercase text-sm border border-black border-b-0">PERSONAL INFORMATION</div>
            <div class="border border-black grid grid-cols-4 text-xs divide-y divide-black">
                <div class="col-span-4 grid grid-cols-4 divide-x divide-black">
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.first_name }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">First Name</div>
                    </div>
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.surname }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Surname</div>
                    </div>
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.middle_name }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Middle Name</div>
                    </div>
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.address }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Address</div>
                    </div>
                </div>
                <div class="col-span-4 grid grid-cols-4 divide-x divide-black">
                    <div class="p-2 relative h-12 col-span-2">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.department }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Department (Internal)</div>
                    </div>
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">NCST</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Company</div>
                    </div>
                    <div class="p-2 relative h-12">
                        <div class="absolute top-1 left-2 w-[90%] border-b border-gray-400 font-medium text-center uppercase truncate">{{ details.contact_no }}</div>
                        <div class="absolute bottom-1 left-2 text-[10px] text-gray-600">Contact No.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EVENT INFORMATION -->
        <div class="mb-4">
            <div class="bg-gray-200 font-bold px-2 py-1 uppercase text-sm border border-black border-b-0">EVENT INFORMATION</div>
            <div class="border border-black text-xs divide-y divide-black">
                <div class="grid grid-cols-6 divide-x divide-black">
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">Date(s) of Event:</div>
                        <div class="font-medium text-center">{{ details.date_of_event }}</div>
                    </div>
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">No. of Hours:</div>
                        <div class="font-medium text-center">{{ details.no_of_hours }}</div>
                    </div>
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">Starting Time:</div>
                        <div class="font-medium text-center">{{ details.starting_time }}</div>
                    </div>
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">Actual Time Start:</div>
                        <div class="font-medium text-center"></div>
                    </div>
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">Time Ended:</div>
                        <div class="font-medium text-center">{{ details.time_ended }}</div>
                    </div>
                    <div class="p-2 h-12 col-span-1">
                        <div class="text-[10px] text-gray-600 mb-1">Actual Time Ended:</div>
                        <div class="font-medium text-center"></div>
                    </div>
                </div>
                <div class="grid grid-cols-6 divide-x divide-black">
                    <div class="p-2 min-h-16 col-span-2 relative">
                        <div class="text-[10px] text-gray-600 absolute top-1 left-2">Name of Activity:</div>
                        <div class="font-medium uppercase text-center mt-4 border-b border-gray-400 mx-2 pb-1">{{ details.name_of_activity }}</div>
                    </div>
                    <div class="p-2 min-h-16 col-span-4 relative">
                        <div class="text-[10px] text-gray-600 absolute top-1 left-2">Place of Event:</div>
                        <div class="mt-4 px-4 font-medium text-center">
                            <span class="uppercase border-b border-gray-400 inline-block min-w-[200px] pb-1">
                                {{ reservation.resource?.name }}
                                <span v-if="details.other_place_of_event"> ({{ details.other_place_of_event }})</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SET-UP NEEDED (EQUIPMENTS) -->
        <div class="mb-4">
            <div class="bg-gray-200 font-bold px-2 py-1 uppercase text-sm border border-black border-b-0">SET-UP NEEDED (EQUIPMENTS)</div>
            <div class="border border-black p-4 text-sm min-h-[80px]">
                <ul v-if="details.equipments && details.equipments.length" class="list-disc list-inside space-y-1">
                    <li v-for="eq in details.equipments" :key="eq.name">
                        <span class="font-bold">{{ eq.name }}</span>
                        <span v-if="eq.quantity"> (Qty: {{ eq.quantity }})</span>
                    </li>
                </ul>
                <div v-if="details.other_equipments" class="mt-3">
                    <span class="font-bold">Others:</span>
                    <span class="border-b border-gray-400 uppercase inline-block min-w-[200px] ml-2">{{ details.other_equipments }}</span>
                </div>
                <div v-if="!details.equipments?.length && !details.other_equipments" class="text-gray-500 italic">
                    No equipment requested.
                </div>
            </div>
        </div>

        <!-- UPON APPROVAL OF RESERVATION SCHEDULE -->
        <div class="mb-4">
            <div class="bg-gray-200 font-bold px-2 py-1 uppercase text-sm border border-black border-b-0">UPON APPROVAL OF RESERVATION SCHEDULE</div>
            <div class="border border-black text-sm">
                <div class="grid grid-cols-2 divide-x divide-black border-b border-black">
                    <div v-if="details.with_charge" class="p-4">
                        <div class="font-bold mb-2">WITH CHARGE</div>
                        <ul class="list-disc list-inside ml-2">
                            <li>Usage Hours: <span class="font-bold">{{ details.with_charge_hours }}</span></li>
                            <li v-if="details.with_charge_lighting">Lighting Included</li>
                            <li v-if="details.with_charge_electricity">Electricity Included</li>
                        </ul>
                    </div>
                    <div v-else class="p-4 flex items-center justify-center text-gray-400 italic">
                        Not Applicable
                    </div>

                    <div v-if="details.without_charge" class="p-4">
                        <div class="font-bold mb-2">WITHOUT CHARGE</div>
                        <ul class="list-disc list-inside ml-2">
                            <li>Usage Hours: <span class="font-bold">{{ details.without_charge_hours }}</span></li>
                            <li v-if="details.without_charge_lighting">Lighting Included</li>
                            <li v-if="details.without_charge_electricity">Electricity Included</li>
                        </ul>
                    </div>
                    <div v-else class="p-4 flex items-center justify-center text-gray-400 italic">
                        Not Applicable
                    </div>
                </div>
                <div class="p-3 bg-gray-100 flex items-center text-xs">
                    <span class="w-32">Total amount charged <br>(amount in words):</span>
                    <div class="flex-1 border-b border-black mx-2 pt-4 px-2 uppercase font-medium">{{ details.total_amount_words || 'N/A' }}</div>
                    <span class="font-bold text-lg">P</span>
                    <div class="w-32 border-b border-black ml-2 pt-4 text-center font-bold">{{ details.total_amount || '0.00' }}</div>
                </div>
            </div>
        </div>

        <!-- OTHER SPECIFIC REQUEST FOR EVENT -->
        <div class="mb-4">
            <div class="border border-black p-3 text-xs min-h-[80px]">
                <div class="font-bold uppercase mb-2">OTHER SPECIFIC REQUEST FOR EVENT:</div>
                <div class="w-full border-b border-gray-400 mt-5"></div>
                <div class="w-full border-b border-gray-400 mt-5"></div>
                <div class="w-full border-b border-gray-400 mt-5"></div>
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="mt-8 grid grid-cols-3 gap-x-4 gap-y-8 text-[11px] pb-8">
            
            <!-- Left Column -->
            <div class="flex flex-col justify-between">
                <div>
                    <div class="font-bold mb-8">SENDER'S SIGNATURE:</div>
                    <div class="text-center relative">
                        <div class="absolute bottom-8 left-0 right-0 flex justify-center">
                            <img v-if="details.signatures?.requestor" :src="details.signatures.requestor" class="h-16 object-contain" />
                        </div>
                        <div class="font-bold uppercase border-t border-black pt-1 px-2 mx-4 truncate">{{ details.signatures?.requestor_name || details.first_name + ' ' + details.surname }}</div>
                        <div class="text-[9px] italic text-gray-600 mt-1">Requesting Department</div>
                        <div class="text-[8px] italic text-gray-500">(Signature over printed name)</div>
                    </div>
                </div>
                <div class="mt-8">
                    <div class="text-center relative">
                        <div class="absolute bottom-8 left-0 right-0 flex justify-center">
                            <template v-if="details.signatures?.osa">
                                <img v-if="typeof details.signatures.osa === 'string'" :src="details.signatures.osa" class="h-16 object-contain" />
                                <template v-else>
                                    <img :src="details.signatures.osa.data" class="h-16 object-contain" />
                                </template>
                            </template>
                        </div>
                        <div class="font-bold uppercase border-t border-black pt-1 px-2 mx-4 truncate">{{ details.signatures?.osa?.name || 'Office of the Student Affairs' }}</div>
                        <div class="text-[9px] italic text-gray-600 mt-1">Office of the Student Affairs</div>
                        <div class="text-[8px] italic text-gray-500">(Signature over printed name)</div>
                    </div>
                </div>
            </div>

            <!-- Middle Column -->
            <div class="flex flex-col justify-between">
                <div>
                    <div class="font-bold mb-8">APPROVED BY:</div>
                    <div class="text-center relative">
                        <div class="absolute bottom-8 left-0 right-0 flex justify-center">
                            <template v-if="details.signatures?.fmo">
                                <img v-if="typeof details.signatures.fmo === 'string'" :src="details.signatures.fmo" class="h-16 object-contain" />
                                <template v-else>
                                    <img :src="details.signatures.fmo.data" class="h-16 object-contain" />
                                </template>
                            </template>
                        </div>
                        <div class="font-bold uppercase border-t border-black pt-1 px-2 mx-4 truncate">{{ details.signatures?.fmo?.name || 'Facilities Management Office' }}</div>
                        <div class="text-[9px] italic text-gray-600 mt-1">Facilities Management Office</div>
                        <div class="text-[8px] italic text-gray-500">(Signature over printed name)</div>
                    </div>
                </div>
                <div class="mt-8">
                    <div class="font-bold mb-8">RECEIVED BY:</div>
                    <div class="text-center relative">
                        <div class="absolute bottom-8 left-0 right-0 flex justify-center">
                            <template v-if="details.signatures?.oss">
                                <img v-if="typeof details.signatures.oss === 'string'" :src="details.signatures.oss" class="h-16 object-contain" />
                                <template v-else>
                                    <img :src="details.signatures.oss.data" class="h-16 object-contain" />
                                </template>
                            </template>
                        </div>
                        <div class="font-bold uppercase border-t border-black pt-1 px-2 mx-4 truncate">{{ details.signatures?.oss?.name || 'Office of the Safety and Security' }}</div>
                        <div class="text-[9px] italic text-gray-600 mt-1">Office of the Safety and Security</div>
                        <div class="text-[8px] italic text-gray-500">(Signature over printed name)</div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col justify-between">
                <div>
                    <div class="font-bold mb-8">NOTED BY:</div>
                    <div class="text-center relative">
                        <div class="absolute bottom-8 left-0 right-0 flex justify-center">
                            <template v-if="details.signatures?.ocm">
                                <img v-if="typeof details.signatures.ocm === 'string'" :src="details.signatures.ocm" class="h-16 object-contain" />
                                <template v-else>
                                    <img :src="details.signatures.ocm.data" class="h-16 object-contain" />
                                </template>
                            </template>
                        </div>
                        <div class="font-bold uppercase border-t border-black pt-1 px-2 mx-4 truncate">{{ details.signatures?.ocm?.name || 'Office of the Campus Manager' }}</div>
                        <div class="text-[9px] italic text-gray-600 mt-1">Office of the Campus Manager</div>
                        <div class="text-[8px] italic text-gray-500">(Signature over printed name)</div>
                    </div>
                </div>
                <div class="mt-8">
                    <!-- Blank for spacing matching physical form -->
                </div>
            </div>
            
        </div>

        <div class="absolute bottom-4 left-8 right-8 flex justify-between text-[10px] text-gray-500 italic">
            <span>Original - FMO</span>
            <span>Duplicate - OSS</span>
            <span>Triplicate - Requestor</span>
            <span>Quad - Collecting Office</span>
        </div>
    </div>
</template>

<style>
.font-arial {
    font-family: Arial, Helvetica, sans-serif;
}
@media print {
    body { background: white; }
    .no-print { display: none !important; }
    .print-container { 
        padding: 0 !important;
        margin: 0 auto !important;
        max-width: 100% !important;
        width: 100% !important;
    }
}
</style>
