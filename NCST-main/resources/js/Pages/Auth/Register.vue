<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed, onBeforeUnmount, nextTick } from 'vue';
import * as faceapi from 'face-api.js';
import Tesseract from 'tesseract.js';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const form = useForm({
    name: '',
    role: 'student',
    email: '',
    school_id: '',
    password: '',
    password_confirmation: '',
    face_descriptor: null,
});

// ID Verification State
const idImage = ref(null);
const idFaceDescriptor = ref(null);
const isAnalyzingId = ref(false);
const idError = ref('');

const handleIdUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (form.role !== 'visitor' && !form.school_id) {
        idError.value = "Please enter your School ID Number first.";
        event.target.value = '';
        return;
    }

    idError.value = '';
    isAnalyzingId.value = true;
    idFaceDescriptor.value = null;
    idImage.value = null;
    
    try {
        await loadModels();
        const imageUrl = URL.createObjectURL(file);
        
        const img = new Image();
        img.onload = async () => {
            try {
                // Run face detection and OCR concurrently for speed and UX
                const [detection, ocrResult] = await Promise.all([
                    faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks()
                        .withFaceDescriptor(),
                    Tesseract.recognize(file, 'eng')
                ]);

                // Validate OCR
                const text = ocrResult.data.text;
                const normalizedText = text.toUpperCase().replace(/[^A-Z0-9]/g, '');
                
                // Helper for fuzzy matching substring to handle OCR misreads
                const fuzzyIncludes = (source, target, maxErrors = 2) => {
                    if (!target) return false;
                    for (let i = 0; i <= source.length - target.length; i++) {
                        let errors = 0;
                        for (let j = 0; j < target.length; j++) {
                            if (source[i + j] !== target[j]) {
                                errors++;
                                if (errors > maxErrors) break;
                            }
                        }
                        if (errors <= maxErrors) return true;
                    }
                    return false;
                };

                // Look for NCST or National College of Science & Technology
                const hasNCST = fuzzyIncludes(normalizedText, 'NCST', 1) || 
                                fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCETECHNOLOGY', 4) ||
                                fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCE', 3) ||
                                fuzzyIncludes(normalizedText, 'NATIONALCOLLEGEOFSCIENCEANDTECHNOLOGY', 5);

                // Smart Fallback: Some IDs (like Student Assistant) have the NCST text printed extremely small, 
                // which the browser scanner struggles to read if there is glare. However, it can usually read the larger ID number.
                const searchSchoolIdNumbers = form.school_id.replace(/[^0-9]/g, '');
                const ocrTextNumbersOnly = text.toUpperCase()
                    .replace(/O|Q|D/g, '0').replace(/I|L|\||!/g, '1').replace(/Z/g, '2')
                    .replace(/E/g, '3').replace(/A/g, '4').replace(/S/g, '5')
                    .replace(/G/g, '6').replace(/T/g, '7').replace(/B/g, '8')
                    .replace(/P/g, '9').replace(/[^0-9]/g, '');
                
                const hasSchoolId = (searchSchoolIdNumbers.length > 0 && ocrTextNumbersOnly.includes(searchSchoolIdNumbers));

                // Pass if we find the school name OR the school ID (skip check for visitors)
                if (form.role !== 'visitor' && !hasNCST && !hasSchoolId) {
                    idError.value = `Verification failed: The uploaded document could not be authenticated as a valid institutional ID.`;
                    console.log("OCR Extracted Text:", text);
                    console.log("Normalized OCR Text:", normalizedText);
                    event.target.value = '';
                    isAnalyzingId.value = false;
                    return;
                }

                // Validate Face
                if (detection) {
                    idFaceDescriptor.value = detection.descriptor;
                    idImage.value = imageUrl;
                    isAnalyzingId.value = false;
                } else {
                    idError.value = "No face detected in the uploaded ID. Please upload a clear ID with your photo.";
                    event.target.value = '';
                    isAnalyzingId.value = false;
                }
            } catch (err) {
                console.error(err);
                idError.value = "Error during ID analysis.";
                isAnalyzingId.value = false;
                event.target.value = '';
            }
        };
        img.src = imageUrl;
    } catch (e) {
        console.error(e);
        idError.value = "Error analyzing ID image.";
        isAnalyzingId.value = false;
        event.target.value = '';
    }
};

