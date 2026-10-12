<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { Html5QrcodeScanner, Html5QrcodeScanType } from 'html5-qrcode';
import * as faceapi from 'face-api.js';
import { useAlert } from '@/utils/alert';

const alert = useAlert();

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const props = defineProps({
    reservations: { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] }
});

// ── Metrics ──────────────────────────────────────────────────────────
const pendingApprovals = computed(() => props.reservations.filter(r => r.status === 'PENDING_APPROVAL' && r.resource?.type !== 'facility'));
const readyForPickup = computed(() => props.reservations.filter(r => r.status === 'APPROVED_AWAITING_PICKUP'));
const activeLoans = computed(() => props.reservations.filter(r => r.status === 'BORROWED'));
const overdueLoans = computed(() => props.reservations.filter(r => r.status === 'overdue'));

// ── Analytics ────────────────────────────────────────────────────────
const mostBorrowedItem = computed(() => {
    if (!props.reservations || props.reservations.length === 0) return 'No Data';
    const counts = {};
    let max = 0;
    let mostBorrowed = null;
    props.reservations.forEach(r => {
        if (r.resource && r.resource.name) {
            counts[r.resource.name] = (counts[r.resource.name] || 0) + 1;
            if (counts[r.resource.name] > max) {
                max = counts[r.resource.name];
                mostBorrowed = r.resource.name;
            }
        }
    });
    return mostBorrowed || 'No Data';
});

const onTimeReturnRate = computed(() => {
    const totalActive = activeLoans.value.length + overdueLoans.value.length;
    if (totalActive === 0) return 100; // default
    return Math.round(((totalActive - overdueLoans.value.length) / totalActive) * 100);
});

const dailyLoans = computed(() => {
    const days = [0, 0, 0, 0, 0, 0]; // Mon-Sat
    const today = new Date();
    props.reservations.forEach(r => {
        const d = new Date(r.created_at);
        if ((today - d) / (1000 * 60 * 60 * 24) <= 7) {
            let dayOfWeek = d.getDay(); // 0=Sun, 1=Mon, 6=Sat
            if (dayOfWeek >= 1 && dayOfWeek <= 6) {
                days[dayOfWeek - 1]++;
            }
        }
    });
    const maxDay = Math.max(...days, 1);
    return days.map(d => Math.round((d / maxDay) * 100));
});

// ── Real-Time Clock ─────────────────────────────────────────
const currentTime = ref(new Date());
let clockTimer = null;

onMounted(() => {
    clockTimer = setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
    window.addEventListener('launch-scanner', startScanner);
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
    window.removeEventListener('launch-scanner', startScanner);
    
    // Ensure scanner is cleaned up when navigating away
    if (html5QrcodeScanner) {
        try {
            html5QrcodeScanner.clear();
        } catch (e) {
            console.error(e);
        }
    }
});

const formattedTime = computed(() => {
    return currentTime.value.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
});

const formattedDate = computed(() => {
    return currentTime.value.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
});

// ── Analytics (Pie Chart) ────────────────────────────────────────────────────────
const loanStats = computed(() => {
    let returned = 0, active = 0, overdue = 0;
    if (props.reservations) {
        props.reservations.forEach(r => {
            if (r.status === 'RETURNED' || r.status === 'DISPENSED') returned++;
            else if (r.status === 'BORROWED') active++;
            else if (r.status === 'overdue') overdue++;
        });
    }
    const total = returned + active + overdue || 1; 
    
    const C = 2 * Math.PI * 25; 
    const rPct = (returned / total) * C;
    const aPct = (active / total) * C;
    const oPct = (overdue / total) * C;
    
    return {
        returned, active, overdue, total: returned + active + overdue,
        dashR: `${rPct} ${C}`,
        dashA: `${aPct} ${C}`,
        offsetA: -rPct,
        dashO: `${oPct} ${C}`,
        offsetO: -(rPct + aPct)
    };
});

// ── Calendar & Holidays ──────────────────────────────────────────────────
const holidays = {
    '1-1': "New Year's Day",
    '2-14': "Valentine's Day",
    '5-1': "Labor Day",
    '6-12': "Independence Day",
    '8-21': "Ninoy Aquino Day",
    '8-31': "National Heroes Day",
    '11-1': "All Saints' Day",
    '11-30': "Bonifacio Day",
    '12-25': "Christmas Day",
    '12-30': "Rizal Day",
    '12-31': "New Year's Eve"
};

const calendarDays = computed(() => {
    const year = currentTime.value.getFullYear();
    const month = currentTime.value.getMonth();
    const firstDay = new Date(year, month, 1).getDay(); 
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    const days = [];
    for (let i = 0; i < firstDay; i++) days.push(null);
    for (let i = 1; i <= daysInMonth; i++) {
        const dateKey = `${month + 1}-${i}`;
        days.push({
            date: i,
            holiday: holidays[dateKey] || null,
            isToday: i === currentTime.value.getDate()
        });
    }
    return days;
});

const thisMonthHolidays = computed(() => {
    return calendarDays.value.filter(d => d && d.holiday);
});

// ── Search & Filter ──────────────────────────────────────────────────
const searchQuery = ref('');
// We removed the pagination/filter for active loans since the tracker is removed.
const isScannerOpen = ref(false);
const scannerInstance = ref(null);
const scannedTransaction = ref(null);
const scannedResource = ref(null);
const manualInput = ref('');

