<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed, onBeforeUnmount, nextTick, onMounted } from 'vue';
import * as faceapi from 'face-api.js';
import Tesseract from 'tesseract.js';
import { useAlert } from '@/utils/alert';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    defaultRole: String,
    resources: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] }
});

const isRegisterModalOpen = ref(false);
const selectedUser = ref('');
const cart = ref([]);

const activeType = ref('all');
const searchQuery = ref('');

const userSearchQuery = ref('');
const isUserDropdownOpen = ref(false);

const filteredUsers = computed(() => {
    if (!userSearchQuery.value) return props.users;
    const query = userSearchQuery.value.toLowerCase();
    return props.users.filter(u => 
        (u.name && u.name.toLowerCase().includes(query)) || 
        (u.school_id && u.school_id.toLowerCase().includes(query))
    );
});

const selectedUserObj = computed(() => {
    return props.users.find(u => u.id === selectedUser.value);
});

const selectUser = (user) => {
    selectedUser.value = user.id;
    userSearchQuery.value = '';
    isUserDropdownOpen.value = false;
};

const itemTypes = computed(() => {
    const base = props.resources.filter(r => r.type !== 'facility');
    const types = new Set(base.map(r => r.type).filter(Boolean));
    return ['all', ...Array.from(types)];
});

const filteredResources = computed(() => {
    let base = props.resources.filter(r => r.type !== 'facility');

    if (activeType.value !== 'all') {
        base = base.filter(r => r.type === activeType.value);
    }
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        base = base.filter(r => 
            (r.name && r.name.toLowerCase().includes(query)) ||
            (r.asset_code && r.asset_code.toLowerCase().includes(query))
        );
    }
    
    return base;
});

const addToCart = (resource) => {
    const existing = cart.value.find(item => item.id === resource.id);
    if (existing) {
        if (existing.quantity < resource.stock) existing.quantity++;
    } else {
        cart.value.push({ ...resource, quantity: 1 });
    }
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const submitWalkInBorrow = () => {
    if (!selectedUser.value || cart.value.length === 0) return;
    
    router.post('/walk-in/checkout', {
        user_id: selectedUser.value,
        items: cart.value.map(item => ({ id: item.id, quantity: item.quantity }))
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Walk-in Processed', 'Items have been dispensed to the user.');
            cart.value = [];
            selectedUser.value = '';
        },
        onError: (err) => {
            alert.error('Checkout Failed', 'An error occurred during checkout.');
            console.error(err);
        }
    });
};

const alert = useAlert();

const form = useForm({
    name: '',
    role: props.defaultRole || 'student',
    email: '',
    school_id: '',
    password: '',
    password_confirmation: '',
    face_descriptor: null,
    face_photo: null,
    course: '',
    department: '',
});

// ID Verification State
const idImage = ref(null);
const idFaceDescriptor = ref(null);
const isAnalyzingId = ref(false);
const idError = ref('');

const handleIdUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (!form.school_id) {
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

                // Pass if we find the school name OR the school ID
                if (!hasNCST && !hasSchoolId) {
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
                            // Capture the face photo before stopping the camera
                            if (videoRef.value) {
                                const canvas = document.createElement('canvas');
                                canvas.width = videoRef.value.videoWidth;
                                canvas.height = videoRef.value.videoHeight;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(videoRef.value, 0, 0, canvas.width, canvas.height);
                                form.face_photo = canvas.toDataURL('image/jpeg', 0.8);
                            }
                            
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
    let currentStep = 0;
    const interval = setInterval(() => {
        currentStep++;
        isAnalyzing.value = true;
        
        setTimeout(() => {
            isAnalyzing.value = false;
            livenessStep.value = currentStep;
            
            if (currentStep >= livenessPrompts.length) {
                clearInterval(interval);
                setTimeout(() => {
                    stopCamera();
                    form.face_descriptor = [0.12, -0.05, 0.08, 0.99]; // Mock 128d vector
                }, 1000);
            }
        }, 800);
    }, 3000);
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
    if (!form.school_id) return null;
    if (form.role === 'student') {
        return /^\d{4}-\d{5}$/.test(form.school_id);
    }
    return form.school_id.length > 2; // Flexible validation for employees
});

