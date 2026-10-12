<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QRCodeVue from 'qrcode.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const props = defineProps({
    reservations: { type: Array, default: () => [] }
});

// ── Qr modal references removed for visitor ────────────────────────────────

const printForm = (res) => {
    window.open(`/transactions/${res.id}/print`, '_blank');
};

// ── Quick Scan QR panel ───────────────────────────────────────────────────
const showIdQr = ref(false);

const now = ref(new Date());
let ticker;
onMounted(()  => { ticker = setInterval(() => now.value = new Date(), 1000); });
onUnmounted(() => clearInterval(ticker));

// ── Reservations ──────────────────────────────────────────────────────────
const activeReservations  = computed(() => props.reservations.filter(r => ['APPROVED_AWAITING_PICKUP', 'BORROWED', 'ACTIVE', 'APPROVED'].includes(r.status)));
const pendingReservations = computed(() => props.reservations.filter(r => r.status === 'PENDING_APPROVAL'));
const pastReservations    = computed(() => props.reservations.filter(r => ['RETURNED','REJECTED','DISPENSED'].includes(r.status)));

// ── Notifications ─────────────────────────────────────────────────────────
const notifications = computed(() => {
    let notifs = [];
    let id = 1;

    // 1. Suspension Check
    if (user.value.is_suspended) {
        const hasDamaged = props.reservations.some(r => r.status === 'RETURNED' && r.remarks?.toLowerCase().includes('damaged'));
        if (hasDamaged) {
            notifs.push({ id: id++, type: 'danger', icon: '🚨', message: `ACCOUNT SUSPENDED: You returned a damaged item. Please coordinate with the admin to resolve this, otherwise your school credentials/documents will be put on hold.`, time: 'Suspended', read: false });
        } else {
            notifs.push({ id: id++, type: 'danger', icon: '🚨', message: `ACCOUNT SUSPENDED: You have an overdue item. Please return it immediately.`, time: 'Suspended', read: false });
        }
    }

    // Borrowed items and overdue checks
    props.reservations.filter(r => r.status === 'BORROWED').forEach(r => {
        if (r.due_date) {
            const due = new Date(r.due_date);
            const diff = due - now.value;
            const mins = diff / 60000;
            const secs = diff / 1000;
            
            if (diff <= 0) {
                notifs.push({ id: id++, type: 'danger', icon: '🚨', message: `Your account is suspended. ${r.resource?.name} is overdue!`, time: 'Suspended', read: false });
            } else if (secs <= 60) {
                notifs.push({ id: id++, type: 'warning', icon: '⏰', message: `Warning: ${r.resource?.name} is due in ${Math.floor(secs)} seconds!`, time: 'Soon', read: false });
            } else {
                notifs.push({ id: id++, type: 'info', icon: '📦', message: `You currently have ${r.resource?.name} borrowed.`, time: 'Active', read: false });
            }
        } else {
            notifs.push({ id: id++, type: 'info', icon: '📦', message: `You currently have ${r.resource?.name} borrowed.`, time: 'Active', read: false });
        }
    });

    // Approved requests
    props.reservations.filter(r => r.status === 'APPROVED_AWAITING_PICKUP' || r.status === 'ACTIVE' || r.status === 'APPROVED').forEach(r => {
        notifs.push({ id: id++, type: 'approval', icon: '✅', message: `Your request for ${r.resource?.name} has been approved.`, time: 'Just now', read: false });
    });

    // Pending
    props.reservations.filter(r => r.status === 'PENDING_APPROVAL').forEach(r => {
        notifs.push({ id: id++, type: 'reservation', icon: '⏳', message: `Your request for ${r.resource?.name} is pending approval.`, time: 'In progress', read: true });
    });

    // Rejected
    props.reservations.filter(r => r.status === 'DECLINED' || r.status === 'REJECTED').forEach(r => {
        let reason = r.remarks ? ` Reason: ${r.remarks}` : '';
        notifs.push({ id: id++, type: 'rejection', icon: '❌', message: `Request for ${r.resource?.name} was rejected.${reason}`, time: 'Past', read: true });
    });

    // Fallback if no notifications
    if (notifs.length === 0) {
        notifs.push({ id: 99, type: 'info', icon: 'ℹ️', message: 'Welcome to the NCST Facility Reservation system.', time: 'System', read: true });
    }

    // Sort by unread and importance
    return notifs.sort((a, b) => {
        if (a.type === 'danger') return -1;
        if (b.type === 'danger') return 1;
        if (a.type === 'warning' && b.type !== 'warning') return -1;
        if (b.type === 'warning' && a.type !== 'warning') return 1;
        return (a.read === b.read) ? 0 : a.read ? 1 : -1;
    });
});
const unreadCount = computed(() => notifications.value.filter(n => !n.read).length);
const markAllRead = () => { /* In a real app, this would hit an API */ };

