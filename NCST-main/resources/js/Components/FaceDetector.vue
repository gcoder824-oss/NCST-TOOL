<template>
  <div class="flex flex-col items-center justify-center p-4">
    <h2 class="text-2xl font-bold mb-4">Face Recognition (MediaPipe)</h2>
    <div class="relative inline-block">
      <!-- Video Element -->
      <video
        ref="videoElement"
        class="rounded-lg shadow-lg"
        autoplay
        playsinline
        muted
        style="width: 640px; height: 480px; object-fit: cover;"
      ></video>
      <!-- Canvas Overlay for Bounding Boxes -->
      <canvas
        ref="canvasElement"
        class="absolute top-0 left-0"
        style="width: 640px; height: 480px;"
        width="640"
        height="480"
      ></canvas>
    </div>
    <div class="mt-4 text-gray-600">
      <p v-if="!isModelLoaded">Loading Face Detection Model...</p>
      <p v-else-if="!isCameraReady">Starting Camera...</p>
      <p v-else>Ready! Detecting faces...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { FaceDetector, FilesetResolver } from '@mediapipe/tasks-vision';

const videoElement = ref(null);
const canvasElement = ref(null);
const isModelLoaded = ref(false);
const isCameraReady = ref(false);

let faceDetector = null;
let animationFrameId = null;

// Initialize MediaPipe Face Detector
const initializeFaceDetector = async () => {
  try {
    const vision = await FilesetResolver.forVisionTasks(
      // using the CDN for the webassembly files
      'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm'
    );
    
    faceDetector = await FaceDetector.createFromOptions(vision, {
      baseOptions: {
        modelAssetPath: `https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite`,
        delegate: 'GPU',
      },
      runningMode: 'VIDEO',
    });
    
    isModelLoaded.value = true;
    startCamera();
  } catch (error) {
    console.error("Error initializing Face Detector:", error);
  }
};

// Start the webcam
const startCamera = async () => {
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: true,
      });
      if (videoElement.value) {
        videoElement.value.srcObject = stream;
        videoElement.value.addEventListener('loadeddata', predictWebcam);
        isCameraReady.value = true;
      }
    } catch (error) {
      console.error("Error accessing webcam:", error);
    }
  }
};

// Continuous detection loop
let lastVideoTime = -1;
const predictWebcam = () => {
  if (!faceDetector || !videoElement.value || !canvasElement.value) return;

  const canvasCtx = canvasElement.value.getContext('2d');
  
  // Calculate current time
  let startTimeMs = performance.now();
  
  if (lastVideoTime !== videoElement.value.currentTime) {
    lastVideoTime = videoElement.value.currentTime;
    
    // Perform face detection
    const detections = faceDetector.detectForVideo(videoElement.value, startTimeMs);
    
    // Clear the canvas
    canvasCtx.clearRect(0, 0, canvasElement.value.width, canvasElement.value.height);
    
    // Draw bounding boxes
    if (detections.detections && detections.detections.length > 0) {
      for (const detection of detections.detections) {
        // MediaPipe returns coordinates relative to the video size
        // We scale them to our canvas size (which matches the video display size)
        
        // We assume 640x480 for both video and canvas, so scaling isn't strictly necessary 
        // if the native video is 640x480, but if it differs, we need relative ratios.
        // For simplicity, tasks-vision returns boundingBox already scaled in pixels based on the input element,
        // but if there are mismatch issues we would handle it here.
        const box = detection.boundingBox;
        
        canvasCtx.strokeStyle = '#00FF00';
        canvasCtx.lineWidth = 3;
        canvasCtx.strokeRect(
          box.originX,
          box.originY,
          box.width,
          box.height
        );
        
        // Optionally draw keypoints (eyes, nose, mouth)
        if (detection.keypoints) {
          canvasCtx.fillStyle = '#FF0000';
          for (const keypoint of detection.keypoints) {
            canvasCtx.beginPath();
            canvasCtx.arc(keypoint.x * canvasElement.value.width, keypoint.y * canvasElement.value.height, 3, 0, 2 * Math.PI);
            canvasCtx.fill();
          }
        }
      }
    }
  }

  // Call this function again for the next frame
  animationFrameId = requestAnimationFrame(predictWebcam);
};

onMounted(() => {
  initializeFaceDetector();
});

onBeforeUnmount(() => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
  }
  if (videoElement.value && videoElement.value.srcObject) {
    videoElement.value.srcObject.getTracks().forEach(track => track.stop());
  }
});
</script>
