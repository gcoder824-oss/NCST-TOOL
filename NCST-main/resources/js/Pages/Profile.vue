<script setup>
import { usePage, useForm, router, Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import * as faceapi from 'face-api.js';
import { useAlert } from '@/utils/alert';

const alert = useAlert();
const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const form = useForm({
    name: user.value.name,
    email: user.value.email,
    school_id: user.value.school_id,
});

const initials = computed(() => {
    if (!user.value.name) return '??';
    return user.value.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const submit = () => {
    form.post('/profile/update', {
        preserveScroll: true,
        onSuccess: () => alert.success('Profile Updated', 'Personal information saved successfully.')
    });
};

// ── ID Scanner & Biometrics State ──────────────────────────────
const isScannerModalOpen = ref(false);
const scannerMode = ref('upload'); // 'upload' or 'camera'
const isModelsLoading = ref(false);
const isScanningId = ref(false);
const isSavingBiometrics = ref(false);
const scanError = ref('');

const selectedIdFile = ref(null);
const previewIdUrl = ref('');
const extractedFacePhoto = ref('');
const extractedDescriptor = ref(null);
const faceDetectionConfidence = ref(0);

// Webcam refs
const cameraVideo = ref(null);
let cameraStream = null;
const isCameraActive = ref(false);

const loadFaceApiModels = async () => {
    if (isModelsLoading.value) return;
    isModelsLoading.value = true;
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models')
        ]);
    } catch (err) {
        console.error('Error loading face-api models:', err);
    } finally {
        isModelsLoading.value = false;
    }
};

onMounted(() => {
    loadFaceApiModels();
});

onUnmounted(() => {
    stopCamera();
});

const openScannerModal = () => {
    scanError.value = '';
    selectedIdFile.value = null;
    previewIdUrl.value = '';
    extractedFacePhoto.value = '';
    extractedDescriptor.value = null;
    isScannerModalOpen.value = true;
    scannerMode.value = 'upload';
    loadFaceApiModels();
};

const closeScannerModal = () => {
    stopCamera();
    isScannerModalOpen.value = false;
};

// ── File Upload Processing ──────────────────────────────────────
const handleFileUpload = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        scanError.value = 'Please select a valid image file (JPG or PNG).';
        return;
    }

    selectedIdFile.value = file;
    scanError.value = '';
    
    const reader = new FileReader();
    reader.onload = (event) => {
        previewIdUrl.value = event.target.result;
        processIdImage(event.target.result);
    };
    reader.readAsDataURL(file);
};

// ── Camera Scanner Processing ───────────────────────────────────
const startCamera = async () => {
    scannerMode.value = 'camera';
    scanError.value = '';
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
        });
        if (cameraVideo.value) {
            cameraVideo.value.srcObject = cameraStream;
            cameraVideo.value.play();
            isCameraActive.value = true;
        }
    } catch (err) {
        console.error('Camera access error:', err);
        scanError.value = 'Unable to access camera. Please check permissions or upload an image file instead.';
    }
};

const stopCamera = () => {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    isCameraActive.value = false;
};

const captureFromCamera = () => {
    if (!cameraVideo.value) return;

    const video = cameraVideo.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
    stopCamera();
    previewIdUrl.value = dataUrl;
    scannerMode.value = 'upload';
    processIdImage(dataUrl);
};

// ── Face Detection & Extraction from ID ─────────────────────────
const processIdImage = async (imageSrc) => {
    isScanningId.value = true;
    scanError.value = '';
    extractedFacePhoto.value = '';
    extractedDescriptor.value = null;

    try {
        await loadFaceApiModels();

        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.src = imageSrc;

        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = () => reject(new Error('Failed to load ID image.'));
        });

        // Detect single face with landmarks & descriptor
        const detection = await faceapi.detectSingleFace(
            img, 
            new faceapi.TinyFaceDetectorOptions({ inputSize: 512, scoreThreshold: 0.3 })
        )
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            scanError.value = 'No clear face detected on the ID. Please ensure your ID photo is well-lit, upright, and without glare or obstruction.';
            isScanningId.value = false;
            return;
        }

        // Face found!
        faceDetectionConfidence.value = Math.round(detection.detection.score * 100);
        extractedDescriptor.value = Array.from(detection.descriptor);

        // Crop face from ID into clean portrait
        const box = detection.detection.box;
        const padX = box.width * 0.3;
        const padY = box.height * 0.3;
        const sx = Math.max(0, box.x - padX);
        const sy = Math.max(0, box.y - padY);
        const sWidth = Math.min(img.width - sx, box.width + padX * 2);
        const sHeight = Math.min(img.height - sy, box.height + padY * 2);

        const faceCanvas = document.createElement('canvas');
        faceCanvas.width = 240;
        faceCanvas.height = 240;
        const fCtx = faceCanvas.getContext('2d');
        fCtx.drawImage(img, sx, sy, sWidth, sHeight, 0, 0, 240, 240);
        extractedFacePhoto.value = faceCanvas.toDataURL('image/jpeg', 0.92);

    } catch (err) {
        console.error('ID processing error:', err);
        scanError.value = 'An error occurred while analyzing the ID card. Please try uploading again.';
    } finally {
        isScanningId.value = false;
    }
};