// ── Overview counts ───────────────────────────────────────────────────────
const activeLoans      = computed(() => activeReservations.value.length);
const upcomingBookings = computed(() => pendingReservations.value.length);
const unpaidFines      = ref(0); // would come from backend

// ── Countdown timer ───────────────────────────────────────────────────────
function countdown(endTime) {
    const diff = new Date(endTime) - now.value;
    if (diff <= 0) {
        return 'Suspended (Overdue)';
    }
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return `${m}m ${s < 10 ? '0' : ''}${s}s`;
}

function getProgress(res) {
    if (res.status !== 'BORROWED' || !res.due_date) return 0;
    const due = new Date(res.due_date);
    const borrowed = new Date(res.borrowed_at || (Date.now() - 120000));
    const total = due - borrowed;
    const elapsed = now.value - borrowed;
    if (elapsed < 0) return 0;
    if (elapsed > total) return 100;
    return (elapsed / total) * 100;
}

function getUrgencyClass(res) {
    if (res.status !== 'BORROWED' || !res.due_date) return 'text-gray-900';
    const diffSecs = (new Date(res.due_date) - now.value) / 1000;
    if (diffSecs <= 0) return 'text-red-600 font-black animate-pulse';
    if (diffSecs <= 60) return 'text-amber-500 font-bold animate-pulse';
    return 'text-green-600 font-semibold';
}

// ── Support ticket ─────────────────────────────────────────────────────────
const showTicketForm = ref(false);
const ticket = ref({ subject: '', category: 'equipment', description: '' });
const submitTicket = () => { showTicketForm.value = false; ticket.value = { subject: '', category: 'equipment', description: '' }; };

// ── Campus Policies ────────────────────────────────────────────────────────
const policies = [
    { title: 'Facility Reservation Limits', body: 'Users may reserve one facility at a time. Maximum reservation period is 8 hours. Extensions must be requested 1 hour before the end time.' },
    { title: 'Late/Cancellation Penalties', body: 'Repeated cancellations without prior notice (3+) will result in a 7-day reservation suspension.' },
    { title: 'Care & Maintenance', body: 'Return the facility in the exact condition it was issued. Turn off all lights and appliances before leaving.' },
];
const openPolicy = ref(null);
</script>

