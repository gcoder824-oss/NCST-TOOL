<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isLoggingIn = ref(false);
const progress = ref(0);

onMounted(() => {
    // If the account was logged out, prevent browser back button from navigating back to protected session pages
    if (sessionStorage.getItem('logged_out') === 'true') {
        window.history.pushState(null, '', window.location.href);
        window.onpopstate = () => {
            window.history.pushState(null, '', window.location.href);
        };
    }
});

const submit = () => {
    form.post('/login', {
        onSuccess: () => {
            sessionStorage.removeItem('logged_out');
            window.onpopstate = null;
            isLoggingIn.value = true;
            
            let currentProgress = 0;
            const interval = setInterval(() => {
                currentProgress += (100 / 15); // 15 steps of 100ms = 1.5s
                progress.value = Math.min(currentProgress, 100);
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                router.visit('/dashboard');
            }, 1500);
        }
    });
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="max-w-[1200px] w-full bg-white rounded-3xl shadow-2xl flex flex-col md:flex-row overflow-hidden border border-gray-100 min-h-[700px]">
            
            <!-- Left Side (Form) -->
            <div class="w-full md:w-1/2 p-8 sm:p-12 lg:p-16 flex flex-col justify-between bg-white relative">
                
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <img src="/ncst-logo.png" alt="NCST Logo" class="w-8 h-8 object-contain">
                    <span class="text-xl font-bold text-gray-900 tracking-tight">NCST</span>
                </div>
                
                <!-- Form Container -->
                <div class="max-w-md w-full mx-auto flex-1 flex flex-col justify-center my-8">
                    <div class="text-center mb-10">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                        <p class="text-gray-500 text-sm">Enter your email and password to access your account.</p>
                    </div>
                    
                    <div v-if="$page.props.flash?.success" class="mb-6 bg-green-50 text-green-600 px-4 py-3 rounded-xl text-sm border border-green-100">
                        {{ $page.props.flash.success }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                            <input id="email" name="email" type="email" autocomplete="email" required v-model="form.email" class="appearance-none rounded-xl relative block w-full px-4 py-3 bg-white border border-gray-200 placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-2 focus:ring-ncst-navy focus:border-ncst-navy sm:text-sm transition-all" placeholder="student@ncst.edu.ph" />
                            <div v-if="form.errors.email" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.email }}</div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" autocomplete="current-password" required v-model="form.password" class="appearance-none rounded-xl relative block w-full px-4 py-3 bg-white border border-gray-200 placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-2 focus:ring-ncst-navy focus:border-ncst-navy sm:text-sm transition-all pr-10" placeholder="••••••••" />
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember-me" type="checkbox" v-model="form.remember" class="h-4 w-4 text-ncst-navy focus:ring-ncst-navy border-gray-300 rounded cursor-pointer transition-colors" />
                                <label for="remember-me" class="ml-2 block text-sm text-gray-600 cursor-pointer select-none">
                                    Remember Me
                                </label>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" :disabled="form.processing" class="w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-ncst-navy hover:bg-ncst-navy-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ncst-navy transition-all shadow-md disabled:opacity-70">
                                Log In
                            </button>
                        </div>
                    </form>


                    
                    <div class="mt-8 text-center text-sm">
                        <span class="text-gray-600">Don't Have An Account?</span>
                        <Link href="/register" class="font-bold text-ncst-navy hover:text-blue-800 ml-1">Register Now.</Link>
                    </div>
                </div>

                <!-- Footer Links -->
                <div class="flex justify-between items-center text-xs text-gray-400 mt-auto pt-4">
                    <span>Copyright &copy; 2026 NCST.</span>
                    <a href="#" class="hover:text-gray-600">Privacy Policy</a>
                </div>
            </div>

            <!-- Right Side (Decorative/Graphic) -->
            <div class="hidden md:flex w-1/2 bg-ncst-navy p-12 lg:p-16 flex-col items-center justify-center relative overflow-hidden text-center">
                <!-- Background patterns -->
                <div class="absolute inset-0 opacity-10">
                    <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid-pattern" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid-pattern)" />
                    </svg>
                </div>
                <div class="absolute -top-24 -right-24 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 right-0 w-64 h-64 bg-ncst-gold/10 rounded-full blur-2xl"></div>

                <div class="relative z-10 flex flex-col items-center max-w-sm w-full">
                    <!-- NCST Logo -->
                    <img src="/ncst-logo.png" alt="NCST Logo" class="w-56 h-56 object-contain mb-8 transform transition-transform hover:scale-105 drop-shadow-2xl">
                    
                    <h2 class="text-2xl font-bold text-white mb-3 leading-tight">
                        Equipment & Facilities
                    </h2>
                    <p class="text-gray-300 text-[15px] leading-relaxed">
                        Borrow tools and reserve spaces securely with QR codes and facial recognition.
                    </p>
                </div>
            </div>
            
            <!-- Success Overlay inside the form container -->
            <div v-if="isLoggingIn" class="absolute inset-0 z-50 bg-white/95 backdrop-blur-sm flex flex-col items-center justify-center p-8 transition-opacity duration-300">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6 shadow-lg">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2 text-center tracking-tight">Login Successful</h3>
                <p class="text-sm text-gray-500 mb-8 text-center">Loading dashboard...</p>
                
                <div class="w-full max-w-xs h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-ncst-navy transition-all duration-100 ease-out" :style="`width: ${progress}%`"></div>
                </div>
            </div>
            
        </div>
    </div>
</template>
