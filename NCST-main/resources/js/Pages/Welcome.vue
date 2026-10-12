<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- Main outer container with the NCST building background -->
    <div class="min-h-screen relative font-sans flex flex-col items-center p-4 sm:p-8" 
         style="background-image: url('/images/ncst-bg.png'); background-size: cover; background-position: center; background-attachment: fixed;">
        
        <!-- Optional overlay to ensure the background isn't too distracting if desired, but user asked to change white to image -->
        <div class="absolute inset-0 bg-white/30 backdrop-blur-sm z-0"></div>

        <!-- Navigation (Header) -->
        <header class="w-full max-w-7xl relative z-20 flex justify-between items-center py-4 px-2 sm:px-8 bg-white/80 backdrop-blur-md rounded-full shadow-sm mb-6 mt-2">
            
            <!-- Left: Logo -->
            <div class="flex items-center gap-3">
                <!-- Using a placeholder for NCST logo if not available, else a styled text -->
                <div class="w-10 h-10 bg-yellow-400 rounded-full flex items-center justify-center font-bold text-blue-900 border-2 border-blue-900">
                    N
                </div>
                <span class="text-xl font-bold tracking-tight text-slate-900">
                    NCST
                </span>
            </div>
            
            <!-- Middle Nav (Hidden on mobile) -->
            <nav class="hidden md:flex items-center space-x-10 text-sm font-semibold text-slate-700">
                <Link href="#" class="hover:text-blue-900 transition-colors">Capabilities</Link>
                <Link href="#" class="hover:text-blue-900 transition-colors">How it Works</Link>
                <Link href="#" class="hover:text-blue-900 transition-colors">Security</Link>
            </nav>
            
            <!-- Right: Auth Buttons -->
            <div class="flex items-center gap-4">
                <Link href="/login" class="px-5 py-2 rounded-full text-sm font-bold text-slate-700 hover:bg-slate-100 transition-colors">
                    Log In
                </Link>
                <Link href="/register" class="bg-black text-white px-6 py-2 rounded-full text-sm font-bold shadow-md hover:bg-gray-800 transition-colors">
                    Register Account
                </Link>
            </div>
        </header>

        <!-- Main Hero Section (The Dark Blue Rounded Box) -->
        <main class="w-full max-w-7xl relative z-10 flex-1 flex flex-col md:flex-row bg-[#0b132b] rounded-[40px] overflow-hidden shadow-2xl">
            
            <!-- Left Content (Text and Buttons) -->
            <div class="w-full md:w-[55%] p-10 sm:p-16 lg:p-20 flex flex-col justify-center">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-[1.1] tracking-tight mb-6">
                    Equipment Borrowing and Facilities Reservation with QR Code and Facial Recognition for NCST.
                </h1>
                
                <p class="text-slate-300 text-lg mb-10 max-w-xl leading-relaxed">
                    Streamline your tools and equipment borrowing process in seconds. Secure your items online, obtain a dynamic QR Pass, and verify checkouts instantly using live facial recognition.
                </p>
                
                <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                    <div class="flex items-center gap-4">
                        <Link href="/register" class="bg-[#f5c400] text-[#0b132b] px-8 py-4 rounded-xl font-bold hover:bg-yellow-400 transition-colors shadow-lg">
                            Register Account
                        </Link>
                        <!-- Stylized Arrow -->
                        <svg class="w-8 h-8 text-[#f5c400]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </div>
                    
                    <Link href="#more" class="text-[#f5c400] font-bold text-sm hover:underline mt-4 sm:mt-0 flex items-center gap-2">
                        Find Out More
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </Link>
                </div>
            </div>
            
            <!-- Right Content (Scanner Graphic representation) -->
            <div class="w-full md:w-[45%] bg-[#0b132b] relative flex items-center justify-center p-10">
                <!-- Abstract Scanner Graphic mimicking the user's image -->
                <div class="relative w-full max-w-md aspect-[3/4] bg-[#111827] rounded-3xl border-4 border-slate-800 shadow-[0_0_50px_rgba(0,0,0,0.5)] flex flex-col items-center justify-center overflow-hidden transform md:rotate-3">
                    
                    <!-- Scanner Corner Brackets -->
                    <div class="absolute top-8 left-8 w-8 h-8 border-t-2 border-l-2 border-slate-600"></div>
                    <div class="absolute top-8 right-8 w-8 h-8 border-t-2 border-r-2 border-slate-600"></div>
                    <div class="absolute bottom-8 left-8 w-8 h-8 border-b-2 border-l-2 border-slate-600"></div>
                    <div class="absolute bottom-8 right-8 w-8 h-8 border-b-2 border-r-2 border-slate-600"></div>
                    
                    <!-- Stylized Face Dots / Skeleton -->
                    <div class="relative w-40 h-56 flex flex-col items-center justify-center">
                        <svg class="w-full h-full text-yellow-500/80" viewBox="0 0 100 150" fill="none" stroke="currentColor" stroke-width="1.5">
                            <!-- Face outline -->
                            <ellipse cx="50" cy="75" rx="40" ry="60" stroke="currentColor" stroke-dasharray="4 4" class="opacity-30" />
                            <!-- Dots and connecting lines -->
                            <circle cx="30" cy="65" r="3" fill="currentColor" />
                            <circle cx="70" cy="65" r="3" fill="currentColor" />
                            <circle cx="50" cy="85" r="3" fill="currentColor" />
                            <circle cx="50" cy="110" r="3" fill="currentColor" />
                            <path d="M50 40 L30 65 L50 85 L70 65 Z" stroke="currentColor" />
                            <path d="M30 65 L20 80 M70 65 L80 80 M50 85 L50 110" stroke="currentColor" />
                        </svg>
                    </div>

                    <!-- Scanning Line Effect -->
                    <div class="absolute top-0 left-0 w-full h-2 bg-yellow-400 shadow-[0_0_20px_#f5c400] animate-[scan_3s_linear_infinite]"></div>

                    <!-- Match Pill -->
                    <div class="absolute bottom-12 bg-black/60 backdrop-blur-md px-6 py-2 rounded-full border border-yellow-500/30 flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></div>
                        <span class="text-yellow-400 font-mono font-bold text-xs tracking-widest">MATCH: 98.4%</span>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</template>