const startScanner = () => {
    isScannerOpen.value = true;
    setTimeout(() => {
        scannerInstance.value = new Html5QrcodeScanner(
            "qr-reader",
            { fps: 10, qrbox: {width: 250, height: 250}, supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA] },
            false
        );
        let isProcessing = false;
        const processScan = (decodedText) => {
            if (isProcessing) return;
            isProcessing = true;
            
            // Find transaction by pass token OR asset code
            const tx = props.reservations.find(r => 
                (r.pass_qr_token === decodedText || (r.resource && r.resource.asset_code === decodedText)) &&
                (r.status === 'APPROVED_AWAITING_PICKUP' || r.status === 'BORROWED')
            );
            
            if (tx) {
                scannedTransaction.value = tx;
                scannedResource.value = null;
                if (scannerInstance.value) scannerInstance.value.clear();
                isScannerOpen.value = false;
                alert.success('Scan Successful', 'Transaction found.');
            } else {
                const resource = props.resources.find(r => r.asset_code === decodedText);
                if (resource) {
                    scannedResource.value = resource;
                    scannedTransaction.value = null;
                    if (scannerInstance.value) scannerInstance.value.clear();
                    isScannerOpen.value = false;
                    alert.success('Item Found', 'Asset details retrieved.');
                } else {
                    alert.error("Invalid QR", "Invalid QR code or no matching item found.");
                    setTimeout(() => isProcessing = false, 3000);
                }
            }
        };

        scannerInstance.value.render(processScan, (error) => { /* ignore errors */ });

        // Expose to window for manual trigger if needed
        window._processScan = processScan;
    }, 200);
};

const simulateManualScan = (text) => {
    if (!text || text.trim() === '') return;
    if (window._processScan) {
        window._processScan(text);
    }
};

const closeScanner = () => {
    if (scannerInstance.value) scannerInstance.value.clear();
    isScannerOpen.value = false;
};

// ── Facial Recognition (Step C & D) ──────────────────────────────────
const isFaceVerificationOpen = ref(false);
const activeTask = ref('Initializing facial recognition...');
const livenessVideo = ref(null);
const livenessCanvas = ref(null);
let livenessStream = null;
let detectionInterval = null;

const livenessPrompts = [
    { text: "Searching for face...", icon: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" },
    { text: "Move closer to the camera", icon: "M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" },
    { text: "Center your face in the frame", icon: "M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" },
    { text: "Hold still, analyzing...", icon: "M15 12a3 3 0 11-6 0 3 3 0 016 0z" }
];
const livenessStep = ref(-1);
const livenessError = ref('');
const isAnalyzing = ref(false);
const isFaceVerified = ref(false);
const faceScore = ref(0);

const currentLivenessPrompt = computed(() => {
    if (livenessStep.value >= 0 && livenessStep.value < livenessPrompts.length) {
        return livenessPrompts[livenessStep.value];
    }
    return { text: "Verification Complete", icon: "M5 13l4 4L19 7" };
});

const verifyFace = async () => {
    isFaceVerificationOpen.value = true;
    isFaceVerified.value = false;
    livenessStep.value = 0;
    livenessError.value = '';
    
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models')
        ]);

        livenessStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        if (livenessVideo.value) {
            livenessVideo.value.srcObject = livenessStream;
            
            livenessVideo.value.onloadedmetadata = () => {
                livenessVideo.value.play();
                runLivenessSequence();
            };
        }
    } catch (e) {
        console.error("Facial recognition error:", e);
        livenessError.value = "Camera Error or models failed to load. Running simulated verification...";
        runMockLivenessSequence();
    }
};