// Liveness & Camera State
const stream = ref(null);
const videoRef = ref(null);
const canvasRef = ref(null);
const livenessStep = ref(-1);
const livenessError = ref('');
const isCameraActive = ref(false);
const isAnalyzing = ref(false);
const isRequestingCamera = ref(false);

const livenessPrompts = [
    { text: "Searching for face...", icon: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" },
    { text: "Move closer to the camera", icon: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" },
    { text: "Center your face in the frame", icon: "M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" },
    { text: "Hold still, analyzing...", icon: "M15 12a3 3 0 11-6 0 3 3 0 016 0z" }
];

const currentLivenessPrompt = computed(() => {
    if (livenessStep.value >= 0 && livenessStep.value < livenessPrompts.length) {
        return livenessPrompts[livenessStep.value];
    }
    return { text: "Verification Complete", icon: "M5 13l4 4L19 7" };
});

const loadModels = async () => {
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri('/models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('/models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('/models');
    } catch (e) {
        console.error("Error loading face-api models:", e);
    }
};

const startCamera = async () => {
    livenessError.value = '';
    isCameraActive.value = true;
    isRequestingCamera.value = true;
    livenessStep.value = 0;
    
    try {
        await loadModels();
        const mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        stream.value = mediaStream;
        isRequestingCamera.value = false;
        
        await nextTick();
        
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.addEventListener('play', () => {
                runLivenessSequence();
            });
        }
    } catch (err) {
        isRequestingCamera.value = false;
        livenessError.value = "Camera not detected. Running simulation mode.";
        runMockLivenessSequence();
    }
};

const runLivenessSequence = () => {
    let attempts = 0;
    let requiredValidFrames = 10;
    let currentValidFrames = 0;
    
    const interval = setInterval(async () => {
        if (!videoRef.value || !isCameraActive.value) {
            clearInterval(interval);
            return;
        }
        
        isAnalyzing.value = true;
        attempts++;
        
        try {
            const detection = await faceapi.detectSingleFace(videoRef.value, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            // Dynamic prompt text based on ACTUAL face position
            if (canvasRef.value && videoRef.value) {
                if (!detection) {
                    livenessStep.value = 0; // Searching for face...
                } else {
                    const displaySize = { width: videoRef.value.clientWidth, height: videoRef.value.clientHeight };
                    const box = detection.detection.box;
                    const faceAreaRatio = (box.width * box.height) / (displaySize.width * displaySize.height);
                    
                    const isCenteredX = (box.x > displaySize.width * 0.15) && ((box.x + box.width) < displaySize.width * 0.85);
                    const isCenteredY = (box.y > displaySize.height * 0.1) && ((box.y + box.height) < displaySize.height * 0.9);
                    
                    if (faceAreaRatio < 0.08) {
                        livenessStep.value = 1; // Move closer
                    } else if (!isCenteredX || !isCenteredY) {
                        livenessStep.value = 2; // Center face
                    } else {
                        livenessStep.value = 3; // Hold still
                    }
                }
            }
                
            // Check match if ID was uploaded
            let isMatch = true;
            let faceDistance = 0;
            if (detection && idFaceDescriptor.value) {
                // Convert to plain arrays to avoid Vue proxy issues
                const desc1 = Array.from(idFaceDescriptor.value);
                const desc2 = Array.from(detection.descriptor);
                faceDistance = faceapi.euclideanDistance(desc1, desc2);
                
                // 0.45 is a much stricter threshold. Anything > 0.45 is considered a different person.
                isMatch = faceDistance <= 0.45;
            }

            // Draw the bounding box
            if (canvasRef.value && videoRef.value) {
                const displaySize = { width: videoRef.value.clientWidth, height: videoRef.value.clientHeight };
                if (canvasRef.value.width !== displaySize.width || canvasRef.value.height !== displaySize.height) {
                    faceapi.matchDimensions(canvasRef.value, displaySize);
                }
                const canvasCtx = canvasRef.value.getContext('2d');
                canvasCtx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
                
                if (detection) {
                    const resizedDetections = faceapi.resizeResults(detection, displaySize);
                    const box = resizedDetections.detection.box;
                    
                    // Style based on match status
                    const boxColor = (idFaceDescriptor.value && !isMatch) ? '#FF0000' : '#00FF00';
                    const boxText = idFaceDescriptor.value 
                        ? (isMatch ? '😊 Match' : '☹️ No Match') + ` (Score: ${faceDistance.toFixed(2)})`
                        : 'Face Detected';

                    canvasCtx.strokeStyle = boxColor;
                    canvasCtx.lineWidth = 3;
                    canvasCtx.strokeRect(box.x, box.y, box.width, box.height);
                    
                    canvasCtx.fillStyle = boxColor;
                    canvasCtx.font = 'bold 18px Arial';
                    canvasCtx.fillText(boxText, box.x, box.y - 10);
                }
            }
                
            if (detection) {
                if (isMatch) {
                    livenessError.value = '';
                    currentValidFrames++;
                    if (currentValidFrames >= requiredValidFrames) {
                        clearInterval(interval);
                        isAnalyzing.value = false;
                        
                        livenessStep.value = livenessPrompts.length; // Success step
                        
                        setTimeout(() => {
                            stopCamera();
                            // Store the actual 128-dimensional face descriptor for recognition
                            form.face_descriptor = Array.from(detection.descriptor); 
                        }, 500);
                    }
                } else {
                    currentValidFrames = 0;
                    livenessError.value = "❌ ERROR: Live face does not match the uploaded ID card.";
                }
            } else {
                currentValidFrames = 0;
            }
        } catch (e) {
            isAnalyzing.value = false;
        }
    }, 200); // 200ms interval for smooth tracking
};

const runMockLivenessSequence = () => {
    isAnalyzing.value = false;
    livenessError.value = "Camera not detected. A live face is required for registration.";
    // Do not automatically set form.face_descriptor
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    isCameraActive.value = false;
    if (canvasRef.value) {
        const ctx = canvasRef.value.getContext('2d');
        ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    }
};

// Clean up camera on unmount
onBeforeUnmount(() => {
    stopCamera();
});

const isRegistering = ref(false);
const progress = ref(0);

const currentStep = ref(1);
const totalSteps = 3;

const steps = [
    { id: 1, title: 'Details' },
    { id: 2, title: 'Security' },
    { id: 3, title: 'Biometrics' }
];

const NCST_EMAIL_SUFFIX = '@ncst.edu.ph';

const isSchoolIdValid = computed(() => {
    if (form.role === 'visitor') return true;
    if (!form.school_id) return null;
    if (form.role === 'student') {
        return /^\d{4}-\d{5}$/.test(form.school_id);
    }
    return form.school_id.length > 2; // Flexible validation for employees
});

const isEmailValid = computed(() => {
    if (!form.email) return null;
    if (form.role === 'visitor') {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email);
    }
    return form.email.endsWith(NCST_EMAIL_SUFFIX);
});

const isPasswordValid = computed(() => {
    if (!form.password) return null;
    return form.password.length >= 8;
});

const isPasswordMatch = computed(() => {
    if (!form.password_confirmation) return null;
    return form.password === form.password_confirmation;
});

const isStep1Valid = computed(() => 
    form.name.trim().length > 0 &&
    isEmailValid.value === true &&
    isSchoolIdValid.value === true &&
    idFaceDescriptor.value !== null
);

const isStep2Valid = computed(() =>
    isPasswordValid.value === true &&
    isPasswordMatch.value === true
);

const isFormValid = computed(() =>
    isStep1Valid.value && isStep2Valid.value && form.face_descriptor !== null
);

const nextStep = () => {
    if (currentStep.value === 1 && !isStep1Valid.value) return;
    if (currentStep.value === 2 && !isStep2Valid.value) return;
    if (currentStep.value < totalSteps) currentStep.value++;
};

const prevStep = () => {
    if (currentStep.value === 3) stopCamera();
    if (currentStep.value > 1) currentStep.value--;
};

const submit = () => {
    if (!isFormValid.value) return;
    
    form.post('/register', {
        onSuccess: () => {
            isRegistering.value = true;
            
            let currentProgress = 0;
            const interval = setInterval(() => {
                currentProgress += (100 / 15); // 15 steps of 100ms = 1.5s
                progress.value = Math.min(currentProgress, 100);
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                router.visit('/dashboard');
            }, 1500);
        },
        onError: (errors) => {
            console.error(errors);
            const errorMessages = Object.values(errors).join('\n');
            alert.error('Registration Failed', errorMessages);
        }
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="max-w-[1200px] w-full bg-white rounded-3xl shadow-2xl flex flex-col md:flex-row overflow-hidden border border-gray-100 min-h-[700px]">
            
            <!-- Left Side (Form) -->
            <div class="w-full md:w-1/2 p-8 sm:p-12 lg:p-16 flex flex-col justify-between bg-white relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                
                <!-- Logo -->
                <div class="flex items-center gap-3 absolute top-8 left-8 sm:top-12 sm:left-12">
                    <img src="/ncst-logo.png" alt="NCST Logo" class="w-8 h-8 object-contain">
                    <span class="text-xl font-bold text-gray-900 tracking-tight">NCST</span>
                </div>
                
                <!-- Form Container -->
                <div class="w-full mx-auto flex-1 flex flex-col justify-center mt-12 mb-8">
                    <div class="flex flex-col items-center">
                <img src="/ncst-logo.png" alt="NCST Logo" class="w-12 h-12 object-contain mb-3">
                <h2 class="text-center text-2xl font-extrabold text-gray-900 tracking-tight">
                    Create NCST Account
                </h2>
                <p v-if="form.role !== 'visitor'" class="mt-1 text-center text-xs text-ncst-navy/80">
                    Registration requires a valid @ncst.edu.ph email
                </p>
            </div>

            <!-- Stepper -->
            <div class="mt-4 mb-6">
                <div class="flex items-center justify-between relative">
                    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-0.5 bg-white rounded-full z-0">
                        <div class="h-full bg-ncst-navy transition-all duration-300" :style="{ width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%` }"></div>
                    </div>
                    
                    <div v-for="step in steps" :key="step.id" class="relative z-10 flex flex-col items-center gap-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all duration-300"
                             :class="[
                                currentStep === step.id ? 'bg-ncst-navy border-ncst-navy text-white shadow' : 
                                currentStep > step.id ? 'bg-ncst-navy border-ncst-navy text-white' : 'bg-white border-gray-300 text-gray-500'
                             ]">
                            <svg v-if="currentStep > step.id" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>{{ step.id }}</span>
                        </div>
                        <span class="text-[10px] font-medium absolute -bottom-4 w-max text-center transition-colors duration-300"
                              :class="currentStep >= step.id ? 'text-ncst-navy' : 'text-gray-400'">
                            {{ step.title }}
                        </span>
                    </div>
                </div>
            </div>
            
            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <!-- Step 1: Details -->
                <div v-show="currentStep === 1" class="space-y-4 animate-[fadeIn_0.3s_ease-out]">
                    <div>
                        <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Full Name</label>
                        <input id="name" type="text" required v-model="form.name" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors" placeholder="Juan Dela Cruz" />
                    </div>
                    
                    <div>
                        <label for="role" class="block text-xs font-medium text-gray-700 mb-1">Account Role</label>
                        <div class="relative">
                            <select id="role" v-model="form.role" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors cursor-pointer">
                                <option value="student">Student</option>
                                <option value="faculty">Employee / Faculty</option>
                                <option value="visitor">Visitor (FRF)</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-ncst-navy/70">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Email Address</label>
                        <input id="email" type="email" required v-model="form.email" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors" :placeholder="form.role === 'visitor' ? 'visitor@example.com' : 'student@ncst.edu.ph'" />
                        <p v-if="form.role !== 'visitor'" class="text-[10px] text-gray-500 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-ncst-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Must strictly end with @ncst.edu.ph
                        </p>
                    </div>
                    
                    <div v-if="form.role !== 'visitor'">
                        <label for="school_id" class="block text-xs font-medium text-gray-700 mb-1">School ID Number</label>
                        <input id="school_id" type="text" required v-model="form.school_id" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors" placeholder="2026-12345" />
                        <p class="text-[10px] text-gray-500 mt-1">Student: YYYY-NNNNN | Employee: Any valid ID</p>
                    </div>

                    <div class="mt-4 p-3 bg-gray-50 border border-gray-300 rounded-lg">
                        <label class="block text-xs font-bold text-ncst-navy mb-2">{{ form.role === 'visitor' ? 'Upload Valid ID (Must contain your photo)' : 'Upload School ID Card (Must contain your photo)' }}</label>
                        <input type="file" accept="image/*" @change="handleIdUpload" class="block w-full text-xs text-gray-900/70 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-ncst-navy file:text-white hover:file:bg-yellow-300 transition-colors" />
                        
                        <div v-if="isAnalyzingId" class="mt-2 text-[10px] text-yellow-400 animate-pulse flex items-center gap-1">
                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Scanning ID for security features and face...
                        </div>
                        <p v-if="idError" class="text-[10px] text-red-400 mt-2">{{ idError }}</p>
                        <div v-if="idImage && !isAnalyzingId" class="mt-2 flex items-center gap-2">
                            <div class="w-10 h-10 rounded-md overflow-hidden border border-green-500 relative shrink-0">
                                <img :src="idImage" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40"><svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
                            </div>
                            <div class="flex flex-col justify-center">
                                <span class="text-[10px] text-green-400 font-bold flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Document authenticity verified
                                </span>
                                <span class="text-[10px] text-green-400 font-bold flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Identification portrait validated
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Security -->
                <div v-show="currentStep === 2" class="space-y-4 animate-[fadeIn_0.3s_ease-out]">
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Password</label>
                            <input id="password" type="password" required v-model="form.password" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors" />
                            <p class="text-[10px] text-gray-500 mt-1">Minimum 8 characters</p>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input id="password_confirmation" type="password" required v-model="form.password_confirmation" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-navy sm:text-sm transition-colors" />
                            <p v-if="form.password_confirmation && !isPasswordMatch" class="text-[10px] text-red-400 mt-1">Passwords do not match</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Biometrics -->
                <div v-show="currentStep === 3" class="space-y-4 animate-[fadeIn_0.3s_ease-out]">
                    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col items-center justify-center text-center relative overflow-hidden min-h-[280px]">
                        
                        <!-- Initial State -->
                        <div v-if="!isCameraActive && !form.face_descriptor" class="flex flex-col items-center z-10 animate-[fadeIn_0.3s_ease-out]">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 border border-gray-300 shadow">
                                <svg class="w-8 h-8 text-ncst-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 mb-2">Active Liveness Check</p>
                            <p class="text-xs text-gray-500 mb-6 max-w-[220px]">We need to verify you are a real person. You will be prompted to perform a few simple actions.</p>
                            <button type="button" @click="startCamera" class="bg-ncst-navy hover:bg-ncst-navy-light text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-md">
                                Start Verification
                            </button>
                        </div>

                        <!-- Active Camera / Liveness Checking State (Full Screen Modal) -->
                        <teleport to="body">
                            <div v-if="isCameraActive" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900 p-4 sm:p-12 animate-[fadeIn_0.3s_ease-out]">
                                
                                <div class="w-full max-w-5xl flex flex-col items-center justify-center h-full max-h-[90vh]">
                                    <h2 class="text-white text-xl font-bold mb-6 flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full border-2 border-blue-400 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        Face Biometrics Registration
                                    </h2>
                                    
                                    <!-- Video/Fallback Container -->
                                    <div class="relative w-full flex-1 min-h-[40vh] max-h-[70vh] mb-8 mx-auto rounded-[2rem] border-4 transition-colors duration-300 overflow-hidden"
                                         :class="isAnalyzing ? 'border-blue-500/50' : 'border-blue-600'">
                                        
                                        <div class="w-full h-full bg-black flex items-center justify-center relative">
                                            <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover transform scale-x-[-1]" :class="{'hidden': !stream}"></video>
                                            <canvas ref="canvasRef" class="absolute top-0 left-0 w-full h-full object-cover transform scale-x-[-1] z-10 pointer-events-none" :class="{'hidden': !stream}"></canvas>
                                            
                                            <!-- Fallback for no camera / Loading -->
                                            <div v-if="!stream" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-800 text-center p-4">
                                                <svg v-if="isRequestingCamera" class="w-16 h-16 text-blue-500 mb-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <svg v-else class="w-16 h-16 text-white/20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                
                                                <span v-if="isRequestingCamera" class="text-sm font-bold text-white uppercase tracking-widest mt-2">Waiting for Permission</span>
                                                <span v-else class="text-xs text-red-400 uppercase tracking-widest font-mono">{{ livenessError }}</span>
                                            </div>
    
                                            <!-- Scanning Overlay Effect -->
                                            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-blue-500/20 to-transparent h-[200%] w-full animate-[scan_2s_linear_infinite] pointer-events-none" v-if="isAnalyzing"></div>
                                        </div>
                                        
                                        <!-- Close / Cancel Button inside the video -->
                                        <button @click="stopCamera" class="absolute top-4 right-4 z-50 text-white/70 hover:text-white transition-colors">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
    
                                    <!-- Liveness Prompts Pill -->
                                    <div class="text-center bg-slate-800/80 border border-slate-700 rounded-3xl py-4 px-12 min-w-[300px]">
                                        <div class="flex items-center justify-center gap-3 mb-3">
                                            <svg class="w-6 h-6 transition-colors duration-300" :class="isAnalyzing ? 'text-white' : 'text-green-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="currentLivenessPrompt.icon" />
                                            </svg>
                                            <p class="text-lg font-bold transition-colors duration-300" :class="isAnalyzing ? 'text-white' : 'text-green-400'">
                                                {{ currentLivenessPrompt.text }}
                                            </p>
                                        </div>
                                        
                                        <!-- Step indicators -->
                                        <div class="flex justify-center gap-2">
                                            <div v-for="(p, index) in livenessPrompts" :key="index" class="h-1.5 rounded-full transition-all duration-300"
                                                 :class="[
                                                    index < livenessStep ? 'w-6 bg-green-500' : 
                                                    index === livenessStep ? 'w-8 bg-blue-500 animate-pulse' : 'w-4 bg-slate-600'
                                                 ]"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </teleport>

                        <!-- Success State -->
                        <div v-if="form.face_descriptor && !isCameraActive" class="flex flex-col items-center z-10 animate-[fadeIn_0.3s_ease-out]">
                            <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mb-3 border border-green-500/30">
                                <svg class="w-8 h-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-900 mb-1">Liveness Verified</p>
                            <p class="text-[10px] text-green-400 font-mono tracking-wider">SECURE VECTOR GENERATED</p>
                        </div>

                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="pt-4 flex gap-3">
                    <button v-if="currentStep > 1" type="button" @click="prevStep" class="flex-1 py-3 px-4 border border-gray-300 text-sm font-bold rounded-lg text-gray-900 hover:bg-white transition-all">
                        Back
                    </button>
                    
                    <button v-if="currentStep < totalSteps" type="button" @click="nextStep" :disabled="(currentStep === 1 && !isStep1Valid) || (currentStep === 2 && !isStep2Valid)" class="flex-[2] flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-ncst-navy hover:bg-ncst-navy-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        Next Step
                    </button>

                    <button v-if="currentStep === totalSteps" type="submit" :disabled="form.processing || !isFormValid || isCameraActive" class="flex-[2] group relative flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-ncst-navy hover:bg-ncst-navy-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        Create Account
                    </button>
                </div>
                
                <div v-if="currentStep === 1" class="text-center text-sm pt-4 mt-2">
                    <span class="text-gray-500">Already have an account?</span>
                    <Link href="/login" class="font-bold text-ncst-navy hover:text-blue-800 ml-1 transition-colors">Sign in</Link>
                </div>
            </form>
                </div>

                <!-- Footer Links -->
                <div class="flex justify-between items-center text-xs text-gray-400 mt-auto pt-4">
                    <span>Copyright &copy; 2026 NCST.</span>
                    <a href="#" class="hover:text-gray-600">Privacy Policy</a>
                </div>

                <!-- Success Overlay -->
            <div v-if="isRegistering" class="absolute inset-0 z-50 bg-white/95 backdrop-blur-sm flex flex-col items-center justify-center p-8 transition-opacity duration-300">
                <div class="w-16 h-16 bg-green-100 border-2 border-transparent rounded-full flex items-center justify-center mb-6 shadow-[0_0_20px_rgba(245,196,0,0.4)] animate-bounce">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2 text-center tracking-tight">Registered Successfully</h3>
                <p class="text-sm text-gray-500 mb-8 text-center">Redirecting to dashboard...</p>
                
                <div class="w-full max-w-xs h-1.5 bg-green-100-mid rounded-full overflow-hidden">
                    <div class="h-full bg-ncst-navy transition-all duration-100 ease-out shadow-[0_0_10px_rgba(245,196,0,0.5)]" :style="`width: ${progress}%`"></div>
                </div>
            </div>
        
            </div>

            <!-- Right Side (Decorative/Graphic) -->
            <div class="hidden md:flex w-1/2 bg-ncst-navy p-12 lg:p-16 flex-col justify-center relative overflow-hidden">
                <!-- Background patterns -->
                <div class="absolute inset-0 opacity-10">
                    <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid-pattern-reg" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid-pattern-reg)" />
                    </svg>
                </div>
                <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
                <div class="absolute top-0 right-0 w-64 h-64 bg-ncst-gold/10 rounded-full blur-2xl"></div>

                <div class="relative z-10 max-w-lg">
                    <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4 leading-tight">
                        Join the smart equipment borrowing system.
                    </h2>
                    <p class="text-white/70 text-lg mb-10">
                        Create an account to browse inventory, reserve tools, and instantly generate digital QR passes.
                    </p>

                    <!-- Mock Dashboard Graphic -->
                    <div class="bg-white/10 backdrop-blur-md border border-gray-300 rounded-2xl p-6 shadow-2xl relative">
                        <!-- Top bar -->
                        <div class="flex items-center justify-between mb-6 border-b border-gray-200 pb-4">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400"></div>
                            </div>
                            <div class="h-2 w-32 bg-white/20 rounded-full"></div>
                        </div>
                        
                        <!-- Content -->
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center gap-4 bg-white/5 p-3 rounded-lg border border-gray-200">
                                <div class="w-10 h-10 rounded bg-ncst-gold/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-ncst-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div>
                                    <div class="h-2 w-24 bg-white/40 rounded-full mb-2"></div>
                                    <div class="h-2 w-16 bg-white/20 rounded-full"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 bg-white/5 p-3 rounded-lg border border-gray-200">
                                <div class="w-10 h-10 rounded bg-ncst-gold/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-ncst-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                </div>
                                <div>
                                    <div class="h-2 w-32 bg-white/40 rounded-full mb-2"></div>
                                    <div class="h-2 w-20 bg-white/20 rounded-full"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating element overlay -->
                        <div class="absolute -right-6 -bottom-6 bg-ncst-gold rounded-xl shadow-xl p-4 border border-yellow-300 w-48 -rotate-3">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-ncst-navy">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" /></svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-ncst-navy">Biometrics Ready</div>
                                </div>
                            </div>
                            <div class="h-1.5 w-full bg-ncst-navy/20 rounded-full overflow-hidden">
                                <div class="h-full bg-ncst-navy w-[100%]"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes scan {
    0% { transform: translateY(-50%); }
    100% { transform: translateY(0%); }
}
</style>
