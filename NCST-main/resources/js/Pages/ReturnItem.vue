<script setup>
import { ref, onUnmounted, onMounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import * as faceapi from 'face-api.js';
import axios from 'axios';
import IncidentReportModal from '@/Components/IncidentReportModal.vue';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const assetCode = ref('');
const isScanning = ref(false);
const html5QrCode = ref(null);

const reservation = ref(null);
const user = ref(null);
const faceMatchStatus = ref('pending');
const returnStatus = ref('');
const isIncidentModalOpen = ref(false);

const videoRef = ref(null);
let faceCheckInterval = null;

onMounted(async () => {
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('/models');
    } catch (e) {
        console.warn('Face API models not found.');
    }
});

onUnmounted(() => {
    stopScanner();
    stopWebcam();
});

const startScanner = () => {
    isScanning.value = true;
    html5QrCode.value = new Html5Qrcode("return-reader");
    html5QrCode.value.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            stopScanner();
            assetCode.value = decodedText;
            fetchActiveReservation();
        },
        (errorMessage) => {}
    ).catch(err => {
        console.error("Scanner failed", err);
        isScanning.value = false;
    });
};

const stopScanner = () => {
    if (html5QrCode.value && html5QrCode.value.isScanning) {
        html5QrCode.value.stop().catch(console.error);
    }
    isScanning.value = false;
};

const fetchActiveReservation = async () => {
    try {
        const response = await axios.get(`/api/reservations/active-by-asset/${assetCode.value}`);
        reservation.value = response.data.reservation;
        user.value = response.data.user;
        startWebcamAndFaceCheck();
    } catch (error) {
        alert.error('Error', 'Could not find an active (checked_in or overdue) reservation for this asset.');
        assetCode.value = '';
    }
};

const startWebcamAndFaceCheck = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
        
        videoRef.value.onplay = () => {
            faceCheckInterval = setInterval(async () => {
                if (!user.value || !user.value.face_descriptor) return;
                
                const detections = await faceapi.detectSingleFace(videoRef.value, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detections) {
                    const raw = typeof user.value.face_descriptor === 'string' ? JSON.parse(user.value.face_descriptor) : user.value.face_descriptor;
                    const storedDescriptor = new Float32Array(Array.isArray(raw) ? raw : Object.values(raw));
                    const distance = faceapi.euclideanDistance(detections.descriptor, storedDescriptor);
                    
                    if (distance <= 0.48) {
                        faceMatchStatus.value = 'matched';
                    } else {
                        faceMatchStatus.value = 'mismatched';
                    }
                }
            }, 1000);
        };
    } catch (err) {
        console.error("Webcam error", err);
    }
};

const stopWebcam = () => {
    if (faceCheckInterval) clearInterval(faceCheckInterval);
    if (videoRef.value && videoRef.value.srcObject) {
        videoRef.value.srcObject.getTracks().forEach(track => track.stop());
    }
};

const processReturn = async () => {
    try {
        await axios.post(`/api/reservations/return`, {
            reservation_id: reservation.value.id,
            asset_code: assetCode.value
        });
        
        returnStatus.value = 'success';
        stopWebcam();
        
        setTimeout(() => {
            resetView();
        }, 3000);
    } catch (error) {
        alert.error('Error', 'Error processing return.');
    }
};

const resetView = () => {
    assetCode.value = '';
    reservation.value = null;
    user.value = null;
    faceMatchStatus.value = 'pending';
    returnStatus.value = '';
    stopWebcam();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-10 px-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-center text-gray-900 mb-8">Return Processing Station</h1>

            <div v-show="isScanning" class="bg-white p-6 rounded-xl shadow-lg mb-8 max-w-md mx-auto text-center">
                <h2 class="text-xl font-bold mb-4">Scan Returned Asset QR</h2>
                <div id="return-reader" class="w-full mx-auto"></div>
                <button @click="stopScanner" class="mt-4 w-full bg-red-100 text-red-700 py-2 rounded-lg font-medium">Cancel</button>
            </div>

            <div v-if="!isScanning && !reservation" class="bg-white p-12 rounded-xl shadow-lg text-center max-w-lg mx-auto">
                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <h2 class="text-2xl font-bold mb-4">Accept a Return</h2>
                <button @click="startScanner" class="w-full bg-green-500 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-bold shadow transition mb-4">
                    Scan Asset Tag
                </button>
                <div class="flex gap-2">
                    <input v-model="assetCode" type="text" placeholder="Or enter code manually" class="w-full border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="fetchActiveReservation">
                    <button @click="fetchActiveReservation" class="bg-gray-200 hover:bg-gray-300 px-4 rounded-lg font-bold text-sm">Find</button>
                </div>
            </div>

            <div v-if="reservation && !isScanning" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-800 p-4 text-gray-900 text-center font-bold">Fast Facial Re-check</div>
                    <div class="relative bg-black aspect-video flex items-center justify-center">
                        <video ref="videoRef" class="w-full h-full object-cover" autoplay muted playsinline></video>
                    </div>
                    <div class="p-6 text-center">
                        <div v-if="faceMatchStatus === 'matched'" class="bg-green-100 text-green-800 p-3 rounded font-bold">
                            IDENTITY VERIFIED
                        </div>
                        <div v-else class="bg-yellow-100 text-yellow-800 p-3 rounded font-bold animate-pulse">
                            AWAITING MATCH...
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-bold border-b pb-2 mb-4">Return Summary</h3>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">Item:</span> <span class="font-bold">{{ reservation.resource.name }}</span></p>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">Borrower:</span> <span class="font-bold">{{ user.name }}</span></p>
                        <p><span class="text-gray-500 w-24 inline-block">Status:</span> 
                            <span :class="reservation.status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'" class="px-2 py-1 text-xs rounded font-bold uppercase">
                                {{ reservation.status }}
                            </span>
                        </p>
                    </div>

                    <div class="mt-8 space-y-3">
                        <div v-if="returnStatus === 'success'" class="bg-green-500 text-white p-4 rounded-lg text-center font-bold">
                            Return Processed Successfully!
                        </div>
                        <template v-else>
                            <button 
                                @click="processReturn" 
                                :disabled="faceMatchStatus !== 'matched'"
                                :class="faceMatchStatus === 'matched' ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 cursor-not-allowed'"
                                class="w-full text-gray-900 font-bold py-3 rounded-lg shadow transition"
                            >
                                Complete Return (Condition: Good)
                            </button>
                            
                            <button @click="isIncidentModalOpen = true" class="w-full bg-red-100 hover:bg-red-200 text-red-700 font-bold py-3 rounded-lg transition border border-red-300">
                                Log Incident & Flag Maintenance
                            </button>
                        </template>
                        
                        <button @click="resetView" class="w-full text-center text-gray-500 hover:text-gray-700 py-2 mt-2">Cancel</button>
                    </div>
                </div>
            </div>
            
            <IncidentReportModal 
                :show="isIncidentModalOpen" 
                :reservationId="reservation?.id" 
                @close="isIncidentModalOpen = false"
                @submitted="resetView" 
            />
        </div>
    </div>
</template>
