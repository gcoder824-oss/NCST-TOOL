<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import SignaturePad from 'signature_pad';

const props = defineProps({
    modelValue: {
        type: String,
        default: null
    },
    title: {
        type: String,
        default: 'Sign Here'
    }
});

const emit = defineEmits(['update:modelValue', 'close']);
const canvasRef = ref(null);
let signaturePad = null;

const printedName = ref('');

const resizeCanvas = () => {
    if (!canvasRef.value) return;
    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
    canvasRef.value.width = canvasRef.value.offsetWidth * ratio;
    canvasRef.value.height = canvasRef.value.offsetHeight * ratio;
    canvasRef.value.getContext("2d").scale(ratio, ratio);
    if (signaturePad) {
        signaturePad.clear();
    }
};

onMounted(() => {
    signaturePad = new SignaturePad(canvasRef.value, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(0, 0, 128)', // dark blue pen
    });
    
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();
});

onUnmounted(() => {
    window.removeEventListener("resize", resizeCanvas);
});

const clear = () => {
    if (signaturePad) signaturePad.clear();
};

const save = () => {
    if (signaturePad.isEmpty()) {
        alert("Please provide a signature first.");
        return;
    }
    if (!printedName.value.trim()) {
        alert("Please provide your printed name.");
        return;
    }
    const data = signaturePad.toDataURL("image/png");
    emit('update:modelValue', data);
    emit('update:printedName', printedName.value);
};
</script>

<template>
    <div class="fixed inset-0 bg-white/80 z-[100] flex items-center justify-center p-4 backdrop-blur-md">
        <div class="bg-gradient-to-b from-gray-50 to-gray-50 border border-blue-600/30 rounded-2xl shadow-[0_0_40px_rgba(245,196,0,0.1)] w-full max-w-lg overflow-hidden flex flex-col transform transition-all animate-in fade-in zoom-in-95 duration-300">
            <!-- Header -->
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-200 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-ncst-gold/10 to-transparent"></div>
                <h3 class="text-gray-800 font-bold text-lg tracking-wide uppercase flex items-center gap-2 relative z-10">
                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    {{ title }}
                </h3>
                <button @click="emit('close')" class="text-gray-900/40 hover:text-gray-900 transition-colors relative z-10 rounded-full hover:bg-gray-100 p-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <!-- Body -->
            <div class="p-6 flex-1 flex flex-col items-center">
                <p class="text-gray-900/60 text-sm mb-4 w-full">Please draw your signature inside the box below:</p>
                
                <!-- Canvas Area -->
                <div class="w-full h-64 bg-white rounded-xl relative overflow-hidden shadow-inner border-2 border-dashed border-blue-600/40 hover:border-blue-600 transition-colors group">
                    <canvas ref="canvasRef" class="w-full h-full cursor-crosshair touch-none"></canvas>
                    <div class="absolute bottom-2 right-3 text-black/20 text-xs font-bold pointer-events-none select-none uppercase tracking-widest">Sign Here</div>
                </div>
                
                <!-- Printed Name Input -->
                <div class="mt-6 w-full text-left">
                    <label class="block text-blue-600 text-xs font-bold mb-2 uppercase tracking-wider">Signature over printed name:</label>
                    <input 
                        v-model="printedName" 
                        type="text" 
                        class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-ncst-gold/50 focus:border-blue-600 outline-none text-gray-900 placeholder-gray-400 transition-all shadow-inner" 
                        placeholder="Enter your printed name" 
                    />
                </div>
            </div>
            
            <!-- Footer Actions -->
            <div class="p-5 border-t border-gray-200 flex justify-end items-center gap-3 bg-gray-50">
                <button @click="clear" class="px-4 py-2.5 text-xs font-bold text-gray-900/50 hover:text-gray-900 transition-colors">Clear</button>
                <div class="flex-1"></div>
                <button @click="emit('close')" class="px-5 py-2.5 text-xs font-bold text-gray-900 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl transition-colors">Cancel</button>
                <button @click="save" class="px-6 py-2.5 text-xs font-bold text-ncst-navy bg-blue-600 hover:bg-yellow-400 rounded-xl shadow-[0_0_15px_rgba(245,196,0,0.3)] hover:shadow-[0_0_20px_rgba(245,196,0,0.5)] transition-all flex items-center gap-2">
                    Confirm Signature
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
            </div>
        </div>
    </div>
</template>