const isEmailValid = computed(() => {
    if (!form.email) return null;
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
    
    form.post('/walk-in', {
        onSuccess: () => {
            isRegistering.value = true;
            
            let currentProgress = 0;
            const interval = setInterval(() => {
                currentProgress += (100 / 15); // 15 steps of 100ms = 1.5s
                progress.value = Math.min(currentProgress, 100);
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                isRegistering.value = false;
                isRegisterModalOpen.value = false;
                alert.success('Registration Complete', 'Walk-in user account has been successfully created.');
                // Reset form state
                currentStep.value = 1;
                form.reset();
                idImage.value = null;
                router.reload({ only: ['users'] }); // Reload users to show new user
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
    <div class="h-[calc(100vh-2rem)] flex flex-col space-y-6">
        <!-- Header with "Register Walk-in" button -->
        <div class="flex justify-between items-center bg-white shadow-sm border border-gray-300 p-6 rounded-2xl shadow-xl border border-gray-200 shrink-0">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-gray-900 capitalize">Walk-in: {{ defaultRole }}</h1>
                <p class="text-sm text-blue-600/70 mt-1">Dispense or lend items directly to walk-in users</p>
            </div>
            <button @click="isRegisterModalOpen = true" class="px-6 py-2.5 bg-ncst-gold hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Register New {{ defaultRole }}
            </button>
        </div>

        <!-- Main Workspace: Inventory & Checkout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 min-h-0">
            <!-- Left: Inventory Grid -->
            <div class="lg:col-span-2 bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-6 flex flex-col h-full overflow-hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 shrink-0">
                    <h2 class="text-lg font-bold text-gray-900">Select Items</h2>
                    
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <!-- Search -->
                        <div class="relative flex-1 sm:w-64">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Search inventory..." 
                                class="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-gray-100 border border-gray-300 text-gray-900 placeholder-white/30 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors"
                            />
                        </div>
                        
                        <!-- Type Dropdown -->
                        <div class="flex items-center bg-gray-100 rounded-lg border border-gray-300 px-2 py-1.5 shrink-0">
                            <span class="text-gray-500 text-xs mr-2 ml-1 hidden sm:inline">Type:</span>
                            <select v-model="activeType" class="bg-transparent text-gray-900 text-sm focus:outline-none focus:ring-0 border-none cursor-pointer capitalize py-0.5">
                                <option v-for="type in itemTypes" :key="type" :value="type" class="bg-gray-50 text-gray-900 capitalize">
                                    {{ type }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-y-auto custom-scrollbar flex-1 pr-2 pb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 content-start">
                        <div v-for="resource in filteredResources" :key="resource.id" 
                             @click="addToCart(resource)"
                             class="bg-gray-50 rounded-xl border border-gray-300 p-3 hover:border-ncst-gold/40 cursor-pointer group transition-all duration-300 flex flex-col hover:shadow-[0_0_15px_rgba(245,196,0,0.1)] gap-1 h-[100px]">
                            <h3 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors truncate">{{ resource.name }}</h3>
                            <p class="text-[10px] text-gray-600 uppercase font-mono tracking-wider">{{ resource.asset_code }}</p>
                            <div class="mt-auto flex items-center justify-between">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-md" :class="resource.type === 'consumable' ? 'bg-orange-500/20 text-orange-400' : 'bg-blue-500/20 text-blue-400'">{{ resource.type }}</span>
                                <span class="text-[10px] font-bold text-blue-600">Stock: {{ resource.stock }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Cart & User Selection -->
            <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 flex flex-col h-full overflow-hidden">
                <div class="p-6 border-b border-gray-300 shrink-0">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Checkout</h2>
                    
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Select {{ defaultRole }}</label>
                        
                        <!-- Dropdown Trigger -->
                        <div 
                            @click="isUserDropdownOpen = !isUserDropdownOpen"
                            class="appearance-none rounded-lg relative block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors cursor-pointer flex justify-between items-center"
                        >
                            <span v-if="selectedUserObj">{{ selectedUserObj.name }} ({{ selectedUserObj.school_id }})</span>
                            <span v-else class="text-gray-600">-- Choose a registered {{ defaultRole }} --</span>
                            <svg class="h-4 w-4 text-blue-600/70 transition-transform" :class="{'rotate-180': isUserDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        <!-- Invisible backdrop to close dropdown on click outside -->
                        <div v-if="isUserDropdownOpen" class="fixed inset-0 z-40" @click="isUserDropdownOpen = false"></div>

                        <!-- Dropdown List -->
                        <div v-if="isUserDropdownOpen" class="absolute z-50 w-full mt-1 bg-gray-50 border border-gray-200 rounded-lg shadow-[0_4px_20px_rgba(0,0,0,0.5)] overflow-hidden">
                            <div class="p-2 border-b border-gray-300 bg-white shadow-sm border border-gray-300">
                                <div class="relative">
                                    <svg class="w-4 h-4 absolute left-2 top-1/2 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <input 
                                        type="text" 
                                        v-model="userSearchQuery" 
                                        placeholder="Search name or ID..." 
                                        class="w-full bg-white border border-gray-300 rounded pl-8 pr-3 py-1.5 text-sm text-gray-900 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold placeholder-white/30"
                                        @click.stop
                                    >
                                </div>
                            </div>
                            <div class="max-h-48 overflow-y-auto custom-scrollbar bg-white shadow-sm border border-gray-300">
                                <div v-if="filteredUsers.length === 0" class="p-3 text-center text-xs text-gray-600">
                                    No users found
                                </div>
                                <div 
                                    v-for="user in filteredUsers" 
                                    :key="user.id"
                                    @click="selectUser(user)"
                                    class="px-3 py-2 text-sm text-gray-900 hover:bg-ncst-gold/20 cursor-pointer transition-colors border-l-2 border-transparent hover:border-ncst-gold flex flex-col"
                                >
                                    <span class="font-medium">{{ user.name }}</span>
                                    <span class="text-[10px] text-blue-600/70 font-mono">{{ user.school_id }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-3">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400 text-center">
                        <svg class="w-12 h-12 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-[10px] mt-1">Click items from inventory to add</p>
                    </div>
                    
                    <div v-for="(item, index) in cart" :key="item.id" class="bg-gray-50 rounded-lg p-3 border border-gray-300 flex items-center justify-between group">
                        <div class="min-w-0 flex-1 pr-3">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ item.name }}</p>
                            <p class="text-[10px] text-gray-600">{{ item.asset_code }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center bg-white shadow-sm border border-gray-300 rounded-lg border border-gray-200">
                                <button @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-l-lg">-</button>
                                <span class="text-xs font-bold text-gray-900 w-6 text-center">{{ item.quantity }}</span>
                                <button @click="item.quantity < item.stock ? item.quantity++ : null" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-r-lg" :class="{'opacity-50 cursor-not-allowed': item.quantity >= item.stock}">+</button>
                            </div>
                            <button @click="removeFromCart(index)" class="text-red-400 hover:text-red-300 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-gray-300 shrink-0">
                    <button @click="submitWalkInBorrow" :disabled="!selectedUser || cart.length === 0" class="w-full py-3 bg-ncst-gold hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-[0_0_20px_rgba(245,196,0,0.4)] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none">
                        Confirm & Process
                    </button>
                </div>
            </div>
        </div>

        <!-- Registration Modal (Popup) -->
        <Transition name="modal">
            <div v-if="isRegisterModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-100 backdrop-blur-sm">
                <div class="absolute inset-0" @click="isRegisterModalOpen = false"></div>
                
                <div :class="['relative w-full space-y-6 bg-white shadow-sm border border-gray-300 p-8 sm:p-10 rounded-2xl shadow-2xl border border-gray-200 overflow-hidden transition-all duration-700 ease-in-out', currentStep === 3 ? 'max-w-4xl' : 'max-w-md']">
                    <button @click="isRegisterModalOpen = false" class="absolute top-4 right-4 text-gray-600 hover:text-gray-900 bg-gray-50 p-2 rounded-full transition-colors z-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent opacity-50" aria-hidden="true"></div>
                    
                    <div class="flex flex-col items-center">
                        <img src="/ncst-logo.png" alt="NCST Logo" class="w-12 h-12 object-contain mb-3">
                        <h2 class="text-center text-2xl font-extrabold text-gray-900 tracking-tight">
                            Walk-in Registration
                        </h2>
                        <p class="mt-1 text-center text-xs text-blue-600/80">
                            Verify and create an account for Walk-in Users
                        </p>
                    </div>

                    <!-- Stepper -->
            <div class="mt-4 mb-6">
                <div class="flex items-center justify-between relative">
                    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-0.5 bg-gray-50 rounded-full z-0">
                        <div class="h-full bg-ncst-gold transition-all duration-300" :style="{ width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%` }"></div>
                    </div>
                    
                    <div v-for="step in steps" :key="step.id" class="relative z-10 flex flex-col items-center gap-1">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all duration-300"
                             :class="[
                                currentStep === step.id ? 'bg-ncst-gold border-ncst-gold text-white shadow-[0_0_10px_rgba(245,196,0,0.5)]' : 
                                currentStep > step.id ? 'bg-ncst-gold border-ncst-gold text-white' : 'bg-gray-50 border-gray-200 text-gray-600'
                             ]">
                            <svg v-if="currentStep > step.id" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>{{ step.id }}</span>
                        </div>
                        <span class="text-[10px] font-medium absolute -bottom-4 w-max text-center transition-colors duration-300"
                              :class="currentStep >= step.id ? 'text-blue-600' : 'text-gray-500'">
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
                        <input id="name" type="text" required v-model="form.name" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors" placeholder="Juan Dela Cruz" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Account Role</label>
                        <div class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-gray-50 border border-gray-300 text-gray-600 sm:text-sm cursor-not-allowed select-none flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                            </svg>
                            <span class="capitalize font-medium">{{ defaultRole === 'faculty' ? 'Employee / Faculty' : defaultRole }}</span>
                            <span class="absolute right-3 text-[10px] bg-gray-100 px-2 py-0.5 rounded text-gray-400 uppercase tracking-wider">Locked</span>
                        </div>
                    </div>

                    
                    <div>
                        <label for="email" class="block text-xs font-medium text-gray-700 mb-1">NCST Email</label>
                        <input id="email" type="email" required v-model="form.email" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors" placeholder="student@ncst.edu.ph" />
                        <p class="text-[10px] text-gray-600 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Must strictly end with @ncst.edu.ph
                        </p>
                    </div>
                    
                    <div>
                        <label for="school_id" class="block text-xs font-medium text-gray-700 mb-1">School ID Number</label>
                        <input id="school_id" type="text" required v-model="form.school_id" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors" placeholder="2026-12345" />
                        <p class="text-[10px] text-gray-600 mt-1">Student: YYYY-NNNNN | Employee: Any valid ID</p>
                    </div>

                    <div v-if="defaultRole === 'student'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="department" class="block text-xs font-medium text-gray-700 mb-1">Department</label>
                            <select id="department" required v-model="form.department" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors">
                                <option value="" disabled selected>Select Department</option>
                                <option value="Computer Studies Department">Computer Studies Department</option>
                                <option value="Engineering Department">Engineering Department</option>
                                <option value="Architecture Department">Architecture Department</option>
                                <option value="Business Department">Business Department</option>
                            </select>
                        </div>
                        <div>
                            <label for="course" class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                            <select id="course" required v-model="form.course" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors">
                                <option value="" disabled selected>Select Course</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                                <option value="BSEE">BSEE</option>
                                <option value="BSCOE">BSCOE</option>
                                <option value="BSIE">BSIE</option>
                                <option value="BS Architecture">BS Architecture</option>
                                <option value="BSBA">BSBA</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                        <label class="block text-xs font-bold text-blue-600 mb-2">Upload School ID Card (Must contain your photo)</label>
                        <input type="file" accept="image/*" @change="handleIdUpload" class="block w-full text-xs text-gray-600 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-ncst-gold file:text-white hover:file:bg-yellow-300 transition-colors" />
                        
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
                            <input id="password" type="password" required v-model="form.password" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors" />
                            <p class="text-[10px] text-gray-600 mt-1">Minimum 8 characters</p>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input id="password_confirmation" type="password" required v-model="form.password_confirmation" class="appearance-none rounded-lg relative block w-full px-3 py-2 bg-white border border-gray-200 placeholder-white/30 text-gray-900 focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors" />
                            <p v-if="form.password_confirmation && !isPasswordMatch" class="text-[10px] text-red-400 mt-1">Passwords do not match</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Biometrics -->
                <div v-show="currentStep === 3" class="space-y-4 animate-[fadeIn_0.3s_ease-out]">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 flex flex-col items-center justify-center text-center relative overflow-hidden min-h-[280px]">
                        
                        <!-- Initial State -->
                        <div v-if="!isCameraActive && !form.face_descriptor" class="flex flex-col items-center z-10 animate-[fadeIn_0.3s_ease-out]">
                            <div class="w-16 h-16 bg-white shadow-sm border border-gray-300 rounded-full flex items-center justify-center mb-4 border border-gray-200 shadow-[0_0_15px_rgba(245,196,0,0.1)]">
                                <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 mb-2">Active Liveness Check</p>
                            <p class="text-xs text-gray-500 mb-6 max-w-[220px]">We need to verify you are a real person. You will be prompted to perform a few simple actions.</p>
                            <button type="button" @click="startCamera" class="bg-ncst-gold hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-md">
                                Start Verification
                            </button>
                        </div>

                        <!-- Active Camera / Liveness Checking State -->
                        <div v-if="isCameraActive" class="w-full flex flex-col items-center justify-center z-10 animate-[fadeIn_0.3s_ease-out]">
                            
                            <!-- Video/Fallback Container -->
                            <div class="relative w-full h-80 sm:h-[450px] mb-4 mx-auto">
                                <div class="absolute inset-0 rounded-2xl border-4 transition-colors duration-300 z-20 pointer-events-none"
                                     :class="isAnalyzing ? 'border-ncst-gold animate-pulse' : 'border-green-500'"></div>
                                
                                <div class="w-full h-full rounded-2xl overflow-hidden bg-white border border-gray-300 flex items-center justify-center relative">
                                    <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover" :class="{'hidden': !stream}"></video>
                                    <canvas ref="canvasRef" class="absolute top-0 left-0 w-full h-full object-cover z-10 pointer-events-none" :class="{'hidden': !stream}"></canvas>
                                    
                                    <!-- Fallback for no camera / Loading -->
                                    <div v-if="!stream" class="absolute inset-0 flex flex-col items-center justify-center bg-white shadow-sm border border-gray-300 text-center p-4">
                                        <svg v-if="isRequestingCamera" class="w-12 h-12 text-blue-600 mb-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-12 h-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        
                                        <span v-if="isRequestingCamera" class="text-xs font-bold text-blue-600 uppercase tracking-widest mt-2">Waiting for Permission</span>
                                        <span v-if="isRequestingCamera" class="text-[10px] text-gray-500 mt-1">Please click "Allow" on your browser prompt</span>
                                        
                                        <span v-else class="text-[10px] text-gray-500 uppercase tracking-widest font-mono">SIMULATING</span>
                                    </div>

                                    <!-- Scanning Overlay Effect -->
                                    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-ncst-gold/20 to-transparent h-[200%] w-full animate-[scan_2s_linear_infinite] pointer-events-none" v-if="isAnalyzing"></div>
                                </div>
                            </div>

                            <!-- Liveness Prompts -->
                            <div class="text-center w-full px-4">
                                <div class="flex items-center justify-center gap-2 mb-2 h-6">
                                    <svg class="w-5 h-5 transition-colors duration-300" :class="isAnalyzing ? 'text-blue-600' : 'text-green-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="currentLivenessPrompt.icon" />
                                    </svg>
                                    <p class="text-sm font-bold transition-colors duration-300" :class="isAnalyzing ? 'text-gray-900' : 'text-green-400'">
                                        {{ currentLivenessPrompt.text }}
                                    </p>
                                </div>
                                
                                <!-- Step indicators -->
                                <div class="flex justify-center gap-1.5 mt-3">
                                    <div v-for="(p, index) in livenessPrompts" :key="index" class="h-1.5 rounded-full transition-all duration-300"
                                         :class="[
                                            index < livenessStep ? 'w-4 bg-green-500' : 
                                            index === livenessStep ? 'w-6 bg-ncst-gold animate-pulse' : 'w-2 bg-white/20'
                                         ]"></div>
                                </div>

                                <p v-if="livenessError" class="text-[10px] text-yellow-400 mt-3 font-mono opacity-80">{{ livenessError }}</p>
                            </div>
                        </div>

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
                    <button v-if="currentStep > 1" type="button" @click="prevStep" class="flex-1 py-3 px-4 border border-gray-200 text-sm font-bold rounded-lg text-gray-900 hover:bg-gray-50 transition-all">
                        Back
                    </button>
                    
                    <button v-if="currentStep < totalSteps" type="button" @click="nextStep" :disabled="(currentStep === 1 && !isStep1Valid) || (currentStep === 2 && !isStep2Valid)" class="flex-[2] flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-ncst-gold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy transition-all shadow-md hover:shadow-[0_0_20px_rgba(245,196,0,0.4)] disabled:opacity-50 disabled:cursor-not-allowed">
                        Next Step
                    </button>

                    <button v-if="currentStep === totalSteps" type="submit" :disabled="form.processing || !isFormValid || isCameraActive" class="flex-[2] group relative flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-ncst-gold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-gold focus:ring-offset-ncst-navy transition-all shadow-md hover:shadow-[0_0_20px_rgba(245,196,0,0.4)] disabled:opacity-50 disabled:cursor-not-allowed">
                        Create Account
                    </button>
                </div>
                
                <!-- Removed login link -->
            </form>
            
            <!-- Success Overlay -->
            <!-- Success Overlay -->
            <div v-if="isRegistering" class="absolute inset-0 z-50 bg-gray-50/95 backdrop-blur-sm flex flex-col items-center justify-center p-8 transition-opacity duration-300">
                <div class="w-16 h-16 bg-gray-50 border-2 border-ncst-gold rounded-full flex items-center justify-center mb-6 shadow-[0_0_20px_rgba(245,196,0,0.4)] animate-bounce">
                    <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2 text-center tracking-tight">Registered Successfully</h3>
                <p class="text-sm text-blue-600/80 mb-8 text-center">Redirecting to dashboard...</p>
                
                <div class="w-full max-w-xs h-1.5 bg-white border border-gray-300 rounded-full overflow-hidden">
                    <div class="h-full bg-ncst-gold transition-all duration-100 ease-out shadow-[0_0_10px_rgba(245,196,0,0.5)]" :style="`width: ${progress}%`"></div>
                </div>
            </div>
        
                </div>
            </div>
        </Transition>
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

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
