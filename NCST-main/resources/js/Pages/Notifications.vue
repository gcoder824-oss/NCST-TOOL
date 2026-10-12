<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const reservations = computed(() => page.props.reservations || []);

// State
const activeTab = ref('all');
const readNotifIds = ref(new Set(JSON.parse(localStorage.getItem('read_notif_ids') || '[]')));
const now = ref(new Date());
let ticker = null;

onMounted(() => {
    ticker = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (ticker) clearInterval(ticker);
});

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
};

// Relative time helper
const timeAgo = (dateStr) => {
    if (!dateStr) return '';
    const diff = Math.floor((now.value.getTime() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};

// System Announcements
const systemAnnouncements = [
    {
        id: 'sys-welcome',
        type: 'announcement',
        title: 'Welcome to the NCST Tools & Equipment System',
        message: 'You can browse available campus inventory, reserve equipment, and present digital QR passes at the counter.',
        created_at: new Date(Date.now() - 3600000 * 24 * 7).toISOString(),
        icon: 'ℹ️',
        badge: 'SYSTEM',
        badgeColor: 'bg-blue-100 text-blue-700 border-blue-200'
    },
    {
        id: 'sys-rules',
        type: 'rule',
        title: 'Strict 2-Minute Borrowing Policy',
        message: 'Equipment borrowed has a strict 2-minute duration with a 1-minute warning alert. Once overdue, borrowing access is immediately locked until the item is returned.',
        created_at: new Date(Date.now() - 3600000 * 24 * 3).toISOString(),
        icon: '⚠️',
        badge: 'POLICY',
        badgeColor: 'bg-amber-100 text-amber-800 border-amber-200'
    }
];

// Computed mapped notifications from reservations
const reservationNotifications = computed(() => {
    return reservations.value.map(res => {
        const isStaff = user.value.role === 'staff' || user.value.role === 'admin';
        const itemName = res.resource?.name || 'Equipment';
        const assetCode = res.resource?.asset_code || '';
        let type = 'info';
        let title = '';
        let message = '';
        let badge = res.status;
        let badgeColor = 'bg-gray-100 text-gray-700 border-gray-200';
        let actionUrl = '/passes';
        let actionText = 'View QR Pass';
        let icon = '📦';

        if (res.status === 'BORROWED') {
            const dueDate = res.due_date ? new Date(res.due_date) : null;
            const diffSeconds = dueDate ? (dueDate.getTime() - now.value.getTime()) / 1000 : null;

            if (diffSeconds !== null && diffSeconds <= 0) {
                type = 'alert';
                title = `OVERDUE: Return "${itemName}" Immediately`;
                message = `The 2-minute return window has expired. Your borrowing privileges are currently suspended until this item is returned to the counter.`;
                badge = 'OVERDUE';
                badgeColor = 'bg-red-100 text-red-700 border-red-300 font-black animate-pulse';
                actionUrl = '/borrowed';
                actionText = 'View Borrowed Item';
                icon = '🚨';
            } else if (diffSeconds !== null && diffSeconds <= 60) {
                type = 'warning';
                const secs = Math.max(0, Math.floor(diffSeconds));
                title = `Due Date Alert: ${secs}s Remaining on "${itemName}"`;
                message = `Item is due in less than a minute! Please proceed to the counter to return it and avoid immediate account lock.`;
                badge = `${secs}s REMAINING`;
                badgeColor = 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
                actionUrl = '/borrowed';
                actionText = 'View Borrowed Item';
                icon = '⏰';
            } else {
                type = 'active';
                title = `Currently Borrowed: "${itemName}"`;
                message = `Item active in your possession. Due on ${formatDate(res.due_date)}.`;
                badge = 'ACTIVE LOAN';
                badgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                actionUrl = '/borrowed';
                actionText = 'View Borrowed Item';
                icon = '⏱️';
            }
        } else if (res.status === 'APPROVED_AWAITING_PICKUP' || res.status === 'APPROVED') {
            type = 'approval';
            title = `Request Approved: "${itemName}"`;
            message = isStaff 
                ? `${res.user?.name || 'A user'} has an approved request ready for pickup.`
                : `Your reservation request for "${itemName}" has been approved! Present your QR pass at the counter to claim.`;
            badge = 'READY FOR CLAIM';
            badgeColor = 'bg-green-100 text-green-700 border-green-200';
            actionUrl = isStaff ? '/approvals' : '/passes';
            actionText = isStaff ? 'View Approvals' : 'Open QR Pass';
            icon = '✅';
        } else if (res.status === 'PENDING_APPROVAL' || res.status === 'PENDING') {
            type = 'pending';
            title = isStaff ? `New Request from ${res.user?.name || 'User'}` : `Request Submitted for "${itemName}"`;
            message = isStaff
                ? `Pending approval for ${itemName} (Asset: ${assetCode}).`
                : `Your reservation request is currently waiting for counter/signatory approval.`;
            badge = 'PENDING APPROVAL';
            badgeColor = 'bg-amber-100 text-amber-700 border-amber-200';
            actionUrl = isStaff ? '/approvals' : '/borrow-requests';
            actionText = isStaff ? 'Review Request' : 'View Request';
            icon = '⏳';
        } else if (res.status === 'RETURNED' || res.status === 'DISPENSED') {
            type = 'history';
            title = `Returned & Completed: "${itemName}"`;
            message = `Item was successfully returned and verified by staff. Thank you!`;
            badge = 'RETURNED';
            badgeColor = 'bg-gray-100 text-gray-600 border-gray-200';
            actionUrl = '/history';
            actionText = 'View in History';
            icon = '✔️';
        } else if (res.status === 'REJECTED' || res.status === 'CANCELLED') {
            type = 'rejected';
            title = `Request Not Approved: "${itemName}"`;
            message = `Your reservation could not be approved at this time.`;
            badge = 'REJECTED';
            badgeColor = 'bg-rose-100 text-rose-700 border-rose-200';
            actionUrl = '/borrow-requests';
            actionText = 'Check Details';
            icon = '❌';
        }

        return {
            id: `res-${res.id}`,
            reservationId: res.id,
            type,
            title,
            message,
            badge,
            badgeColor,
            actionUrl,
            actionText,
            icon,
            assetCode,
            itemName,
            created_at: res.updated_at || res.created_at,
            raw: res
        };
    });
});

// Combine all notifications
const allNotifications = computed(() => {
    return [...reservationNotifications.value, ...systemAnnouncements].sort((a, b) => {
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    });
});

// Filtered notifications based on active tab
const filteredNotifications = computed(() => {
    if (activeTab.value === 'alerts') {
        return allNotifications.value.filter(n => n.type === 'alert' || n.type === 'warning');
    }
    if (activeTab.value === 'approvals') {
        return allNotifications.value.filter(n => n.type === 'approval' || n.type === 'pending');
    }
    if (activeTab.value === 'history') {
        return allNotifications.value.filter(n => n.type === 'history' || n.type === 'rejected');
    }
    if (activeTab.value === 'announcements') {
        return allNotifications.value.filter(n => n.type === 'announcement' || n.type === 'rule');
    }
    return allNotifications.value;
});

// Count metrics
const totalAlertsCount = computed(() => {
    return allNotifications.value.filter(n => n.type === 'alert' || n.type === 'warning').length;
});
const totalApprovalsCount = computed(() => {
    return allNotifications.value.filter(n => n.type === 'approval' || n.type === 'pending').length;
});
const unreadCount = computed(() => {
    return allNotifications.value.filter(n => !readNotifIds.value.has(n.id)).length;
});

// Mark actions
const markAsRead = (id) => {
    readNotifIds.value.add(id);
    localStorage.setItem('read_notif_ids', JSON.stringify([...readNotifIds.value]));
};

const markAllRead = () => {
    allNotifications.value.forEach(n => readNotifIds.value.add(n.id));
    localStorage.setItem('read_notif_ids', JSON.stringify([...readNotifIds.value]));
};

const refreshNotifications = () => {
    router.reload({ preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Notifications Board" />

    <div class="py-8 space-y-8 max-w-7xl mx-auto">
        <!-- ── Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center shadow-sm">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
                            Notifications Board
                            <span v-if="unreadCount > 0" class="text-xs bg-red-500 text-white font-bold px-2.5 py-0.5 rounded-full shadow-sm animate-pulse">
                                {{ unreadCount }} New
                            </span>
                        </h1>
                        <p class="text-gray-500 text-sm mt-0.5">Real-time status updates, approval notices, return due dates, and system announcements.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button 
                    @click="refreshNotifications"
                    class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold shadow-sm transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
                <button 
                    @click="markAllRead"
                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-md shadow-blue-500/20 transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Mark All Read
                </button>
            </div>
        </div>

        <!-- ── Quick Metric Cards ─────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Total Notifications -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Notices</span>
                    <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">📬</span>
                </div>
                <p class="text-3xl font-black text-gray-900 mt-2">{{ allNotifications.length }}</p>
                <p class="text-xs text-gray-500 mt-1">Aggregated board alerts</p>
            </div>

            <!-- Active Alerts -->
            <div class="bg-white rounded-2xl border p-5 shadow-sm hover:shadow-md transition" :class="totalAlertsCount > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-gray-200'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider" :class="totalAlertsCount > 0 ? 'text-amber-600' : 'text-gray-400'">Borrow Alerts</span>
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm">⏰</span>
                </div>
                <p class="text-3xl font-black text-gray-900 mt-2">{{ totalAlertsCount }}</p>
                <p class="text-xs text-gray-500 mt-1">Due warnings & overdue items</p>
            </div>

            <!-- Approvals & Passes -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Approvals</span>
                    <span class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center font-bold text-sm">✅</span>
                </div>
                <p class="text-3xl font-black text-gray-900 mt-2">{{ totalApprovalsCount }}</p>
                <p class="text-xs text-gray-500 mt-1">Ready for pickup & pending</p>
            </div>

            <!-- Account Standing -->
            <div class="bg-white rounded-2xl border p-5 shadow-sm hover:shadow-md transition" :class="user.is_suspended ? 'border-red-300 bg-red-50/20' : 'border-emerald-200 bg-emerald-50/20'">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider" :class="user.is_suspended ? 'text-red-500' : 'text-emerald-600'">Account Standing</span>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-sm" :class="user.is_suspended ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'">
                        {{ user.is_suspended ? '🚨' : '🛡️' }}
                    </span>
                </div>
                <p class="text-2xl font-black mt-2" :class="user.is_suspended ? 'text-red-600' : 'text-gray-900'">
                    {{ user.is_suspended ? 'Suspended' : 'Good Standing' }}
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ user.is_suspended ? 'Overdue item lock active' : 'Full borrowing privileges' }}</p>
            </div>
        </div>

        <!-- ── Filter Tabs ─────────────────────────────────────────── -->
        <div class="flex items-center gap-2 border-b border-gray-200 overflow-x-auto pb-2 custom-scrollbar">
            <button 
                @click="activeTab = 'all'"
                class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shrink-0"
                :class="activeTab === 'all' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
            >
                All Notifications
                <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'all' ? 'bg-blue-200 text-blue-800' : 'bg-gray-200 text-gray-600'">
                    {{ allNotifications.length }}
                </span>
            </button>

            <button 
                @click="activeTab = 'alerts'"
                class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shrink-0"
                :class="activeTab === 'alerts' ? 'bg-amber-50 text-amber-800' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
            >
                Due Date & Alerts
                <span v-if="totalAlertsCount > 0" class="text-xs px-2 py-0.5 rounded-full bg-amber-200 text-amber-900 font-bold">
                    {{ totalAlertsCount }}
                </span>
            </button>

            <button 
                @click="activeTab = 'approvals'"
                class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shrink-0"
                :class="activeTab === 'approvals' ? 'bg-green-50 text-green-800' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
            >
                Approvals & Passes
                <span v-if="totalApprovalsCount > 0" class="text-xs px-2 py-0.5 rounded-full bg-green-200 text-green-900 font-bold">
                    {{ totalApprovalsCount }}
                </span>
            </button>

            <button 
                @click="activeTab = 'history'"
                class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shrink-0"
                :class="activeTab === 'history' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
            >
                Returned / Closed
            </button>

            <button 
                @click="activeTab = 'announcements'"
                class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shrink-0"
                :class="activeTab === 'announcements' ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'"
            >
                Announcements
            </button>
        </div>

        <!-- ── Notification Board Feed ────────────────────────────── -->
        <div v-if="filteredNotifications.length === 0" class="bg-white rounded-3xl border border-gray-200 p-16 text-center shadow-sm">
            <div class="w-16 h-16 rounded-full bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center mx-auto mb-4 text-2xl">
                ✨
            </div>
            <h3 class="text-lg font-bold text-gray-900">You're all caught up!</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">No notifications currently found under this section. Any item updates or counter alerts will appear here in real time.</p>
            <div class="mt-6 flex justify-center gap-3">
                <Link href="/dashboard" class="px-5 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-bold transition">
                    Go to Dashboard
                </Link>
                <Link href="/equipment" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition shadow-sm">
                    Borrow Equipment
                </Link>
            </div>
        </div>

        <div v-else class="space-y-4">
            <div 
                v-for="item in filteredNotifications" 
                :key="item.id"
                class="bg-white rounded-2xl border transition-all duration-200 p-5 shadow-sm hover:shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4"
                :class="[
                    readNotifIds.has(item.id) ? 'border-gray-200 opacity-80' : 'border-blue-200/80 shadow-[0_4px_20px_rgba(59,130,246,0.06)]',
                    item.type === 'alert' ? 'border-red-400 bg-red-50/20' : '',
                    item.type === 'warning' ? 'border-amber-400 bg-amber-50/20' : ''
                ]"
                @click="markAsRead(item.id)"
            >
                <!-- Left Details -->
                <div class="flex items-start gap-4 flex-1">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-sm" :class="{
                        'bg-red-100 border border-red-200': item.type === 'alert',
                        'bg-amber-100 border border-amber-200': item.type === 'warning',
                        'bg-green-100 border border-green-200': item.type === 'approval',
                        'bg-blue-100 border border-blue-200': item.type === 'active' || item.type === 'announcement' || item.type === 'rule',
                        'bg-gray-100 border border-gray-200': item.type === 'history' || item.type === 'pending' || item.type === 'rejected'
                    }">
                        {{ item.icon }}
                    </div>

                    <div class="space-y-1 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-bold text-gray-900 text-base">{{ item.title }}</h4>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wide" :class="item.badgeColor">
                                {{ item.badge }}
                            </span>
                            <span v-if="!readNotifIds.has(item.id)" class="w-2 h-2 rounded-full bg-blue-600 shrink-0" title="Unread"></span>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ item.message }}</p>
                        <div class="flex items-center gap-4 text-xs text-gray-400 pt-1">
                            <span>{{ formatDate(item.created_at) }}</span>
                            <span>•</span>
                            <span>{{ timeAgo(item.created_at) }}</span>
                            <span v-if="item.assetCode">• Asset: {{ item.assetCode }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                    <Link 
                        v-if="item.actionUrl" 
                        :href="item.actionUrl" 
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                        :class="item.type === 'alert' || item.type === 'warning' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border border-blue-200'"
                    >
                        <span>{{ item.actionText }}</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    <button 
                        v-if="!readNotifIds.has(item.id)"
                        @click.stop="markAsRead(item.id)"
                        class="p-2 rounded-xl text-gray-400 hover:text-blue-600 hover:bg-gray-100 transition"
                        title="Mark as read"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
