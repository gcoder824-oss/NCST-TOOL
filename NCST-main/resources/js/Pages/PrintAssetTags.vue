<script setup>
import { computed } from 'vue';

const props = defineProps({
    resources: {
        type: Array,
        default: () => []
    }
});

// A simple utility to generate a QR URL dynamically if we want to point to a specific asset API route.
// In this case, we might create a generic API for the asset tags or embed directly.
const getQrUrl = (assetCode) => {
    // We assume there's a route that generates a QR code given a string, 
    // or we can just point to an asset info page URL encoded in the QR.
    const baseUrl = window.location.origin;
    const url = `${baseUrl}/resources/scan/${assetCode}`;
    // Using a public API for demonstration if our backend doesn't have an asset QR route yet,
    // but preferably we use our own backend endpoint. We'll use a placeholder URL 
    // that assumes we have an endpoint like /api/qr?text=...
    return `/api/qr?text=${encodeURIComponent(url)}`;
};
</script>

<template>
    <div class="bg-gray-100 min-h-screen py-10 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6 no-print">
                <h1 class="text-3xl font-bold text-gray-900">Print Physical Asset Tags</h1>
                <button 
                    onclick="window.print()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg shadow transition flex items-center"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Tags
                </button>
            </div>

            <!-- Print Layout Grid -->
            <div class="print-container grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div 
                    v-for="resource in resources" 
                    :key="resource.id"
                    class="bg-white border-2 border-black rounded-lg p-3 flex flex-col items-center justify-center text-center shadow-sm asset-tag"
                >
                    <div class="font-bold text-sm uppercase tracking-wider mb-2 border-b-2 border-black pb-1 w-full">NCST Property</div>
                    
                    <!-- We can use the simple-qrcode API endpoint here if we create one, or an img tag -->
                    <!-- Since we installed simple-qrcode, we can assume the backend will provide this -->
                    <div class="qr-wrapper bg-gray-50 p-2 border border-gray-200 mb-2">
                        <img :src="`/resources/${resource.id}/qr`" alt="QR Code" class="w-24 h-24" />
                    </div>
                    
                    <h3 class="font-bold text-sm leading-tight mb-1">{{ resource.name }}</h3>
                    <p class="text-xs font-mono bg-gray-100 px-2 py-1 rounded w-full">{{ resource.asset_code }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    .print-container {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 10px !important;
    }
    .asset-tag {
        page-break-inside: avoid;
        border: 2px solid black !important;
        box-shadow: none !important;
    }
    body {
        background-color: white !important;
    }
}
</style>
