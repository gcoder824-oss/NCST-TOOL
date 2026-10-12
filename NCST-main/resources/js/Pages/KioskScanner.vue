<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import * as faceapi from 'face-api.js';
import axios from 'axios';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const passToken = ref('');
const assetCode = ref('');
const isScanning = ref(false);
const html5QrCode = ref(null);
const currentScanTarget = ref('pass'); // 'pass' or 'asset'

const reservation = ref(null);
const user = ref(null);
const faceMatchStatus = ref('pending'); // 'pending', 'matched', 'mismatched'
const checkoutStatus = ref('');

const videoRef = ref(null);
let faceCheckInterval = null;

onMounted(async () => {
    // Load face-api models. In a real app, these should be hosted in public/models
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('/models');
    } catch (e) {
        console.warn('Face API models not found in /models.');
    }
});

onUnmounted(() => {
    stopScanner();
    stopWebcam();
});

const startScanner = (target) => {
    currentScanTarget.value = target;
    isScanning.value = true;
    
    html5QrCode.value = new Html5Qrcode("reader");
    html5QrCode.value.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            stopScanner();
            if (target === 'pass') {
                passToken.value = decodedText;
                fetchReservationDetails();
            } else if (target === 'asset') {
                assetCode.value = decodedText;
                processCheckout();
            }
        },
        (errorMessage) => {
            // parse errors ignored for continuous scanning
        }
    ).catch(err => {
        console.error("Failed to start scanner", err);
        isScanning.value = false;
    });
};

const stopScanner = () => {
    if (html5QrCode.value && html5QrCode.value.isScanning) {
        html5QrCode.value.stop().catch(console.error);
    }
    isScanning.value = false;
};

const fetchReservationDetails = async () => {
    try {
        const response = await axios.get(`/api/reservations/verify/${passToken.value}`);
        reservation.value = response.data.reservation;
        user.value = response.data.user;
        startWebcamAndFaceCheck();
    } catch (error) {
        alert.error('Invalid Pass', 'Invalid or missing reservation pass.');
        reservation.value = null;
        user.value = null;
    }
};

const startWebcamAndFaceCheck = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
        
        // Wait for video to be ready before starting interval
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
                    
                    // 0.48 threshold for accurate matching against uploaded ID photo
                    if (distance <= 0.48) {
                        faceMatchStatus.value = 'matched';
                    } else {
                        faceMatchStatus.value = 'mismatched';
                    }
                }
            }, 1000);
        };
    } catch (err) {
        console.error("Error accessing webcam: ", err);
    }
};

const stopWebcam = () => {
    if (faceCheckInterval) clearInterval(faceCheckInterval);
    if (videoRef.value && videoRef.value.srcObject) {
        videoRef.value.srcObject.getTracks().forEach(track => track.stop());
    }
};

const processCheckout = async () => {
    if (reservation.value.resource.asset_code !== assetCode.value) {
        alert.error('Mismatch', 'The scanned asset does not match the reserved resource.');
        assetCode.value = '';
        return;
    }
    
    try {
        await axios.post(`/api/reservations/checkout`, {
            reservation_id: reservation.value.id,
            asset_code: assetCode.value,
            pass_qr_token: passToken.value
        });
        
        checkoutStatus.value = 'success';
        stopWebcam();
        
        // Reset after 3 seconds
        setTimeout(() => {
            resetKiosk();
        }, 3000);
    } catch (error) {
        checkoutStatus.value = 'error';
        alert.error('Error', error.response?.data?.message || 'Error processing checkout.');
    }
};