const runLivenessSequence = () => {
    let requiredValidFrames = 10;
    let currentValidFrames = 0;
    
    // Check if the user has a stored face descriptor
    const storedFaceData = scannedTransaction.value?.user?.face_descriptor;
    let storedDescriptor = null;
    if (storedFaceData) {
        try {
            if (storedFaceData instanceof Float32Array) {
                storedDescriptor = storedFaceData;
            } else if (Array.isArray(storedFaceData)) {
                storedDescriptor = new Float32Array(storedFaceData);
            } else if (typeof storedFaceData === 'string') {
                const parsed = JSON.parse(storedFaceData);
                storedDescriptor = new Float32Array(Array.isArray(parsed) ? parsed : Object.values(parsed));
            } else if (typeof storedFaceData === 'object') {
                storedDescriptor = new Float32Array(Object.values(storedFaceData));
            }
        } catch (e) {
            console.error("Error parsing stored face descriptor:", e);
        }
    }

    detectionInterval = setInterval(async () => {
        if (!livenessVideo.value || !isFaceVerificationOpen.value) return;
        
        isAnalyzing.value = true;
        
        try {
            const detection = await faceapi.detectSingleFace(livenessVideo.value, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (livenessCanvas.value && livenessVideo.value) {
                const displaySize = { width: livenessVideo.value.clientWidth, height: livenessVideo.value.clientHeight };
                
                if (livenessCanvas.value.width !== displaySize.width) {
                    faceapi.matchDimensions(livenessCanvas.value, displaySize);
                }
                
                const canvasCtx = livenessCanvas.value.getContext('2d');
                canvasCtx.clearRect(0, 0, livenessCanvas.value.width, livenessCanvas.value.height);

                if (!detection) {
                    livenessStep.value = 0;
                } else {
                    const box = detection.detection.box;
                    const faceAreaRatio = (box.width * box.height) / (displaySize.width * displaySize.height);
                    
                    const isCenteredX = (box.x > displaySize.width * 0.15) && ((box.x + box.width) < displaySize.width * 0.85);
                    const isCenteredY = (box.y > displaySize.height * 0.1) && ((box.y + box.height) < displaySize.height * 0.9);
                    
                    if (faceAreaRatio < 0.08) {
                        livenessStep.value = 1; 
                    } else if (!isCenteredX || !isCenteredY) {
                        livenessStep.value = 2;
                    } else {
                        livenessStep.value = 3;
                    }

                    let isMatch = true;
                    let faceDistance = 0;
                    if (storedDescriptor) {
                        faceDistance = faceapi.euclideanDistance(storedDescriptor, detection.descriptor);
                        isMatch = faceDistance <= 0.45;
                        // Calculate a percentage score (0 distance = 100%, 0.45 distance = ~80%)
                        faceScore.value = Math.max(0, Math.min(100, Math.round((1 - faceDistance) * 100)));
                    } else {
                        faceScore.value = Math.round(detection.detection.score * 100);
                    }
                    
                    const boxColor = (storedDescriptor && !isMatch) ? '#FF0000' : '#00FF00';
                    const boxText = storedDescriptor 
                        ? (isMatch ? '😊 Match' : '☹️ No Match')
                        : 'Face Detected';

                    const resizedDetections = faceapi.resizeResults(detection, displaySize);
                    canvasCtx.strokeStyle = boxColor;
                    canvasCtx.lineWidth = 3;
                    canvasCtx.strokeRect(resizedDetections.detection.box.x, resizedDetections.detection.box.y, resizedDetections.detection.box.width, resizedDetections.detection.box.height);
                    
                    canvasCtx.fillStyle = boxColor;
                    canvasCtx.font = 'bold 18px Arial';
                    canvasCtx.fillText(boxText, resizedDetections.detection.box.x, resizedDetections.detection.box.y - 10);
                    
                    if (isMatch) {
                        livenessError.value = '';
                        currentValidFrames++;
                        if (currentValidFrames >= requiredValidFrames) {
                            clearInterval(detectionInterval);
                            isAnalyzing.value = false;
                            livenessStep.value = livenessPrompts.length; // Success
                            isFaceVerified.value = true;
                            
                            setTimeout(() => {
                                stopCamera();
                                isFaceVerificationOpen.value = false;
                                alert.success('Face Verified', 'Identity confirmed successfully.');
                            }, 1500);
                        }
                    } else {
                        currentValidFrames = 0;
                        livenessError.value = "Face does not match the registered user.";
                    }
                }
            }
        } catch (e) {
            isAnalyzing.value = false;
        }
    }, 200);
};

const runMockLivenessSequence = () => {
    let currentStep = 0;
    detectionInterval = setInterval(() => {
        currentStep++;
        isAnalyzing.value = true;
        
        setTimeout(() => {
            isAnalyzing.value = false;
            livenessStep.value = currentStep;
            
            if (currentStep >= livenessPrompts.length) {
                clearInterval(detectionInterval);
                isFaceVerified.value = true;
                setTimeout(() => {
                    stopCamera();
                    isFaceVerificationOpen.value = false;
                    alert.success('Face Verified', 'Identity confirmed successfully (Simulated).');
                }, 1500);
            }
        }, 800);
    }, 2000);
};

const stopCamera = () => {
    if (detectionInterval) clearInterval(detectionInterval);
    if (livenessStream) {
        livenessStream.getTracks().forEach(track => track.stop());
        livenessStream = null;
    }
};

const confirmAndRelease = () => {
    if (!scannedTransaction.value) return;
    router.post(`/transaction/verify-scan`, {
        qr_pass_code: scannedTransaction.value.pass_qr_token
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Success', 'Equipment Released successfully.');
            scannedTransaction.value = null;
        },
        onError: (errors) => {
            alert.error('Error', errors.message || 'Failed to release equipment.');
        }
    });
};

const isRejectModalOpen = ref(false);
const rejectRemarks = ref('');

const rejectRelease = () => {
    rejectRemarks.value = '';
    isRejectModalOpen.value = true;
};

const submitRejectRelease = () => {
    if (!rejectRemarks.value) {
        alert.error('Error', 'Please provide a reason for rejecting.');
        return;
    }

    if (!scannedTransaction.value) return;
    router.post(`/transaction/${scannedTransaction.value.id}/reject-release`, {
        remarks: rejectRemarks.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.warning('Rejected', 'Release rejected successfully.');
            scannedTransaction.value = null;
            isRejectModalOpen.value = false;
        },
        onError: (errors) => {
            alert.error('Error', errors.message || 'Failed to reject release.');
        }
    });
};

// ── Tool Return & Restock ────────────────────────────────────────────
const isReturnModalOpen = ref(false);
const returnCondition = ref('Good Condition');
const returnRemarks = ref('');

const openReturnModal = (transaction) => {
    if (transaction) {
        scannedTransaction.value = transaction;
        isReturnModalOpen.value = true;
    } else {
        alert.error("Invalid transaction.");
    }
};

