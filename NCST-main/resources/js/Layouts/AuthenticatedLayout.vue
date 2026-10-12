<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useAlert } from '@/utils/alert';

const alert = useAlert();
const page = usePage();

const user = computed(() => page.props.auth?.user || { name: 'Guest', role: 'guest' });
const borrowedItems = computed(() => page.props.borrowedItems || []);
const now = ref(new Date());
let ticker = null;

const warned1MinIds = ref(new Set());
const warnedOverdueIds = ref(new Set());
const warnedSuspendedIds = ref(new Set());

// Active borrowed items with dynamic second countdown
const activeBorrowedAlerts = computed(() => {
    return (borrowedItems.value || []).map(item => {
        if (!item.due_date || item.status !== 'BORROWED') return null;
        const diffSeconds = (new Date(item.due_date).getTime() - now.value.getTime()) / 1000;
        return {
            id: item.id,
            name: item.resource?.name || 'Borrowed Item',
            diffSeconds
        };
    }).filter(Boolean);
});

// Determine highest urgency alert state across borrowed items
const criticalBorrowState = computed(() => {
    const list = activeBorrowedAlerts.value;
    if (user.value.is_suspended) {
        return {
            type: 'suspended',
            icon: '🚨',
            title: 'Borrowing Access Locked — Overdue Consequence',
            message: 'Your borrowing and reservation privileges are disabled. You cannot reserve or borrow any items until your overdue equipment is returned.',
            badgeText: 'ACCESS DISABLED',
            badgeClass: 'bg-red-600 text-white border-red-700 animate-pulse'
        };
    }
    if (!list || list.length === 0) return null;

    // Check if any is overdue (<= 0 seconds)
    const suspendedItem = list.find(item => item.diffSeconds <= 0);
    if (suspendedItem) {
        return {
            type: 'suspended',
            icon: '🚨',
            title: `Borrowing Access Locked — "${suspendedItem.name}" Overdue`,
            message: `The 2-minute due date for "${suspendedItem.name}" has expired. All reservation buttons are disabled until this item is returned.`,
            badgeText: 'ACCESS DISABLED',
            badgeClass: 'bg-red-600 text-white border-red-700 animate-pulse'
        };
    }

    // Check if any is within 1 minute (<= 60 seconds)
    const warningItem = list.find(item => item.diffSeconds <= 60 && item.diffSeconds > 0);
    if (warningItem) {
        const remainingSecs = Math.max(0, Math.floor(warningItem.diffSeconds));
        return {
            type: 'warning_1min',
            icon: '⏰',
            title: `1-Minute Return Warning — "${warningItem.name}"`,
            message: `"${warningItem.name}" is due in ${remainingSecs} seconds! Please return the item to avoid immediate suspension.`,
            badgeText: `${remainingSecs}s REMAINING`,
            badgeClass: 'bg-amber-400 text-amber-950 border-amber-500 font-bold'
        };
    }

    return null;
});

// Superadmin Tab Navigation Handling with Shared Reactive State
import { superadminActiveTab, setSuperadminTab } from '@/utils/superadminState';

const isSuperadmin = computed(() => user.value?.role === 'admin');

const handleSuperadminTab = (tab) => {
    isMobileMenuOpen.value = false;
    setSuperadminTab(tab, router, page.url);
};

onMounted(() => {
    if (!page.props.auth?.user || page.props.auth.user.role === 'guest' || sessionStorage.getItem('logged_out') === 'true') {
        window.location.replace('/login');
        return;
    }

    ticker = setInterval(() => {
        now.value = new Date();

        activeBorrowedAlerts.value.forEach(item => {
            // 1 minute left warning
            if (item.diffSeconds <= 60 && item.diffSeconds > 0 && !warned1MinIds.value.has(item.id)) {
                warned1MinIds.value.add(item.id);
                alert.warning(
                    'Due Date Warning (1 Min Left)',
                    `Your borrowed item "${item.name}" is due in less than 1 minute! Please return it soon to avoid suspension.`
                );
            }
            // Overdue (<= 0) -> Immediately lock privileges
            else if (item.diffSeconds <= 0 && !warnedSuspendedIds.value.has(item.id)) {
                warnedSuspendedIds.value.add(item.id);
                if (page.props.auth?.user) {
                    page.props.auth.user.is_suspended = true;
                }
                alert.error(
                    'Borrowing Access Locked',
                    `Due date for "${item.name}" has expired! All reserve buttons are now disabled until you return this item.`
                );
            }
        });
    }, 1000);
});