<template>
    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- ── Header ─────────────────────────────────────── -->
        <div class="flex items-start justify-between flex-wrap gap-4 relative">
            <div class="absolute -top-10 -left-10 w-40 h-40 bg-ncst-gold/20 rounded-full blur-[80px] pointer-events-none"></div>
            <div class="relative z-10">
                <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">
                    Welcome back, <span class="text-transparent bg-clip-text bg-gradient-to-r from-ncst-gold to-yellow-200">{{ user.name?.split(' ')[0] }}</span>!
                </h1>
                <p class="text-gray-500 text-sm mt-2 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    {{ user.school_id }} &bull; {{ user.role }}
                </p>
            </div>
        </div>

        <!-- ── Analytics & Overview ──────────────────────────────── -->
        <section>
            <h2 class="text-xs font-bold text-blue-600/80 uppercase tracking-widest mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Dashboard Analytics
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- Active Reservations -->
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-green-500/30 p-6 shadow-lg relative overflow-hidden group hover:border-green-500/60 transition-colors">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <svg class="w-16 h-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <p class="text-xs text-green-400 uppercase tracking-widest font-semibold mb-2">Active Reservations</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ activeReservations.length }}</p>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-200 p-6 shadow-lg relative overflow-hidden group hover:border-blue-200 transition-colors">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <svg class="w-16 h-16 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <p class="text-xs text-blue-600/70 uppercase tracking-widest font-semibold mb-2">Pending Requests</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ pendingReservations.length }}</p>
                    </div>
                </div>

                <!-- Total History -->
                <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-blue-500/30 p-6 shadow-lg relative overflow-hidden group hover:border-blue-500/60 transition-colors">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <svg class="w-16 h-16 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-xs text-blue-400 uppercase tracking-widest font-semibold mb-2">Total Lifetime Reservations</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-4xl font-black text-gray-900 drop-shadow-md">{{ pastReservations.length + activeReservations.length }}</p>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="bg-gradient-to-br rounded-2xl border p-6 shadow-lg relative overflow-hidden group transition-colors"
                     :class="user.is_suspended ? 'from-red-50 to-gray-50 border-red-500/40 shadow-[0_0_20px_rgba(239,68,68,0.15)] hover:border-red-500' : 'from-white to-gray-50 border-emerald-500/30 hover:border-emerald-500/60'">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <svg v-if="user.is_suspended" class="w-16 h-16 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <svg v-else class="w-16 h-16 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-xs uppercase tracking-widest font-semibold mb-2"
                       :class="user.is_suspended ? 'text-red-400' : 'text-emerald-400'">Account Status</p>
                    <div class="flex items-baseline gap-3">
                        <p class="text-2xl font-black drop-shadow-md" :class="user.is_suspended ? 'text-red-500' : 'text-gray-900'">{{ user.is_suspended ? 'Suspended' : 'Good Standing' }}</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ── Notifications ───────────────────────────────── -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                    Notifications
                    <span v-if="unreadCount > 0" class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ unreadCount }}</span>
                </h2>
                <button @click="markAllRead" class="text-xs text-gray-400 hover:text-blue-600 transition-colors">Mark all read</button>
            </div>
            <div class="space-y-2">
                <div v-for="notif in notifications" :key="notif.id"
                    class="flex items-start gap-3 px-4 py-3 rounded-xl border transition-all"
                    :class="notif.read ? 'bg-white shadow-sm border border-gray-300 border-gray-300 opacity-60' : 'bg-white shadow-sm border border-gray-300 border-gray-200 shadow-[0_0_12px_rgba(245,196,0,0.05)]'">
                    <span class="text-lg mt-0.5 shrink-0">{{ notif.icon }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900/90">{{ notif.message }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ notif.time }}</p>
                    </div>
                    <div v-if="!notif.read" class="w-2 h-2 rounded-full bg-ncst-gold mt-1.5 shrink-0 animate-pulse"></div>
                </div>
            </div>
        </section>

        <!-- ── Active Reservations ──────────────────── -->
        <section>
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Active Reservations</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="res in activeReservations" :key="res.id"
                    class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 p-5 relative overflow-hidden hover:border-ncst-gold/40 transition-all">
                    <div class="absolute top-0 left-0 w-full h-0.5"
                        :class="res.status === 'overdue' ? 'bg-red-500' : 'bg-green-500'"></div>
                    <div class="flex justify-between items-start gap-4 mb-3">
                        <h3 class="font-bold text-gray-900 text-sm truncate flex-1" :title="res.resource?.name">{{ res.resource?.name }}</h3>
                        <span class="text-[9px] whitespace-nowrap uppercase font-black px-2.5 py-1 rounded-full shrink-0 border"
                            :class="res.status === 'BORROWED' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : 'bg-green-500/10 text-green-400 border-green-500/20'">
                            {{ res.status === 'APPROVED_AWAITING_PICKUP' ? 'AWAITING PICKUP' : res.status }}
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-2 text-gray-600 mb-4">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p v-if="res.status === 'BORROWED'" class="text-xs">{{ new Date(res.borrowed_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                        <p v-else class="text-xs">{{ new Date(res.approved_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                    </div>
                    
                    <div v-if="res.status === 'BORROWED' && res.due_date" class="mt-2 mb-4 bg-black/20 rounded-lg p-3 border border-gray-300">
                        <div class="flex justify-between items-end mb-1.5">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Time Left</span>
                            <span class="font-mono text-sm font-bold" :class="getUrgencyClass(res)">{{ countdown(res.due_date) }}</span>
                        </div>
                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 relative overflow-hidden" 
                                 :class="{
                                     'bg-emerald-500': getProgress(res) < 70,
                                     'bg-yellow-500': getProgress(res) >= 70 && getProgress(res) < 90,
                                     'bg-red-500': getProgress(res) >= 90
                                 }"
                                 :style="`width: ${getProgress(res)}%`">
                                 <div class="absolute inset-0 bg-white/20 w-full animate-[shimmer_2s_infinite]" :class="{'hidden': getProgress(res) >= 90}"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="res.status === 'APPROVED_AWAITING_PICKUP'" class="mt-auto pt-2 flex gap-2">
                        <button class="px-4 text-xs font-semibold bg-gray-100 hover:bg-white/10 text-gray-600 hover:text-gray-900 py-2 rounded-lg transition-colors border border-gray-300">
                            Cancel
                        </button>
                    </div>
                    <div v-else-if="['ACTIVE', 'APPROVED'].includes(res.status)" class="mt-auto pt-2 flex gap-2">
                        <button class="px-4 text-xs font-semibold bg-gray-100 hover:bg-white/10 text-gray-600 hover:text-gray-900 py-2 rounded-lg transition-colors border border-gray-300">
                            Cancel
                        </button>
                        <button @click="printForm(res)"
                            class="flex-1 text-xs bg-ncst-gold/20 hover:bg-ncst-gold text-blue-600 hover:text-white font-bold py-2 rounded-lg transition-all flex items-center justify-center gap-1.5 border border-gray-200 hover:border-ncst-gold shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Form
                        </button>
                    </div>
                </div>
                <div v-if="activeReservations.length === 0"
                    class="col-span-full py-10 text-center border border-gray-300 rounded-2xl bg-white shadow-sm border border-gray-300">
                    <p class="text-gray-400 text-sm italic">No active reservations right now.</p>
                </div>
            </div>
        </section>

        <!-- ── Status Tracker ──────────────────────────────── -->
        <section>
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Status Tracker — Pending Requests</h2>
            <div class="space-y-3">
                <div v-for="res in pendingReservations" :key="res.id"
                    class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 px-5 py-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-ncst-gold/15 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 text-sm">{{ res.resource?.name }}</p>
                        <p class="text-xs text-gray-500">Requested: {{ new Date(res.requested_at).toLocaleString() }}</p>
                    </div>
                    <!-- Stage pills -->
                    <div class="hidden sm:flex items-center gap-1 text-[10px] font-bold">
                        <span class="bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded-full border border-yellow-500/30">PENDING APPROVAL</span>
                    </div>
                </div>
                <div v-if="pendingReservations.length === 0"
                    class="py-10 text-center border border-gray-300 rounded-2xl bg-white shadow-sm border border-gray-300">
                    <p class="text-gray-400 text-sm italic">No pending requests.</p>
                </div>
            </div>
        </section>


    </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
