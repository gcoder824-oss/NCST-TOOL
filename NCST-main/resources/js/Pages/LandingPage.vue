<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const scrollToSection = (id) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <Head title="NCST Smart Kiosk" />

    <div class="min-h-screen text-gray-900 font-sans selection:bg-[#DCFA56] selection:text-black overflow-x-hidden relative"
         style="background-image: url('/images/ncst-bg.png'); background-size: cover; background-position: center; background-attachment: fixed;">
        
        <!-- Subtle overlay to make content pop against the photo -->
        <div class="absolute inset-0 bg-white/30 backdrop-blur-[2px] z-0 pointer-events-none fixed"></div>

        <!-- Header -->
        <header class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-200/50 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4">
                <a href="#top" class="flex items-center gap-3 min-w-0">
                    <img src="/ncst-logo.png" alt="NCST Logo" class="w-10 h-10 shrink-0 object-contain">
                    <span class="min-w-0 text-black">
                        <span class="block text-xl font-bold tracking-tight leading-tight">NCST</span>
                    </span>
                </a>

                <nav class="hidden md:flex items-center gap-8 font-medium text-gray-700">
                    <button type="button" @click="scrollToSection('features')" class="hover:text-black transition-colors">Capabilities</button>
                    <button type="button" @click="scrollToSection('workflow')" class="hover:text-black transition-colors">How it Works</button>
                    <button type="button" @click="scrollToSection('security')" class="hover:text-black transition-colors">Security</button>
                </nav>

                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    <Link href="/login" class="text-sm font-bold text-gray-900 bg-white hover:bg-gray-200 px-5 py-2.5 rounded-full transition-colors">
                        Log In
                    </Link>
                    <Link href="/register" class="inline-flex items-center justify-center px-5 py-2.5 bg-ncst-gold hover:bg-yellow-500 text-black text-sm font-bold rounded-full transition-all">
                        Register Account
                    </Link>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <main id="top" class="relative pt-28 pb-12 px-4 sm:px-6 max-w-[1400px] mx-auto z-10">
            <div class="bg-ncst-navy rounded-[3rem] p-8 md:p-12 lg:p-16 flex flex-col lg:flex-row items-center gap-12 lg:gap-16 relative overflow-hidden shadow-2xl">
                <!-- Left side -->
                <div class="w-full lg:w-1/2 relative z-10 text-white">
                    <h1 class="text-4xl sm:text-5xl lg:text-[4rem] font-bold tracking-tight leading-[1.05] mb-6">
                        Equipment Borrowing and Facilities Reservation with QR Code and Facial Recognition for NCST.
                    </h1>

                    <p class="text-lg lg:text-xl text-white/80 leading-relaxed mb-10 max-w-xl">
                        Streamline your tools and equipment borrowing process in seconds. Secure your items online, obtain a dynamic QR Pass, and verify checkouts instantly using live facial recognition.
                    </p>

                    <div class="relative inline-block">
                        <Link href="/register" class="inline-flex items-center justify-center px-8 py-4 bg-ncst-gold text-ncst-navy font-bold rounded-2xl transition-transform hover:scale-105 shadow-xl">
                            Register Account
                        </Link>
                        <!-- Decorative Arrow pointing right -->
                        <svg class="absolute -right-24 top-1/2 w-20 h-20 text-ncst-gold hidden md:block transform rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </div>

                    <div class="mt-16 text-sm font-bold flex items-center gap-2 cursor-pointer text-ncst-gold" @click="scrollToSection('features')">
                        Find Out More &darr;
                    </div>
                </div>

                <!-- Right side -->
                <div class="w-full lg:w-1/2 flex justify-center lg:justify-end relative z-10">
                    <div class="relative w-full max-w-sm aspect-[4/5] rounded-[2.5rem] bg-[#111] overflow-hidden flex flex-col items-center justify-center p-8 shadow-2xl border-[6px] border-black transform lg:-rotate-6 hover:rotate-0 transition-transform duration-500">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent shadow-[0_0_15px_#F5C400] animate-[scan_3s_ease-in-out_infinite]" aria-hidden="true"></div>

                        <svg class="w-full h-full text-gray-800" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" role="img">
                            <path d="M20 40 L20 20 L40 20" stroke="currentColor" stroke-width="2"/>
                            <path d="M160 20 L180 20 L180 40" stroke="currentColor" stroke-width="2"/>
                            <path d="M20 160 L20 180 L40 180" stroke="currentColor" stroke-width="2"/>
                            <path d="M160 180 L180 180 L180 160" stroke="currentColor" stroke-width="2"/>

                            <path d="M70 150 C50 120 50 80 70 50 C90 30 110 30 130 50 C150 80 150 120 130 150 C110 170 90 170 70 150 Z" stroke="#333" stroke-width="1" stroke-dasharray="4 4"/>
                            
                            <path d="M78 62 L100 52 L122 62 M66 88 L58 108 M134 88 L142 108 M85 140 L100 148 L115 140" stroke="#F5C400" stroke-width="0.75"/>

                            <line x1="100" y1="30" x2="100" y2="170" stroke="#fff" stroke-width="0.5" opacity="0.2"/>
                            <line x1="40" y1="100" x2="160" y2="100" stroke="#fff" stroke-width="0.5" opacity="0.2"/>
                            
                            <circle cx="85" cy="85" r="3" fill="#F5C400" class="animate-pulse"/>
                            <circle cx="115" cy="85" r="3" fill="#F5C400" class="animate-pulse" style="animation-delay: 200ms"/>
                            <circle cx="100" cy="115" r="3" fill="#F5C400" class="animate-pulse" style="animation-delay: 400ms"/>
                            <circle cx="100" cy="140" r="3" fill="#F5C400" class="animate-pulse" style="animation-delay: 600ms"/>
                            <circle cx="78" cy="62" r="2" fill="#F5C400" class="animate-pulse" style="animation-delay: 800ms"/>
                            <circle cx="122" cy="62" r="2" fill="#F5C400" class="animate-pulse" style="animation-delay: 1000ms"/>
                        </svg>

                        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 bg-[#222] border border-[#F5C400]/30 px-4 py-2 rounded-xl flex items-center gap-2 shadow-2xl whitespace-nowrap">
                            <span class="w-2 h-2 rounded-full bg-[#F5C400] shadow-[0_0_8px_#F5C400]"></span>
                            <span class="text-[#F5C400] text-xs font-mono font-bold tracking-wider">MATCH: 98.4%</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Features -->
        <section id="features" class="py-20 sm:py-24 scroll-mt-20 relative z-10">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-6">
                <div class="mb-16 inline-block bg-white/80 backdrop-blur-md p-8 rounded-3xl border border-white/50 shadow-xl">
                    <h2 class="text-4xl md:text-5xl font-bold text-black mb-4 tracking-tight">Get the Most Out <br/> of the NCST System</h2>
                    <p class="text-gray-700 max-w-xl text-lg font-medium">
                        An intelligent ecosystem designed to eliminate hardware loss, prevent schedule conflicts, and secure campus assets.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
                    <div class="bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl hover:bg-white hover:border-gray-200 transition-all shadow-xl group">
                        <div class="w-14 h-14 bg-white border border-gray-100 rounded-2xl flex items-center justify-center text-black mb-8 group-hover:scale-110 shadow-sm transition-transform">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Smart Catalog &amp; Real-time Stock</h3>
                        <p class="text-gray-700 font-medium leading-relaxed">
                            Browse real-time equipment availability and stock levels. Automated slot validation prevents over-borrowing and schedule overlaps.
                        </p>
                    </div>

                    <div class="bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl hover:bg-white hover:border-gray-200 transition-all shadow-xl group">
                        <div class="w-14 h-14 bg-white border border-gray-100 rounded-2xl flex items-center justify-center text-black mb-8 group-hover:scale-110 shadow-sm transition-transform">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Tokenized Digital QR Pass</h3>
                        <p class="text-gray-700 font-medium leading-relaxed">
                            Instant generation of secure, time-bound QR passes upon booking approval. Accessible on mobile browsers or printable as physical asset tags.
                        </p>
                    </div>

                    <div class="bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl hover:bg-white hover:border-gray-200 transition-all shadow-xl group sm:col-span-2 md:col-span-1">
                        <div class="w-14 h-14 bg-white border border-gray-100 rounded-2xl flex items-center justify-center text-black mb-8 group-hover:scale-110 shadow-sm transition-transform">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Biometric Verification Kiosk</h3>
                        <p class="text-gray-700 font-medium leading-relaxed">
                            High-precision facial recognition matching live webcam feeds against registered student photos. Eliminates proxy borrowing and identity fraud.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section id="workflow" class="py-20 sm:py-24 scroll-mt-20 relative z-10">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-6">
                <div class="mb-16 inline-block bg-white/80 backdrop-blur-md p-8 rounded-3xl border border-white/50 shadow-xl">
                    <h2 class="text-4xl md:text-5xl font-bold text-black mb-4 tracking-tight">How It Works</h2>
                    <p class="text-gray-700 max-w-xl text-lg font-medium">From registration to check-out in four guided steps.</p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8">
                    <div class="relative pt-6 bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl shadow-xl">
                        <div class="text-8xl font-black text-gray-200/60 absolute top-2 right-4 z-0 select-none" aria-hidden="true">1</div>
                        <div class="relative z-10">
                            <h4 class="text-black font-bold mb-3 text-lg">Register with NCST Credentials</h4>
                            <p class="text-gray-700 font-medium leading-relaxed">
                                Sign up using your validated <span class="font-mono text-black">YYYY-*****</span> school ID and <span class="font-mono text-black">@ncst.edu.ph</span> email.
                            </p>
                        </div>
                    </div>
                    <div class="relative pt-6 bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl shadow-xl">
                        <div class="text-8xl font-black text-gray-200/60 absolute top-2 right-4 z-0 select-none" aria-hidden="true">2</div>
                        <div class="relative z-10">
                            <h4 class="text-black font-bold mb-3 text-lg">Reserve Tools &amp; Equipment</h4>
                            <p class="text-gray-700 font-medium leading-relaxed">
                                Select equipment items or tools, pick your borrowing dates, and get instant stock validation.
                            </p>
                        </div>
                    </div>
                    <div class="relative pt-6 bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl shadow-xl">
                        <div class="text-8xl font-black text-gray-200/60 absolute top-2 right-4 z-0 select-none" aria-hidden="true">3</div>
                        <div class="relative z-10">
                            <h4 class="text-black font-bold mb-3 text-lg">Present Digital Pass</h4>
                            <p class="text-gray-700 font-medium leading-relaxed">
                                Show the dynamic Pass QR Code generated after booking approval at the property desk kiosk.
                            </p>
                        </div>
                    </div>
                    <div class="relative pt-6 bg-white/80 backdrop-blur-md border border-white/50 p-8 rounded-3xl shadow-xl">
                        <div class="text-8xl font-black text-gray-200/60 absolute top-2 right-4 z-0 select-none" aria-hidden="true">4</div>
                        <div class="relative z-10">
                            <h4 class="text-black font-bold mb-3 text-lg">Complete Facial Match</h4>
                            <p class="text-gray-700 font-medium leading-relaxed">
                                Scan your face on the kiosk webcam for an instant biometric check-out confirmation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 px-4 text-center text-gray-600 bg-white/80 backdrop-blur-md border-t border-white/50 mt-12">
            <p class="font-medium">&copy; 2026 National College of Science and Technology. All rights reserved.</p>
        </footer>
    </div>
</template>

<style>
@keyframes scan {
    0% { top: 10%; opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { top: 90%; opacity: 0; }
}
</style>