// ── Save Biometrics to Backend ──────────────────────────────────
const saveBiometrics = () => {
    if (!extractedDescriptor.value || !extractedFacePhoto.value) {
        alert.error('Missing Face Data', 'Please scan a valid ID with your face photo before saving.');
        return;
    }

    isSavingBiometrics.value = true;
    router.post('/profile/biometrics', {
        face_descriptor: extractedDescriptor.value,
        face_photo: extractedFacePhoto.value,
        id_photo: previewIdUrl.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSavingBiometrics.value = false;
            closeScannerModal();
            alert.success(
                'Biometrics Registered',
                'Your ID has been scanned and registered. Face recognition will now be verified against this photo during borrowing and returning.'
            );
        },
        onError: (err) => {
            isSavingBiometrics.value = false;
            alert.error('Registration Failed', err.message || 'Failed to save biometric ID.');
        }
    });
};
</script>

<template>
    <Head title="My Profile" />

    <div class="py-10 space-y-10 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">My Profile</h1>
                <p class="text-gray-600 text-sm mt-1">Manage your account information, institutional ID, and biometric security.</p>
            </div>
            
            <button 
                @click="openScannerModal" 
                class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2 self-start sm:self-auto"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                {{ user.face_descriptor ? 'Update / Re-scan ID' : 'Upload & Scan ID' }}
            </button>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Identity & Biometrics -->
            <div class="col-span-1 space-y-6">
                <!-- User Card -->
                <div class="bg-white shadow-sm border border-gray-200 rounded-3xl p-8 text-center relative overflow-hidden shadow-lg">
                    <div class="absolute top-0 left-0 w-full h-24 bg-blue-600/5 blur-xl"></div>
                    <div class="relative z-10 flex flex-col items-center">
                        <!-- Profile Face Photo or Initials -->
                        <div class="relative mb-4">
                            <div v-if="user.face_photo || user.photo_path" class="w-24 h-24 rounded-full overflow-hidden border-4 border-blue-600 shadow-[0_0_20px_rgba(37,99,235,0.2)]">
                                <img :src="user.face_photo || user.photo_path" alt="Biometric Portrait" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="w-24 h-24 rounded-full bg-blue-600 flex items-center justify-center text-white text-3xl font-black shadow-[0_0_20px_rgba(37,99,235,0.2)]">
                                {{ initials }}
                            </div>
                            <!-- Verified Biometric Icon Badge -->
                            <div v-if="user.face_descriptor" class="absolute -bottom-1 -right-1 w-7 h-7 bg-green-500 text-white rounded-full flex items-center justify-center shadow-md border-2 border-white" title="Biometric Security Active">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>

                        <h2 class="text-xl font-bold text-gray-900">{{ user.name }}</h2>
                        <p class="text-blue-600 font-semibold text-xs mt-1 capitalize px-3 py-1 bg-blue-50 rounded-full border border-blue-100">
                            {{ user.role }} • {{ user.school_id }}
                        </p>
                    </div>
                </div>
                
                <!-- Biometrics Identity Card -->
                <div class="bg-white shadow-sm border border-gray-200 rounded-3xl p-6 shadow-lg space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h3 class="text-gray-900 font-bold text-base flex items-center gap-2">
                            <span>🛡️</span> Biometrics Security
                        </h3>
                        <span 
                            class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full border"
                            :class="user.face_descriptor ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-600 border-red-200'"
                        >
                            {{ user.face_descriptor ? 'Enrolled' : 'Not Registered' }}
                        </span>
                    </div>

                    <!-- Enrolled Biometrics Details -->
                    <div v-if="user.face_descriptor" class="space-y-4">
                        <div class="p-4 rounded-2xl bg-green-50/60 border border-green-200/80 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-500/20 text-green-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-green-900 leading-tight">Facial Security Basis Active</p>
                                <p class="text-xs text-green-700 mt-1 leading-relaxed">
                                    Your registered ID photo is active as the security standard. Whenever you borrow or return items, the counter scanner matches your live face against this photo.
                                </p>
                            </div>
                        </div>

                        <!-- Photos Preview: Face and ID card -->
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <!-- Cropped Face Photo -->
                            <div class="bg-gray-50 p-2.5 rounded-2xl border border-gray-200 text-center">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Registered Face</p>
                                <div class="w-full h-24 rounded-xl overflow-hidden bg-gray-200 flex items-center justify-center border border-gray-200">
                                    <img v-if="user.face_photo || user.photo_path" :src="user.face_photo || user.photo_path" class="w-full h-full object-cover" alt="Face">
                                    <span v-else class="text-xs text-gray-400">No Photo</span>
                                </div>
                            </div>

                            <!-- Full ID Card -->
                            <div class="bg-gray-50 p-2.5 rounded-2xl border border-gray-200 text-center">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Scanned ID Card</p>
                                <div class="w-full h-24 rounded-xl overflow-hidden bg-gray-200 flex items-center justify-center border border-gray-200">
                                    <img v-if="user.photo_path" :src="user.photo_path" class="w-full h-full object-cover" alt="ID Card">
                                    <span v-else class="text-xs text-gray-400">ID on file</span>
                                </div>
                            </div>
                        </div>

                        <button 
                            @click="openScannerModal" 
                            class="w-full py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                        >
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Re-scan / Replace ID
                        </button>
                    </div>

                    <!-- Not Enrolled State -->
                    <div v-else class="space-y-4">
                        <div class="p-4 rounded-2xl bg-red-50/70 border border-red-200 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-500/20 text-red-600 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-red-900 leading-tight">No Facial Data Registered</p>
                                <p class="text-xs text-red-700 mt-1 leading-relaxed">
                                    Please upload and scan your institutional ID card. The photo will be used to verify your identity with facial recognition whenever you borrow or return items.
                                </p>
                            </div>
                        </div>

                        <button 
                            @click="openScannerModal" 
                            class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-md shadow-blue-500/20"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Upload & Scan ID Card
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings Form -->
            <div class="col-span-1 lg:col-span-2 space-y-6">
                <!-- Personal Info Card -->
                <div class="bg-white shadow-sm border border-gray-200 rounded-3xl shadow-lg overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Personal Information</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Update your basic student or employee details</p>
                        </div>
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-bold rounded-full">
                            Role: {{ user.role }}
                        </span>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Full Name</label>
                                    <input 
                                        type="text" 
                                        v-model="form.name" 
                                        class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                        required
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">NCST Institutional ID</label>
                                    <input 
                                        type="text" 
                                        v-model="form.school_id" 
                                        class="w-full bg-gray-100 border border-gray-300 rounded-xl px-4 py-2.5 text-gray-600 cursor-not-allowed opacity-80" 
                                        readonly
                                    >
                                    <p class="text-[11px] text-gray-400">Institutional ID cannot be changed directly.</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Email Address</label>
                                <input 
                                    type="email" 
                                    v-model="form.email" 
                                    class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                    required
                                >
                            </div>
                            
                            <div class="pt-2 flex justify-end">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing" 
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/20 transition-all disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Borrowing Security Explanation Card -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50/40 rounded-3xl border border-blue-200/80 p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-md">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-gray-900 text-base">Facial Recognition Security Standard</h4>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                To prevent unauthorized borrowing and verify accountable custodianship, the NCST system uses high-accuracy 128-dimensional biometric facial descriptors. The face detected on your uploaded institutional ID card serves as the permanent ground truth reference against live counter cameras during item dispensing and returns.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── ID Scanner & Biometrics Modal ────────────────────────────── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isScannerModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="closeScannerModal"></div>

                    <div class="relative bg-white border border-gray-200 rounded-3xl w-full max-w-2xl shadow-2xl z-10 overflow-hidden flex flex-col max-h-[92vh]">
                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                            <div>
                                <h3 class="text-lg font-black text-gray-900 flex items-center gap-2">
                                    <span>📸</span> Scan Institutional ID Card
                                </h3>
                                <p class="text-xs text-gray-500">Extract facial biometric security basis for borrowing & returning</p>
                            </div>
                            <button @click="closeScannerModal" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-200 transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-6 overflow-y-auto space-y-6">
                            <!-- Toggle Mode (Upload / Camera) -->
                            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl">
                                <button 
                                    @click="stopCamera(); scannerMode = 'upload'"
                                    class="flex-1 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
                                    :class="scannerMode === 'upload' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Upload ID File
                                </button>
                                <button 
                                    @click="startCamera"
                                    class="flex-1 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
                                    :class="scannerMode === 'camera' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Snap via Webcam
                                </button>
                            </div>

                            <!-- Error Message -->
                            <div v-if="scanError" class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-start gap-3">
                                <span class="text-base shrink-0">⚠️</span>
                                <div class="flex-1 leading-relaxed">{{ scanError }}</div>
                            </div>

                            <!-- Mode A: File Upload Drop Area -->
                            <div v-if="scannerMode === 'upload' && !previewIdUrl" class="border-2 border-dashed border-gray-300 hover:border-blue-500 rounded-3xl p-8 text-center transition cursor-pointer bg-gray-50 hover:bg-blue-50/20 relative">
                                <input 
                                    type="file" 
                                    accept="image/*" 
                                    @change="handleFileUpload" 
                                    class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                >
                                <div class="w-16 h-16 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-4 text-2xl shadow-sm">
                                    🪪
                                </div>
                                <p class="text-sm font-bold text-gray-900">Click or Drag & Drop your Institutional ID Card</p>
                                <p class="text-xs text-gray-500 mt-1">Supports PNG, JPG, JPEG (Ensure face photo is sharp and visible)</p>
                            </div>

                            <!-- Mode B: Live Camera Scanner -->
                            <div v-if="scannerMode === 'camera'" class="space-y-4">
                                <div class="relative w-full aspect-[4/3] max-h-72 bg-black rounded-3xl overflow-hidden flex items-center justify-center">
                                    <video ref="cameraVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
                                    
                                    <!-- ID Card Alignment Overlay -->
                                    <div class="absolute inset-6 border-2 border-dashed border-white/80 rounded-2xl pointer-events-none flex flex-col justify-between p-3">
                                        <div class="flex justify-between items-center text-[10px] text-white/80 font-mono">
                                            <span>NCST ID FRAME</span>
                                            <span>POSITION CARD HERE</span>
                                        </div>
                                        <div class="text-center text-xs text-white/90 font-bold bg-black/50 py-1 rounded-lg backdrop-blur-sm">
                                            Keep ID flat, well-lit, and in focus
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <button 
                                        @click="captureFromCamera" 
                                        class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-sm transition shadow-md shadow-blue-500/20 flex items-center justify-center gap-2"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        </svg>
                                        Capture ID Image
                                    </button>
                                    <button 
                                        @click="stopCamera(); scannerMode = 'upload'" 
                                        class="px-5 py-3 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-100 transition"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            <!-- Scanning Animation State -->
                            <div v-if="isScanningId" class="py-12 text-center flex flex-col items-center justify-center space-y-4">
                                <div class="w-16 h-16 rounded-full border-4 border-blue-200 border-t-blue-600 animate-spin"></div>
                                <div>
                                    <p class="font-bold text-gray-900 text-sm">Scanning ID Card & Extracting Facial Biometrics...</p>
                                    <p class="text-xs text-gray-500 mt-1">Detecting face landmarks and generating 128-d vector</p>
                                </div>
                            </div>

                            <!-- Extracted Results Preview -->
                            <div v-if="!isScanningId && previewIdUrl && extractedFacePhoto" class="space-y-4">
                                <div class="p-4 rounded-2xl bg-green-50 border border-green-200 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-sm">
                                            ✓
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-green-900">Valid Face Detected on ID Card</p>
                                            <p class="text-xs text-green-700">Detection Confidence: {{ faceDetectionConfidence }}% • Biometrics Ready</p>
                                        </div>
                                    </div>
                                    <button 
                                        @click="previewIdUrl = ''; extractedFacePhoto = null; extractedDescriptor = null;"
                                        class="text-xs font-bold text-gray-500 hover:text-red-600 transition"
                                    >
                                        Retake / Change
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Extracted Face Crop -->
                                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 text-center flex flex-col items-center">
                                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Extracted Face Portrait</span>
                                        <div class="w-32 h-32 rounded-2xl overflow-hidden border-2 border-green-500 shadow-md mb-2">
                                            <img :src="extractedFacePhoto" alt="Extracted Face" class="w-full h-full object-cover">
                                        </div>
                                        <p class="text-[11px] text-green-700 font-semibold">Official Basis for Borrowing/Return</p>
                                    </div>

                                    <!-- Scanned Full ID Preview -->
                                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 text-center flex flex-col items-center">
                                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Full ID Card Document</span>
                                        <div class="w-full h-32 rounded-2xl overflow-hidden border border-gray-300 shadow-sm mb-2 bg-black flex items-center justify-center">
                                            <img :src="previewIdUrl" alt="Full ID" class="w-full h-full object-contain">
                                        </div>
                                        <p class="text-[11px] text-gray-500">Archived institutional verification</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3 bg-gray-50">
                            <button 
                                @click="closeScannerModal" 
                                class="px-5 py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 text-gray-700 text-xs font-bold transition"
                            >
                                Cancel
                            </button>
                            <button 
                                v-if="extractedDescriptor && extractedFacePhoto"
                                @click="saveBiometrics" 
                                :disabled="isSavingBiometrics"
                                class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50"
                            >
                                <span v-if="isSavingBiometrics" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                {{ isSavingBiometrics ? 'Saving Biometrics...' : 'Save Biometric ID' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