const resetKiosk = () => {
    passToken.value = '';
    assetCode.value = '';
    reservation.value = null;
    user.value = null;
    faceMatchStatus.value = 'pending';
    checkoutStatus.value = '';
    stopWebcam();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-center text-gray-900 mb-8">Property Staff Verification Kiosk</h1>

            <!-- Scanner Box -->
            <div v-show="isScanning" class="bg-white p-6 rounded-xl shadow-lg mb-8 max-w-md mx-auto">
                <h2 class="text-xl font-bold text-center mb-4">
                    Scanning {{ currentScanTarget === 'pass' ? 'QR Pass' : 'Asset Tag' }}
                </h2>
                <div id="reader" class="w-full"></div>
                <button @click="stopScanner" class="mt-4 w-full bg-red-100 text-red-700 py-2 rounded-lg font-medium">Cancel Scan</button>
            </div>

            <!-- Main Kiosk Layout -->
            <div v-if="!isScanning && !reservation" class="bg-white p-12 rounded-xl shadow-lg text-center">
                <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </div>
                <h2 class="text-2xl font-bold mb-2">Ready for Verification</h2>
                <p class="text-gray-500 mb-8">Scan a student's reservation QR pass to begin the checkout process.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4 max-w-md mx-auto">
                    <button @click="startScanner('pass')" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg font-medium shadow transition">
                        Scan QR Pass
                    </button>
                    <!-- Fallback manual entry -->
                    <div class="flex-1 flex gap-2">
                        <input v-model="passToken" type="text" placeholder="Or enter token" class="w-full border-gray-300 rounded-lg px-3 py-2" @keyup.enter="fetchReservationDetails">
                        <button @click="fetchReservationDetails" class="bg-gray-200 hover:bg-gray-300 px-4 rounded-lg font-bold">→</button>
                    </div>
                </div>
            </div>

            <!-- Verification Process -->
            <div v-if="reservation && !isScanning" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Left Column: Face Verification -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-800 p-4 text-gray-900 text-center font-bold">
                        Facial Recognition Verification
                    </div>
                    <div class="relative bg-black aspect-video flex items-center justify-center overflow-hidden">
                        <video ref="videoRef" class="w-full h-full object-cover" autoplay muted playsinline></video>
                        <canvas id="overlay" class="absolute top-0 left-0 w-full h-full pointer-events-none"></canvas>
                        
                        <!-- Reference Photo Overlay (Optional, for visual comparison) -->
                        <div v-if="user?.photo_path" class="absolute bottom-4 right-4 w-24 h-24 border-2 border-white rounded-lg overflow-hidden shadow-lg">
                            <img :src="`/storage/${user.photo_path}`" alt="Reference ID" class="w-full h-full object-cover" />
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Match Indicator Alert -->
                        <div v-if="faceMatchStatus === 'matched'" class="bg-green-100 border-l-4 border-green-500 p-4 flex items-center">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span class="font-bold text-green-700">MATCH CONFIRMED</span>
                        </div>
                        <div v-else-if="faceMatchStatus === 'mismatched'" class="bg-red-100 border-l-4 border-red-500 p-4 flex items-center">
                            <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            <span class="font-bold text-red-700">FACE MISMATCH DETECTED</span>
                        </div>
                        <div v-else class="bg-yellow-100 border-l-4 border-yellow-500 p-4 flex items-center">
                            <svg class="w-6 h-6 text-yellow-500 mr-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="font-bold text-yellow-700">Scanning face...</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Checkout Details -->
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold border-b pb-2 mb-4 text-gray-800">Borrower Details</h3>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">Name:</span> <span class="font-bold">{{ user.name }}</span></p>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">ID No:</span> <span class="font-bold">{{ user.school_id }}</span></p>
                        <p><span class="text-gray-500 w-24 inline-block">Role:</span> <span class="capitalize font-bold">{{ user.role }}</span></p>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold border-b pb-2 mb-4 text-gray-800">Reserved Resource</h3>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">Item:</span> <span class="font-bold">{{ reservation.resource.name }}</span></p>
                        <p class="mb-1"><span class="text-gray-500 w-24 inline-block">Type:</span> <span class="capitalize">{{ reservation.resource.type }}</span></p>
                        <p><span class="text-gray-500 w-24 inline-block">Status:</span> 
                            <span class="px-2 py-1 text-xs rounded font-bold uppercase bg-blue-100 text-blue-800">{{ reservation.status }}</span>
                        </p>
                    </div>

                    <!-- Step 3: Asset Scan & Checkout -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border-2" :class="faceMatchStatus === 'matched' ? 'border-green-400' : 'border-gray-200 opacity-50 pointer-events-none'">
                        <h3 class="text-lg font-bold mb-4">Complete Checkout</h3>
                        <p class="text-sm text-gray-600 mb-4">Facial verification required before releasing the hardware.</p>
                        
                        <div v-if="checkoutStatus === 'success'" class="bg-green-500 text-white p-4 rounded-lg text-center font-bold text-lg mb-4 shadow-inner">
                            Successfully Checked Out!
                        </div>
                        <div v-else>
                            <button @click="startScanner('asset')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow mb-3 transition">
                                Scan Hardware QR Tag
                            </button>
                            <div class="flex gap-2">
                                <input v-model="assetCode" type="text" placeholder="Or manual asset code" class="w-full border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="processCheckout">
                                <button @click="processCheckout" class="bg-gray-200 hover:bg-gray-300 px-4 rounded-lg font-bold text-sm">Verify</button>
                            </div>
                        </div>
                    </div>
                    
                    <button @click="resetKiosk" class="w-full text-center text-gray-500 hover:text-gray-700 font-medium py-2">
                        Cancel & Reset Kiosk
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>