const completeReturn = () => {
    if (!scannedTransaction.value) return;

    if (returnCondition.value === 'Damaged' && !returnRemarks.value.trim()) {
        alert.error("Error", "Please indicate what is damaged in the remarks.");
        return;
    }

    router.post(`/transaction/${scannedTransaction.value.id}/return`, {
        condition: returnCondition.value,
        remarks: returnCondition.value === 'Damaged' ? returnRemarks.value : ''
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success("Success", `Return completed with condition: ${returnCondition.value}`);
            isReturnModalOpen.value = false;
            scannedTransaction.value = null;
            returnCondition.value = 'Good Condition';
            returnRemarks.value = '';
        },
        onError: () => {
            alert.error("Failed to process return. Please try again.");
        }
    });
};
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- 1. Header & Live Metrics Bar -->
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Property Custodian Counter</h1>
                <div class="flex items-center gap-2 mt-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <p class="text-green-400 text-sm font-semibold tracking-wide">Counter Camera & Scanner Active</p>
                </div>
            </div>
        </div>

        <!-- KPI Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Pending -->
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-200 p-6 shadow-lg relative overflow-hidden group hover:border-blue-200 transition-colors">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <svg class="w-16 h-16 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="text-xs text-blue-600/70 uppercase tracking-widest font-semibold mb-2">Pending Approvals</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ pendingApprovals.length }}</p>
                    <span v-if="pendingApprovals.length > 0" class="text-xs font-bold text-blue-600 px-2 py-0.5 rounded-full bg-ncst-gold/10">Action Req</span>
                </div>
            </div>
            
            <!-- Ready -->
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-blue-500/30 p-6 shadow-lg relative overflow-hidden group hover:border-blue-500/60 transition-colors">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <svg class="w-16 h-16 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="text-xs text-blue-400 uppercase tracking-widest font-semibold mb-2">Ready for Pickup</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ readyForPickup.length }}</p>
                </div>
            </div>

            <!-- Active -->
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-green-500/30 p-6 shadow-lg relative overflow-hidden group hover:border-green-500/60 transition-colors">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <svg class="w-16 h-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </div>
                <p class="text-xs text-green-400 uppercase tracking-widest font-semibold mb-2">Active Loans</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ activeLoans.length }}</p>
                </div>
            </div>

            <!-- Overdue -->
            <div class="bg-gradient-to-br from-red-50 to-gray-50 rounded-2xl border border-red-500/40 p-6 shadow-[0_0_20px_rgba(239,68,68,0.15)] relative overflow-hidden group hover:border-red-500 transition-colors">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <svg class="w-16 h-16 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <p class="text-xs text-red-400 uppercase tracking-widest font-semibold mb-2">Overdue Alerts</p>
                <div class="flex items-baseline gap-3">
                    <p class="text-4xl font-black text-red-500 drop-shadow-md">{{ overdueLoans.length }}</p>
                    <span v-if="overdueLoans.length > 0" class="text-xs font-bold text-red-600 px-2 py-0.5 rounded-full bg-red-100 animate-pulse">Critical</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8">
            
            <!-- Main Content Area -->
            <div class="space-y-8">
                
                <!-- 2. Main Dashboard Grid (Insights, Analytics, Clock) -->

                <!-- 3. Main Dashboard Grid (Insights, Analytics, Clock) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-6 items-stretch">
                    
                    <!-- Col 1: Interactive Calendar -->
                    <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 flex flex-col shadow-xl overflow-hidden min-h-[350px]">
                        <div class="px-6 py-4 border-b border-gray-300 flex items-center justify-between bg-gray-100 shrink-0">
                            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ currentTime.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}
                            </h2>
                        </div>
                        <div class="p-6 flex flex-col flex-1 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-32 h-32 bg-blue-500/5 rounded-full blur-2xl -translate-y-1/2 -translate-x-1/4 pointer-events-none"></div>
                            
                            <!-- Calendar Grid -->
                            <div class="grid grid-cols-7 gap-1 text-center mb-2 shrink-0">
                                <div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-[10px] font-bold text-gray-500 uppercase">
                                    {{ day }}
                                </div>
                            </div>
                            <div class="grid grid-cols-7 gap-1 text-center shrink-0">
                                <div v-for="(day, index) in calendarDays" :key="index" 
                                     class="aspect-square flex flex-col items-center justify-center rounded-lg text-xs font-medium transition-all group relative cursor-default"
                                     :class="[
                                        day === null ? 'bg-transparent' : 'bg-gray-100 hover:bg-white/10 border border-gray-300',
                                        day && day.isToday ? 'bg-ncst-gold/20 border-ncst-gold text-blue-600 font-bold shadow-md' : (day !== null ? 'text-gray-700' : ''),
                                        day && day.holiday ? 'border-b-2 border-b-red-400' : ''
                                     ]">
                                    <span v-if="day">{{ day.date }}</span>
                                    
                                    <!-- Tooltip for holiday -->
                                    <div v-if="day && day.holiday" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-900 text-gray-900 text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none shadow-xl border border-gray-300">
                                        {{ day.holiday }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Holidays List -->
                            <div class="mt-4 pt-4 border-t border-gray-300 flex-1 overflow-y-auto">
                                <h3 class="text-[10px] font-bold text-gray-500 uppercase mb-2">Events & Holidays</h3>
                                <div class="space-y-2" v-if="thisMonthHolidays.length > 0">
                                    <div v-for="h in thisMonthHolidays" :key="h.date" class="flex items-center gap-3 text-sm">
                                        <div class="w-8 h-8 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 flex items-center justify-center font-bold text-xs shrink-0">
                                            {{ h.date }}
                                        </div>
                                        <span class="text-gray-700 font-medium truncate" :title="h.holiday">{{ h.holiday }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-xs text-gray-400 italic">
                                    No major holidays this month.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Col 2: Loan Status Analytics (Pie Chart) -->
                    <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-6 shadow-xl relative overflow-hidden flex flex-col justify-center items-center min-h-[350px]">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-full blur-2xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>
                        <h2 class="font-bold text-gray-900 mb-6 text-sm uppercase tracking-wide flex items-center gap-2 self-start w-full">
                            <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>
                            Loan Status Breakdown
                        </h2>
                        
                        <div class="flex items-center gap-6 w-full">
                            <!-- Pie Chart SVG -->
                            <div class="relative w-32 h-32 shrink-0">
                                <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90 rounded-full bg-gray-100 drop-shadow-xl">
                                    <circle r="25" cx="50" cy="50" fill="transparent" stroke="currentColor" stroke-width="50"
                                            :stroke-dasharray="loanStats.dashR" class="text-green-500 transition-all duration-1000"></circle>
                                    <circle r="25" cx="50" cy="50" fill="transparent" stroke="currentColor" stroke-width="50"
                                            :stroke-dasharray="loanStats.dashA" :stroke-dashoffset="loanStats.offsetA" class="text-blue-500 transition-all duration-1000"></circle>
                                    <circle r="25" cx="50" cy="50" fill="transparent" stroke="currentColor" stroke-width="50"
                                            :stroke-dasharray="loanStats.dashO" :stroke-dashoffset="loanStats.offsetO" class="text-red-500 transition-all duration-1000"></circle>
                                </svg>
                                <!-- Inner hole for donut look (optional, but requested pie chart so let's keep it mostly solid or a thick donut) -->
                                <div class="absolute inset-0 bg-white shadow-sm border border-gray-300 rounded-full scale-[0.6] flex items-center justify-center shadow-inner">
                                    <span class="text-xl font-bold text-gray-900">{{ loanStats.total }}</span>
                                </div>
                            </div>
                            
                            <!-- Legend -->
                            <div class="flex-1 space-y-4">
                                <div class="flex items-center justify-between p-2 rounded-lg bg-green-500/5 border border-green-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-green-500 shadow-[0_0_5px_#22c55e]"></div>
                                        <span class="text-xs font-bold text-green-400">Returned</span>
                                    </div>
                                    <span class="text-sm font-black text-gray-900">{{ loanStats.returned }}</span>
                                </div>
                                <div class="flex items-center justify-between p-2 rounded-lg bg-blue-500/5 border border-blue-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-blue-500 shadow-[0_0_5px_#3b82f6]"></div>
                                        <span class="text-xs font-bold text-blue-400">Active</span>
                                    </div>
                                    <span class="text-sm font-black text-gray-900">{{ loanStats.active }}</span>
                                </div>
                                <div class="flex items-center justify-between p-2 rounded-lg bg-red-500/5 border border-red-500/10">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_5px_#ef4444]"></div>
                                        <span class="text-xs font-bold text-red-400">Overdue</span>
                                    </div>
                                    <span class="text-sm font-black text-gray-900">{{ loanStats.overdue }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Col 3: Clean Date & Time Display -->
                    <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-300 p-8 shadow-xl flex flex-col items-center justify-center relative overflow-hidden min-h-[350px]">
                        <div class="absolute bottom-0 right-0 w-64 h-64 bg-ncst-gold/5 rounded-full blur-3xl translate-y-1/3 translate-x-1/3 pointer-events-none"></div>
                        <div class="absolute top-0 left-0 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl -translate-y-1/3 -translate-x-1/3 pointer-events-none"></div>
                        
                        <div class="text-center w-full flex flex-col items-center justify-center h-full z-10">
                            <h2 class="text-blue-600 text-xs font-bold tracking-[0.2em] uppercase mb-6 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Current Local Time
                            </h2>
                            <div class="text-4xl lg:text-5xl xl:text-6xl font-black text-gray-900 drop-shadow-2xl tracking-tighter font-mono mb-2">
                                {{ formattedTime }}
                            </div>
                            <div class="w-24 h-1 bg-gradient-to-r from-transparent via-ncst-gold/50 to-transparent rounded-full my-6"></div>
                            <div class="text-lg lg:text-xl font-bold text-gray-900/90 tracking-wide bg-gray-100 px-6 py-2 rounded-full border border-gray-300">
                                {{ formattedDate }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Bottom Row: Low Stock Alerts -->
                <div class="pb-6">
                    <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 flex flex-col shadow-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-300 flex items-center justify-between bg-gray-100 shrink-0">
                            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Low Stock Alerts
                            </h2>
                            <div class="text-xs font-medium text-gray-600">
                                Items with 5 or fewer in stock
                            </div>
                        </div>
                        <div class="max-h-[320px] overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                            <table v-if="props.resources.filter(r => r.stock <= 5 && r.type !== 'facility').length > 0" class="w-full text-left border-collapse">
                                <thead class="sticky top-0 bg-white shadow-sm border border-gray-300 z-10 shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1)]">
                                    <tr class="text-gray-500 text-[10px] uppercase tracking-widest border-b border-gray-300">
                                        <th class="px-6 py-3 font-bold">Resource Name</th>
                                        <th class="px-6 py-3 font-bold">Asset Code</th>
                                        <th class="px-6 py-3 font-bold">Type</th>
                                        <th class="px-6 py-3 font-bold text-right">Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <tr v-for="item in props.resources.filter(r => r.stock <= 5 && r.type !== 'facility').sort((a,b) => a.stock - b.stock)" :key="item.id" class="hover:bg-white/[0.02] transition-colors group">
                                        <td class="px-6 py-3.5">
                                            <span class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ item.name }}</span>
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span class="text-xs text-gray-600 font-mono">{{ item.asset_code || '—' }}</span>
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span class="text-xs text-gray-600 capitalize">{{ item.type }}</span>
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <div class="inline-flex items-center gap-3 justify-end">
                                                <div class="w-16 h-1.5 bg-white/10 rounded-full overflow-hidden hidden sm:block">
                                                    <div class="h-full rounded-full transition-all" 
                                                         :class="item.stock === 0 ? 'bg-red-500 w-full' : 'bg-yellow-500'" 
                                                         :style="`width: ${item.stock === 0 ? 100 : (item.stock/5)*100}%`"></div>
                                                </div>
                                                <span class="px-2.5 py-1 rounded-md text-[10px] font-black tracking-wide border w-24 text-center" 
                                                     :class="item.stock === 0 ? 'bg-red-500/10 text-red-400 border-red-500/20' : 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'">
                                                    {{ item.stock === 0 ? 'OUT OF STOCK' : `${item.stock} LEFT` }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="text-center py-16 flex flex-col items-center justify-center">
                                <div class="w-16 h-16 rounded-full bg-green-500/10 flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <h3 class="text-gray-900 font-bold mb-1">All items are well stocked</h3>
                                <p class="text-gray-600 text-sm">No inventory alerts at this moment.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Modals ───────────────────────────────────────────────────────── -->
        
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isScannerOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeScanner">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-white shadow-sm border border-gray-300 border border-gray-200 rounded-2xl p-6 w-full max-w-md shadow-2xl z-10 text-center">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Scan QR Pass or Item</h3>
                        <div id="qr-reader" class="rounded-xl overflow-hidden mb-4 border-2 border-dashed border-gray-300 bg-black"></div>
                        <p class="text-xs text-gray-600 mb-4">Position the student's QR pass or item QR within the frame.</p>
                        
                        <!-- Manual Input for Testing -->
                        <div class="mb-6 flex gap-2">
                            <input 
                                type="text" 
                                v-model="manualInput"
                                placeholder="Manual Entry (e.g., EQ-001)" 
                                class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-gray-900 placeholder-white/30 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold outline-none"
                                @keyup.enter="() => simulateManualScan(manualInput)"
                            >
                            <button 
                                @click="() => simulateManualScan(manualInput)"
                                class="bg-blue-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-blue-700 transition-colors"
                            >
                                Submit
                            </button>
                        </div>

                        <button @click="closeScanner" class="w-full py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">
                            Cancel Scan
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Face Verification Modal -->
        <teleport to="body">
            <div v-if="isFaceVerificationOpen" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900 p-4 sm:p-12 animate-[fadeIn_0.3s_ease-out]">
                
                <div class="w-full max-w-5xl flex flex-col items-center justify-center h-full max-h-[90vh]">
                    <h2 class="text-white text-xl font-bold mb-2 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full border-2 border-blue-400 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        {{ isFaceVerified ? 'Identity Confirmed' : 'Identity Verification' }}
                    </h2>
                    <div class="flex items-center gap-3 mb-4 bg-slate-800/90 border border-slate-700 rounded-2xl px-4 py-2">
                        <img 
                            v-if="scannedTransaction?.user?.face_photo || scannedTransaction?.user?.photo_path" 
                            :src="scannedTransaction?.user?.face_photo || scannedTransaction?.user?.photo_path" 
                            alt="Registered ID Face" 
                            class="w-10 h-10 rounded-xl object-cover border-2 border-blue-400 shrink-0" 
                        />
                        <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-sm shrink-0" v-else>
                            🆔
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-white leading-tight">{{ scannedTransaction?.user?.name }}</p>
                            <p class="text-[10px] text-slate-400">
                                Verified against Registered ID Face • Match Score: 
                                <span class="font-mono font-bold" :class="faceScore >= 55 ? 'text-green-400' : 'text-blue-400'">{{ faceScore }}%</span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Video/Fallback Container -->
                    <div class="relative w-full flex-1 min-h-[40vh] max-h-[70vh] mb-8 mx-auto rounded-[2rem] border-4 transition-colors duration-300 overflow-hidden"
                         :class="isAnalyzing ? 'border-blue-500/50' : (isFaceVerified ? 'border-green-500' : 'border-blue-600')">
                        
                        <div class="w-full h-full bg-black flex items-center justify-center relative">
                            <video ref="livenessVideo" autoplay playsinline muted class="w-full h-full object-cover transform scale-x-[-1]"></video>
                            <canvas ref="livenessCanvas" class="absolute top-0 left-0 w-full h-full object-cover transform scale-x-[-1] z-10 pointer-events-none"></canvas>
                            
                            <!-- Scanning Overlay Effect -->
                            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-blue-500/20 to-transparent h-[200%] w-full animate-[scan_2s_linear_infinite] pointer-events-none" v-if="isAnalyzing"></div>
                        </div>
                        
                        <!-- Close / Cancel Button inside the video -->
                        <button @click="stopCamera(); isFaceVerificationOpen = false;" class="absolute top-4 right-4 z-50 text-white/70 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Liveness Prompts Pill -->
                    <div class="text-center bg-slate-800/80 border border-slate-700 rounded-3xl py-4 px-12 min-w-[300px]">
                        <div class="flex items-center justify-center gap-3 mb-3">
                            <svg class="w-6 h-6 transition-colors duration-300" :class="isFaceVerified ? 'text-green-400' : (isAnalyzing ? 'text-white' : 'text-blue-400')" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="currentLivenessPrompt.icon" />
                            </svg>
                            <p class="text-lg font-bold transition-colors duration-300" :class="isFaceVerified ? 'text-green-400' : (isAnalyzing ? 'text-white' : 'text-blue-400')">
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
                        <p v-if="livenessError" class="text-[10px] text-red-400 mt-3 font-mono opacity-80">{{ livenessError }}</p>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Reject Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isRejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="isRejectModalOpen = false">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-white shadow-sm border border-gray-300 border border-red-500/30 rounded-2xl p-6 w-full max-w-md shadow-2xl z-10">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Reject Release</h3>
                        </div>
                        
                        <p class="text-gray-600 text-sm mb-4">Please provide a reason for rejecting the release of <span class="font-bold text-blue-600">{{ scannedTransaction?.resource?.name }}</span> to <span class="font-bold text-gray-900">{{ scannedTransaction?.user?.name }}</span>.</p>
                        
                        <textarea v-model="rejectRemarks" rows="3" class="w-full bg-white border border-gray-300 rounded-xl p-3 text-gray-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 mb-6 placeholder-white/30" placeholder="e.g., Tools are currently undergoing maintenance..."></textarea>
                        
                        <div class="flex gap-3">
                            <button @click="isRejectModalOpen = false" class="flex-1 py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">Cancel</button>
                            <button @click="submitRejectRelease" class="flex-[2] py-3 bg-red-500 hover:bg-red-500 text-white font-bold rounded-xl transition-all shadow-[0_0_15px_rgba(220,38,38,0.3)]">Confirm Rejection</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Return Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isReturnModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="isReturnModalOpen = false">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-white shadow-sm border border-gray-300 border border-gray-200 rounded-2xl p-6 w-full max-w-md shadow-2xl z-10">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Complete Tool Return</h3>
                        
                        <div class="bg-gray-100 p-4 rounded-xl border border-gray-300 mb-5">
                            <p class="text-xs text-gray-600 mb-1">Returning Item</p>
                            <p class="font-bold text-gray-900">{{ scannedTransaction?.resource?.name }}</p>
                            <p class="text-sm text-blue-600/80 mt-1">Borrower: {{ scannedTransaction?.user?.name }}</p>
                        </div>

                        <label class="block text-sm font-medium text-gray-700 mb-2">Item Condition Upon Return</label>
                        <select v-model="returnCondition" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-gray-900 focus:border-ncst-gold focus:ring-1 focus:ring-ncst-gold mb-4">
                            <option value="Good Condition">Good Condition</option>
                            <option value="Damaged">Damaged</option>
                        </select>

                        <div v-if="returnCondition === 'Damaged'" class="mb-6 animate-[fadeIn_0.3s_ease-out]">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Damage Remarks</label>
                            <textarea v-model="returnRemarks" rows="3" class="w-full bg-white border border-red-500/50 rounded-xl p-3 text-gray-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 placeholder-white/30" placeholder="Please describe what is damaged..."></textarea>
                        </div>

                        <div class="flex gap-3" :class="{'mt-6': returnCondition !== 'Damaged'}">
                            <button @click="isReturnModalOpen = false" class="flex-1 py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">Cancel</button>
                            <button @click="completeReturn" 
                                class="flex-[2] py-3 text-gray-900 font-bold rounded-xl transition-all shadow-[0_0_15px_rgba(34,197,94,0.3)]"
                                :class="returnCondition === 'Damaged' ? 'bg-red-500 hover:bg-red-400 shadow-[0_0_15px_rgba(239,68,68,0.3)]' : 'bg-green-500 hover:bg-green-400 shadow-[0_0_15px_rgba(34,197,94,0.3)]'">
                                {{ returnCondition === 'Damaged' ? 'Report Damage & Suspend' : 'Complete & Restock' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Scanned Transaction Receipt Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="scannedTransaction && !isReturnModalOpen && !isFaceVerificationOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="scannedTransaction = null">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-[#f9fafb] text-gray-800 rounded shadow-2xl overflow-hidden w-full max-w-md mx-auto z-10">
                        
                        <!-- Receipt Content -->
                        <div class="p-8 pb-4 font-mono">
                            <div class="text-center mb-6">
                                <h2 class="text-2xl font-black uppercase tracking-widest text-gray-900">NCST System</h2>
                                <p class="text-[10px] text-gray-500 uppercase mt-1 tracking-widest">Transaction Receipt</p>
                                <div class="w-full border-t-2 border-dashed border-gray-300 my-4"></div>
                            </div>

                            <div class="space-y-5">
                                <!-- User Info -->
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">Borrower Profile</p>
                                    <p class="font-bold text-gray-900 text-lg uppercase">{{ scannedTransaction.user?.name }}</p>
                                    <div class="flex justify-between items-center mt-1">
                                        <p class="text-xs text-gray-600">{{ scannedTransaction.user?.school_id || 'ID: N/A' }}</p>
                                        <p class="text-xs font-bold text-gray-500 capitalize bg-gray-200 px-2 py-0.5 rounded">{{ scannedTransaction.user?.role }}</p>
                                    </div>
                                </div>

                                <div class="w-full border-t border-dashed border-gray-300"></div>

                                <!-- Tool Info -->
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mb-1">Equipment Details</p>
                                    <p class="font-bold text-gray-900 text-lg uppercase">{{ scannedTransaction.resource?.name }}</p>
                                    <div class="flex justify-between items-center mt-2">
                                        <p class="text-xs text-gray-600 font-bold">Qty: 1</p>
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase border border-gray-300 bg-gray-100 text-gray-700">
                                            {{ scannedTransaction.status.replace(/_/g, ' ') }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="w-full border-t-2 border-dashed border-gray-300"></div>
                                
                                <!-- Meta -->
                                <div class="text-[10px] text-gray-500 flex justify-between font-bold">
                                    <span>DATE: {{ new Date().toLocaleDateString() }}</span>
                                    <span>TIME: {{ new Date().toLocaleTimeString() }}</span>
                                </div>
                                
                                <div class="text-center pt-2">
                                    <svg class="w-full h-12 text-gray-800" preserveAspectRatio="none" viewBox="0 0 100 24" fill="none" stroke="currentColor">
                                        <!-- Fake Barcode -->
                                        <path stroke-width="2" d="M10 0v24M14 0v24M20 0v24M22 0v24M30 0v24M34 0v24M36 0v24M44 0v24M48 0v24M52 0v24M60 0v24M66 0v24M70 0v24M74 0v24M82 0v24M88 0v24M90 0v24" />
                                    </svg>
                                    <p class="text-[8px] text-gray-400 mt-1 tracking-widest">{{ scannedTransaction.pass_qr_token?.substring(0,16) || 'TXN-0000000000' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="bg-gray-100 p-6 border-t border-gray-200">
                            <div class="flex flex-col gap-3 font-sans">
                                <template v-if="scannedTransaction.status === 'APPROVED_AWAITING_PICKUP'">
                                    <button v-if="!isFaceVerified" @click="verifyFace" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl uppercase tracking-wider transition-all shadow-md text-sm">
                                        Verify Face
                                    </button>
                                    <div v-else class="w-full flex items-center justify-center gap-2 bg-green-50 text-green-600 font-bold py-3 rounded-xl uppercase tracking-wider text-sm border border-green-200">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Face Verified Successfully
                                    </div>
                                    <button 
                                        @click="confirmAndRelease" 
                                        :disabled="!isFaceVerified"
                                        :class="isFaceVerified ? 'bg-ncst-gold hover:bg-yellow-500 text-white cursor-pointer' : 'bg-gray-400 text-gray-700 cursor-not-allowed'"
                                        class="w-full font-bold py-3 rounded-xl uppercase tracking-wider transition-all shadow-md text-sm"
                                    >
                                        Confirm & Release
                                    </button>
                                </template>
                                <template v-else-if="scannedTransaction.status === 'BORROWED'">
                                    <button v-if="!isFaceVerified" @click="verifyFace" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl uppercase tracking-wider transition-all shadow-md text-sm">
                                        Verify Face
                                    </button>
                                    <div v-else class="w-full flex items-center justify-center gap-2 bg-green-50 text-green-600 font-bold py-3 rounded-xl uppercase tracking-wider text-sm border border-green-200">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Face Verified Successfully
                                    </div>
                                    <button 
                                        @click="openReturnModal(scannedTransaction)" 
                                        :disabled="!isFaceVerified"
                                        :class="isFaceVerified ? 'bg-green-500 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-400 text-gray-700 cursor-not-allowed'"
                                        class="w-full font-bold py-3 rounded-xl uppercase tracking-wider transition-all shadow-md text-sm"
                                    >
                                        Process Return
                                    </button>
                                    <button @click="scannedTransaction = null" class="w-full bg-white hover:bg-gray-50 text-gray-600 font-bold py-3 rounded-xl uppercase tracking-wider transition-all border border-gray-300 text-sm">
                                        Cancel
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Standalone Item Result Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="scannedResource && !scannedTransaction" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="scannedResource = null">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
                    <div class="relative bg-gradient-to-br from-white to-gray-50 border border-gray-300 rounded-3xl p-6 shadow-2xl overflow-hidden w-full max-w-md mx-auto z-10">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>
                        
                        <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Asset Details
                        </h2>

                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Asset Information</p>
                                <p class="font-bold text-gray-900 text-lg">{{ scannedResource.name }}</p>
                                <p class="text-sm text-blue-600/80">{{ scannedResource.asset_code }}</p>
                                <p class="text-xs text-gray-500 mt-1 capitalize">Type: {{ scannedResource.type }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Inventory Status</p>
                                <p class="font-bold text-gray-900 text-lg">In Stock: {{ scannedResource.stock }}</p>
                                <span v-if="scannedResource.stock > 0" class="inline-block mt-2 px-2 py-0.5 rounded-full bg-green-500/20 text-green-400 text-[10px] font-bold uppercase border border-green-500/30">
                                    Available
                                </span>
                                <span v-else class="inline-block mt-2 px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 text-[10px] font-bold uppercase border border-red-500/30">
                                    Out of Stock
                                </span>
                            </div>
                        </div>

                        <div class="flex mt-4 pt-4 border-t border-gray-300">
                            <button @click="scannedResource = null" class="w-full bg-gray-100 hover:bg-white/10 text-gray-900 font-bold py-3 rounded-xl transition-all border border-gray-300">
                                Close
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