onUnmounted(() => {
    if (ticker) clearInterval(ticker);
});

const initials = computed(() => {
    return user.value.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const launchScanner = () => {
    if (page.url.startsWith('/dashboard')) {
        window.dispatchEvent(new Event('launch-scanner'));
    } else {
        router.visit('/dashboard', {
            onSuccess: () => {
                setTimeout(() => window.dispatchEvent(new Event('launch-scanner')), 500);
            }
        });
    }
    isMobileMenuOpen.value = false;
};

const isMobileMenuOpen = ref(false);
const isNotificationOpen = ref(false);
const isWalkInDropdownOpen = ref(false);
const isWalkInStudentOpen = ref(false);
const isWalkInFacultyOpen = ref(false);

const isLogoutModalOpen = ref(false);
const isLoggingOut = ref(false);
const logoutProgress = ref(0);

const performLogout = () => {
    isLoggingOut.value = true;
    let currentProgress = 0;
    const interval = setInterval(() => {
        currentProgress += (100 / 15);
        logoutProgress.value = Math.min(currentProgress, 100);
    }, 100);

    setTimeout(() => {
        clearInterval(interval);
        sessionStorage.setItem('logged_out', 'true');
        router.post('/logout', {}, {
            onSuccess: () => {
                window.location.replace('/login');
            },
            onError: () => {
                window.location.replace('/login');
            },
            onFinish: () => {
                isLogoutModalOpen.value = false;
                isLoggingOut.value = false;
                logoutProgress.value = 0;
                window.location.replace('/login');
            }
        });
    }, 1500);
};
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50 text-gray-900">
        <!-- Mobile Header & Hamburger -->
        <div :class="isSuperadmin ? 'bg-slate-900 border-b border-slate-800 text-white' : 'bg-white border-b border-gray-200 text-gray-900'" class="lg:hidden absolute top-0 left-0 right-0 h-16 flex items-center justify-between px-4 z-20">
            <div class="flex items-center gap-3">
                <img src="/ncst-logo.png" alt="NCST Logo" class="w-8 h-8 object-contain shrink-0">
                <div>
                    <span :class="isSuperadmin ? 'text-white' : 'text-gray-900'" class="font-bold text-base tracking-tight leading-tight block">NCST System</span>
                    <span v-if="isSuperadmin" class="text-[10px] font-mono text-amber-400 font-bold uppercase tracking-wider block">AccessVault Root</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="isSuperadmin" class="bg-amber-400 text-slate-950 text-[10px] font-black px-1.5 py-0.5 rounded font-mono">ROOT</span>
                <button @click="isMobileMenuOpen = !isMobileMenuOpen" :class="isSuperadmin ? 'text-slate-300 hover:text-white' : 'text-gray-500 hover:text-blue-600'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile overlay -->
        <div v-if="isMobileMenuOpen" class="lg:hidden fixed inset-0 bg-black/60 z-30" @click="isMobileMenuOpen = false"></div>

        <!-- Sidebar -->
        <aside 
            :class="[
                isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                isSuperadmin 
                    ? 'bg-slate-900 border-r border-slate-800 text-slate-100 shadow-2xl' 
                    : 'bg-white border-r border-gray-200 text-gray-900 shadow-sm'
            ]" 
            class="fixed lg:static lg:translate-x-0 z-40 w-64 h-full flex flex-col shrink-0 transition-transform duration-300 ease-in-out"
        >
            <!-- Sidebar Header Brand -->
            <div 
                :class="isSuperadmin ? 'bg-slate-950/60 border-b border-slate-800' : 'bg-white border-b border-gray-200'" 
                class="h-16 flex items-center justify-between px-5 shrink-0"
            >
                <div class="flex items-center gap-3">
                    <img src="/ncst-logo.png" alt="NCST Logo" class="w-8 h-8 object-contain shrink-0">
                    <div>
                        <span :class="isSuperadmin ? 'text-white' : 'text-gray-900'" class="font-bold text-base tracking-tight leading-tight block">NCST System</span>
                        <span v-if="isSuperadmin" class="text-[10px] font-mono text-amber-400 font-bold uppercase tracking-wider block">AccessVault Root</span>
                    </div>
                </div>
                <span v-if="isSuperadmin" class="bg-amber-400 text-slate-950 text-[10px] font-black px-1.5 py-0.5 rounded font-mono shadow-xs">ROOT</span>
            </div>

            <!-- Sidebar Navigation Links -->
            <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto" @click="isMobileMenuOpen = false">
                
                <!-- ============================================================= -->
                <!-- SUPERADMIN SIDEBAR: MATCHING EXACT RED CIRCLE THEME & TABS     -->
                <!-- ============================================================= -->
                <!-- ============================================================= -->
                <!-- SUPERADMIN SIDEBAR: CLEAN PROFESSIONAL SVG ICONS & INSTANT TABS-->
                <!-- ============================================================= -->
                <template v-if="isSuperadmin">
                    <div class="px-3 pb-2 pt-1">
                        <p class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest">Core Subsystems</p>
                    </div>

                    <!-- 1. Dashboard -->
                    <button 
                        @click="handleSuperadminTab('dashboard')"
                        :class="superadminActiveTab === 'dashboard' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-5a1 1 0 011-1h4a1 1 0 011 1v9a1 1 0 01-1 1h-4a1 1 0 01-1-1v-9z"/>
                        </svg>
                        <span class="truncate">Dashboard</span>
                    </button>

                    <!-- 2. User Roles -->
                    <button 
                        @click="handleSuperadminTab('user-roles')"
                        :class="superadminActiveTab === 'user-roles' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="truncate">User Roles</span>
                    </button>

                    <!-- 3. Biometric AI -->
                    <button 
                        @click="handleSuperadminTab('biometric-ai')"
                        :class="superadminActiveTab === 'biometric-ai' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m0 6v4a2 2 0 002 2h4m6 0h4a2 2 0 002-2v-4m0-6V5a2 2 0 00-2-2h-4M9 10a3 3 0 116 0 3 3 0 01-6 0zm-2 7a5 5 0 0110 0"/>
                        </svg>
                        <span class="truncate">Biometric AI</span>
                    </button>

                    <!-- 4. System Rules -->
                    <button 
                        @click="handleSuperadminTab('system-rules')"
                        :class="superadminActiveTab === 'system-rules' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <span class="truncate">System Rules</span>
                    </button>

                    <!-- 5. Audit Trail -->
                    <button 
                        @click="handleSuperadminTab('audit-trail')"
                        :class="superadminActiveTab === 'audit-trail' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Audit Trail</span>
                    </button>

                    <!-- 6. DB & Backup -->
                    <button 
                        @click="handleSuperadminTab('db-backup')"
                        :class="superadminActiveTab === 'db-backup' && $page.url.startsWith('/superadmin')
                            ? 'bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30 ring-1 ring-indigo-400/40' 
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-mono font-medium transition-all group text-left cursor-pointer"
                    >
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                        </svg>
                        <span class="truncate">DB & Backup</span>
                    </button>

                    <!-- Account Section -->
                    <div class="px-3 pt-6 pb-1">
                        <p class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest">Account</p>
                    </div>
                    <Link 
                        href="/profile" 
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-mono font-medium transition-colors" 
                        :class="$page.url.startsWith('/profile') ? 'bg-slate-800 text-white font-bold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>My Profile</span>
                    </Link>
                    <Link 
                        href="/settings" 
                        class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-mono font-medium transition-colors" 
                        :class="$page.url.startsWith('/settings') ? 'bg-slate-800 text-white font-bold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Settings</span>
                    </Link>
                </template>

                <!-- ============================================================= -->
                <!-- REGULAR ACCOUNTS (STUDENT / FACULTY / STAFF / VISITOR)         -->
                <!-- ============================================================= -->
                <template v-else>
                    <!-- MAIN -->
                    <Link href="/dashboard" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/dashboard') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </Link>

                    <!-- Notification -->
                    <Link 
                        href="/notifications" 
                        class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" 
                        :class="$page.url.startsWith('/notifications') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'"
                    >
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span>Notification</span>
                        </div>
                        <span v-if="$page.props.pendingApprovalsCount > 0" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm animate-pulse">
                            {{ $page.props.pendingApprovalsCount }}
                        </span>
                    </Link>

                    <button v-if="$page.props.auth.user.role === 'staff'" @click="launchScanner" class="w-full mt-2 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-blue-200 bg-blue-50 text-blue-600 text-sm font-bold shadow-sm hover:bg-blue-100 hover:border-blue-300 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01"/>
                        </svg>
                        Launch Scanner
                    </button>

                    <!-- EQUIPMENT & FACILITIES BORROWING/RESERVING -->
                    <template v-if="$page.props.auth.user.role !== 'staff'">
                        <!-- Equipment (Hidden for visitors) -->
                        <template v-if="$page.props.auth.user.role !== 'visitor'">
                            <div class="pt-4 pb-2">
                                <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Equipment Borrowing</p>
                            </div>
                            <Link href="/equipment" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/equipment') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                Equipment
                            </Link>
                            <Link href="/borrowed" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/borrowed') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                Borrowed
                            </Link>
                            <Link href="/borrow-requests" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/borrow-requests') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                Borrow Request
                            </Link>
                            <Link href="/history" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/history') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Borrow History
                            </Link>
                        </template>

                        <!-- Facilities (Hidden for students) -->
                        <template v-if="$page.props.auth.user.role !== 'student'">
                            <div class="pt-4 pb-2">
                                <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Facility Reservation</p>
                            </div>
                            <Link href="/facilities" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/facilities') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                Facilities
                            </Link>
                            <Link href="/reserved" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/reserved') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Reserved
                            </Link>
                            <Link href="/facility-requests" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/facility-requests') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                Facility Request
                            </Link>
                            <Link href="/reserve-history" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/reserve-history') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Reserve History
                            </Link>
                        </template>

                        <!-- QR Passes (Hidden for visitors) -->
                        <template v-if="$page.props.auth.user.role !== 'visitor'">
                            <div class="pt-4 pb-2">
                                <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Passes</p>
                            </div>
                            <Link href="/passes" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/passes') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                QR Passes
                            </Link>
                        </template>
                    </template>
                    
                    <!-- FMO / SUPERIOR MANAGEMENT -->
                    <template v-if="['FMO', 'OSA', 'OCM', 'OSS'].includes($page.props.auth.user.department) || $page.props.auth.user.school_id === 'FMO-Admin'">
                        <div class="pt-4 pb-2">
                            <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ (['OSA', 'OCM', 'OSS'].includes($page.props.auth.user.department) ? $page.props.auth.user.department + ' Management' : 'FMO Management') }}</p>
                        </div>
                        <Link href="/facility-calendar" class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors mb-1" :class="$page.url.startsWith('/facility-calendar') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                Facility Calendar
                            </div>
                        </Link>
                        <Link href="/facility-approvals" class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/facility-approvals') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Facility Approvals
                            </div>
                        </Link>
                    </template>

                    <!-- INVENTORY MANAGEMENT (STAFF) -->
                    <template v-if="$page.props.auth.user.role === 'staff' && !['OSA', 'OCM', 'OSS'].includes($page.props.auth.user.department)">
                        <div class="pt-4 pb-2">
                            <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                        </div>
                        <Link href="/approvals" class="flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/approvals') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Pending Approvals
                            </div>
                            <span v-if="$page.props.pendingApprovalsCount > 0" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg">{{ $page.props.pendingApprovalsCount }}</span>
                        </Link>
                        <Link href="/inventory" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/inventory') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            Inventory
                        </Link>
                        <Link href="/users" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/users') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            User Directory
                        </Link>
                        <Link href="/admin-history" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/admin-history') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Transaction History
                        </Link>
                        
                        <!-- WALK-INS DROPDOWN -->
                        <div class="relative">
                            <button @click="isWalkInDropdownOpen = !isWalkInDropdownOpen" class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/walk-in') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                                <div class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                    Walk-ins
                                </div>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="isWalkInDropdownOpen ? 'rotate-180 text-blue-600' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <div v-show="isWalkInDropdownOpen" class="pl-7 pr-3 py-1 mt-1 space-y-1">
                                <!-- Student -->
                                <Link href="/walk-in/student" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors" :class="$page.url === '/walk-in/student' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="$page.url === '/walk-in/student' ? 'bg-blue-600' : 'bg-gray-300'"></div>
                                    Student
                                </Link>
                                
                                <!-- Faculty / Staff -->
                                <Link href="/walk-in/faculty" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors" :class="$page.url === '/walk-in/faculty' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="$page.url === '/walk-in/faculty' ? 'bg-blue-600' : 'bg-gray-300'"></div>
                                    Faculty / Staff
                                </Link>

                                <!-- Combined Return Item -->
                                <Link href="/walk-in/return" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors" :class="$page.url === '/walk-in/return' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="$page.url === '/walk-in/return' ? 'bg-blue-600' : 'bg-gray-300'"></div>
                                    Return Item
                                </Link>
                            </div>
                        </div>
                    </template>

                    <!-- ACCOUNT -->
                    <div class="pt-4 pb-2">
                        <p class="px-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Account</p>
                    </div>
                    <Link href="/profile" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/profile') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        My Profile
                    </Link>
                    <Link href="/settings" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors" :class="$page.url.startsWith('/settings') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-blue-600'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Settings
                    </Link>
                </template>
            </nav>

            <!-- Sidebar User Profile Footer -->
            <div :class="isSuperadmin ? 'p-4 border-t border-slate-800 bg-slate-950/40' : 'p-4 border-t border-gray-200'" class="shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center min-w-0">
                        <div :class="isSuperadmin ? 'bg-amber-400 text-slate-950 font-black' : 'bg-blue-100 text-blue-700 font-bold'" class="w-8 h-8 rounded-full flex items-center justify-center text-xs shrink-0 shadow-xs">{{ initials }}</div>
                        <div class="ml-3 truncate">
                            <p :class="isSuperadmin ? 'text-slate-100 font-mono font-bold' : 'text-gray-900 font-medium'" class="text-sm truncate">{{ user.name }}</p>
                            <p :class="isSuperadmin ? 'text-amber-400 font-mono text-[11px] font-semibold' : 'text-gray-400 text-xs capitalize'">{{ isSuperadmin ? 'SUPERADMIN' : user.role }}</p>
                        </div>
                    </div>
                    <button @click="isLogoutModalOpen = true" :class="isSuperadmin ? 'text-slate-400 hover:text-red-400 hover:bg-slate-800' : 'text-gray-400 hover:text-blue-600 hover:bg-gray-50'" class="p-2 rounded-md transition shrink-0" title="Logout">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-gray-50 p-6 pt-20 lg:pt-6 sm:p-8 relative">
            
            <!-- Global Alerts -->
            <div class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-full max-w-md flex flex-col gap-2 px-4 pointer-events-none">
                <TransitionGroup name="list">
                    <!-- Success/Error Flash -->
                    <div v-if="$page.props.flash?.success" class="bg-green-500/90 backdrop-blur text-white px-4 py-3 rounded-xl shadow-2xl border border-green-400 flex items-center gap-3 pointer-events-auto">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-sm font-medium">{{ $page.props.flash.success }}</p>
                    </div>
                    <div v-if="$page.props.flash?.error" class="bg-red-500/90 backdrop-blur text-white px-4 py-3 rounded-xl shadow-2xl border border-red-400 flex items-center gap-3 pointer-events-auto">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-sm font-medium">{{ $page.props.flash.error }}</p>
                    </div>
                    <!-- Suspension Notice -->
                    <div v-if="$page.props.suspensionNotice" class="bg-red-500/95 backdrop-blur text-white px-4 py-3 rounded-xl shadow-2xl border border-red-400 flex items-start gap-3 pointer-events-auto">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <p class="text-sm font-bold">{{ $page.props.suspensionNotice }}</p>
                    </div>
                    <!-- Overdue Warnings -->
                    <div v-for="(warning, idx) in ($page.props.overdueWarnings || [])" :key="'warning-'+idx" class="bg-yellow-500/90 backdrop-blur text-white px-4 py-3 rounded-xl shadow-2xl border border-yellow-400 flex items-start gap-3 pointer-events-auto">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-sm font-bold">{{ warning }}</p>
                    </div>
                </TransitionGroup>
            </div>



            <div class="max-w-7xl mx-auto">
                <!-- Persistent Dynamic Due Date Warning and Overdue Lock Banner -->
                <div v-if="criticalBorrowState" class="mb-6 rounded-2xl p-4 sm:p-5 shadow-lg border transition-all animate-fade-in" :class="{
                    'bg-amber-500/15 border-amber-500/40 text-amber-900': criticalBorrowState.type === 'warning_1min',
                    'bg-red-500/15 border-red-500/40 text-red-950': criticalBorrowState.type === 'suspended',
                }">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl shrink-0">{{ criticalBorrowState.icon }}</span>
                            <div>
                                <h4 class="font-extrabold text-sm sm:text-base tracking-tight">{{ criticalBorrowState.title }}</h4>
                                <p class="text-xs sm:text-sm font-medium mt-0.5 opacity-90">{{ criticalBorrowState.message }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="px-3.5 py-1.5 rounded-xl font-mono text-xs font-black shadow-sm border" :class="criticalBorrowState.badgeClass">
                                {{ criticalBorrowState.badgeText }}
                            </span>
                            <Link href="/borrowed" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition bg-white/90 hover:bg-white text-gray-900 border border-gray-300 shadow-sm">
                                View Pass
                            </Link>
                        </div>
                    </div>
                </div>

                <slot />
            </div>
        </main>

        <!-- Logout Confirmation Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isLogoutModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="!isLoggingOut ? isLogoutModalOpen = false : null"></div>
                    <div class="relative bg-gradient-to-b from-white to-gray-50 border border-gray-200 rounded-3xl p-8 w-full max-w-sm shadow-2xl z-10 flex flex-col items-center text-center overflow-hidden">
                        
                        <div v-if="!isLoggingOut">
                            <div class="w-16 h-16 bg-red-500/20 border-2 border-red-500 rounded-full flex items-center justify-center mb-6 mx-auto">
                                <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 mb-2">Log out</h3>
                            <p class="text-sm text-gray-500 mb-8">Are you sure you want to log out of your account?</p>
                            
                            <div class="flex gap-3 w-full">
                                <button @click="isLogoutModalOpen = false" class="flex-1 py-3 bg-gray-100 hover:bg-white/10 text-gray-900 font-bold rounded-xl transition-all border border-gray-300">
                                    Cancel
                                </button>
                                <button @click="performLogout" class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl transition-all shadow-md shadow-red-500/20">
                                    Yes, Log out
                                </button>
                            </div>
                        </div>

                        <!-- Logging out animation state -->
                        <div v-else class="py-4">
                            <div class="w-16 h-16 bg-gray-50 border-2 border-ncst-gold rounded-full flex items-center justify-center mx-auto mb-6 shadow-[0_0_20px_rgba(245,196,0,0.4)] animate-spin border-t-transparent"></div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2 tracking-tight">Logging Out</h3>
                            <p class="text-sm text-blue-600/80 mb-6">See you next time!</p>
                            
                            <div class="w-full max-w-xs h-1.5 bg-gray-50-mid rounded-full overflow-hidden mx-auto">
                                <div class="h-full bg-ncst-gold transition-all duration-100 ease-out shadow-[0_0_10px_rgba(245,196,0,0.5)]" :style="`width: ${logoutProgress}%`"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style>
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateY(-20px); }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
