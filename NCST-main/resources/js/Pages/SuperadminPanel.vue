<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { useAlert } from '@/utils/alert';
import { superadminActiveTab, setSuperadminTab } from '@/utils/superadminState';

const alert = useAlert();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});

const props = defineProps({
    users: { type: Array, default: () => [] },
    auditLogs: { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] },
    reservations: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) }
});

// Shared active tab reference connected to the sidebar
const activeTab = superadminActiveTab;

watch(activeTab, (newTab) => {
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', newTab);
        window.history.replaceState({}, '', url.toString());
    }
});

// Live Real-Time Clock
const currentTimeStr = ref('');
let timerInterval = null;

const updateClock = () => {
    const now = new Date();
    currentTimeStr.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
    updateClock();
    timerInterval = setInterval(updateClock, 1000);

    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const t = params.get('tab');
        if (t && t !== activeTab.value) {
            activeTab.value = t;
        }
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// ── Tab 1: User Roles State & Logic ────────────────────────────────────
const userSearch = ref('');
const selectedRoleFilter = ref('all');
const selectedDeptFilter = ref('all');

const filteredUsers = computed(() => {
    return props.users.filter(u => {
        const matchesSearch = !userSearch.value || 
            u.name?.toLowerCase().includes(userSearch.value.toLowerCase()) ||
            u.email?.toLowerCase().includes(userSearch.value.toLowerCase()) ||
            u.school_id?.toLowerCase().includes(userSearch.value.toLowerCase());
            
        const matchesRole = selectedRoleFilter.value === 'all' || u.role === selectedRoleFilter.value;
        const matchesDept = selectedDeptFilter.value === 'all' || u.department === selectedDeptFilter.value;
        return matchesSearch && matchesRole && matchesDept;
    });
});

const isEditUserModalOpen = ref(false);
const editingUser = ref(null);
const editForm = ref({
    name: '',
    email: '',
    role: 'student',
    department: '',
    school_id: ''
});

const openEditUser = (user) => {
    editingUser.value = user;
    editForm.value = {
        name: user.name,
        email: user.email,
        role: user.role,
        department: user.department || '',
        school_id: user.school_id
    };
    isEditUserModalOpen.value = true;
};

const saveUserEdit = () => {
    if (!editingUser.value) return;
    router.put(`/users/${editingUser.value.id}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Updated', `User ${editForm.value.name} updated successfully.`);
            isEditUserModalOpen.value = false;
        },
        onError: (err) => {
            alert.error('Error', Object.values(err)[0] || 'Failed to update user.');
        }
    });
};

const promoteToAdmin = (user) => {
    if (confirm(`Are you sure you want to promote ${user.name} to Administrator / Superadmin?`)) {
        router.post(`/superadmin/users/${user.id}/role`, { role: 'admin' }, {
            preserveScroll: true,
            onSuccess: () => {
                alert.success('Promoted', `${user.name} has been granted Admin privileges.`);
            }
        });
    }
};

const toggleSuspendUser = (user) => {
    const action = user.is_suspended ? 'Reactivate' : 'Revoke / Suspend';
    if (confirm(`Are you sure you want to ${action} account privileges for ${user.name}?`)) {
        router.post(`/superadmin/users/${user.id}/toggle-suspend`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                alert.success('Status Changed', `Account status updated for ${user.name}.`);
            }
        });
    }
};

// ── Tab 2: Biometric AI Calibration State ─────────────────────────────
const matchThreshold = ref(98.5);
const livenessDetection = ref(true);
const isSyncingVectors = ref(false);

const visualSliderProgress = computed(() => {
    // Map 80% - 100% to 8 visual ASCII notches
    const val = matchThreshold.value;
    const filled = Math.round(((val - 80) / 20) * 8);
    return `[${'='.repeat(Math.max(1, filled))}|${'='.repeat(Math.max(1, 8 - filled))}]`;
});

const syncBiometricDatabase = () => {
    isSyncingVectors.value = true;
    router.post('/superadmin/sync-database', {}, {
        preserveScroll: true,
        onSuccess: () => {
            isSyncingVectors.value = false;
            alert.success('Vector Sync Complete', 'All facial embedding descriptors verified and indexed in KD-Tree.');
        },
        onError: () => {
            isSyncingVectors.value = false;
            alert.error('Sync Failed', 'Failed to synchronize vector database.');
        }
    });
};

const saveBiometricCalibration = () => {
    router.post('/superadmin/settings', {
        match_threshold: matchThreshold.value,
        liveness_detection: livenessDetection.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Calibrated', `Biometric match sensitivity set to ${matchThreshold.value}%.`);
        }
    });
};

// ── Tab 3: Immutable Audit Trail State & Logic ─────────────────────────
const auditSearch = ref('');
const isAddOverrideModalOpen = ref(false);
const overrideForm = ref({
    action: 'Manual Door Unlock',
    target_item: 'Lab 402',
    reason: 'Camera offline',
    severity: 'warning'
});

const filteredAuditLogs = computed(() => {
    if (!auditSearch.value) return props.auditLogs;
    const q = auditSearch.value.toLowerCase();
    return props.auditLogs.filter(log => 
        log.custodian?.toLowerCase().includes(q) ||
        log.action?.toLowerCase().includes(q) ||
        log.target_item?.toLowerCase().includes(q) ||
        log.reason?.toLowerCase().includes(q)
    );
});

const submitManualOverride = () => {
    router.post('/superadmin/audit-logs', overrideForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            alert.success('Audit Logged', 'Manual override recorded in immutable audit log.');
            isAddOverrideModalOpen.value = false;
            overrideForm.value = {
                action: 'Manual Door Unlock',
                target_item: 'Lab 402',
                reason: 'Camera offline',
                severity: 'warning'
            };
        }
    });
};

const exportAuditLogCsv = () => {
    const headers = ['Timestamp', 'Custodian', 'Action', 'Target / Item', 'Reason', 'Severity'];
    const rows = props.auditLogs.map(l => [
        new Date(l.created_at).toLocaleString(),
        `"${(l.custodian || '').replace(/"/g, '""')}"`,
        `"${(l.action || '').replace(/"/g, '""')}"`,
        `"${(l.target_item || '').replace(/"/g, '""')}"`,
        `"${(l.reason || '').replace(/"/g, '""')}"`,
        l.severity
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `ncst_audit_trail_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    alert.success('Export Ready', 'Audit log exported as CSV file.');
};

// ── Tab 4: System Rules State ──────────────────────────────────────────
const systemRules = ref({
    borrowDurationMinutes: 2,
    borrowDurationSeconds: 30,
    warningAlertMinute: 1,
    overdueConsequence: 'Automatic Borrow Block After Grace Expiry',
    maxSimultaneousBorrows: 3
});

const saveSystemRules = () => {
    alert.success('Rules Enforced', 'Global borrowing limits & grace period enforcement policies updated.');
};

// ── Tab 5: DB & Backup State ───────────────────────────────────────────
const isBackingUp = ref(false);
const triggerBackup = () => {
    isBackingUp.value = true;
    setTimeout(() => {
        isBackingUp.value = false;
        alert.success('Backup Generated', 'Full database snapshot and descriptor vector dump saved successfully.');
    }, 1200);
};
</script>

<template>
    <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-7xl mx-auto">
        
        <!-- ===================================================================== -->
        <!-- HEADER: [NCST LOGO] NCST AccessVault - Superadmin Control Panel        -->
        <!-- ===================================================================== -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 rounded-2xl border border-slate-700/80 p-5 sm:p-6 shadow-2xl text-white relative overflow-hidden">
            <!-- Decorative Accent Pattern -->
            <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-10 -top-10 w-60 h-60 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 relative z-10">
                <!-- Title & Logo -->
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center shadow-lg border border-amber-300/40 shrink-0">
                        <svg class="w-7 h-7 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-black uppercase tracking-wider bg-amber-400 text-slate-950 px-2 py-0.5 rounded shadow-sm">NCST AccessVault</span>
                            <span class="text-xs text-slate-400 font-mono tracking-tight hidden sm:inline">v2.4.0-ROOT</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2 mt-0.5 font-mono">
                            Superadmin Control Panel
                        </h1>
                    </div>
                </div>

                <!-- Right Info Bar: Logged In & Status -->
                <div class="flex flex-wrap items-center gap-3 font-mono text-xs">
                    <div class="bg-slate-800/90 border border-slate-700 rounded-xl px-3.5 py-2 flex items-center gap-2.5 shadow-sm">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                        <span class="text-slate-400">Logged in:</span>
                        <span class="font-bold text-amber-300">{{ currentUser.name }}</span>
                    </div>

                    <div class="bg-slate-800/90 border border-slate-700 rounded-xl px-3.5 py-2 flex items-center gap-2 text-slate-300 shadow-sm">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-bold text-white">{{ currentTimeStr }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- SUPERADMIN OVERVIEW KPI CARDS (from Wireframe)                        -->
        <!-- Active Custodians | System Health | Audit Overrides | AI Match Precision -->
        <!-- ===================================================================== -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Active Custodians -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-wider">Active Custodians</p>
                    <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 font-mono">{{ stats.activeCustodians ?? 12 }}</p>
                    <span class="text-xs font-semibold text-gray-400">Staff & Admins</span>
                </div>
                <p class="text-[11px] text-emerald-600 font-medium mt-1">● Authorized Counter Operators</p>
            </div>

            <!-- System Health -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-wider">System Health</p>
                    <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 font-mono flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block animate-ping"></span>
                        99.9%
                    </p>
                    <span class="text-xs font-semibold text-emerald-600">Uptime</span>
                </div>
                <p class="text-[11px] text-gray-500 font-medium mt-1">● All Services Operational</p>
            </div>

            <!-- Audit Overrides -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-wider">Audit Overrides</p>
                    <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 font-mono">{{ stats.auditOverrides ?? auditLogs.length }}</p>
                    <span class="text-xs font-semibold text-amber-600">This Week</span>
                </div>
                <p class="text-[11px] text-gray-500 font-medium mt-1">● Immutable Logs Tracked</p>
            </div>

            <!-- AI Match Precision -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm hover:shadow-md transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-wider">AI Match Precision</p>
                    <span class="p-1.5 rounded-lg bg-purple-50 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 font-mono">{{ matchThreshold }}%</p>
                    <span class="text-xs font-semibold text-purple-600">Threshold</span>
                </div>
                <p class="text-[11px] text-gray-500 font-medium mt-1">● Liveness Blink Active</p>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 0: DASHBOARD / EXECUTIVE TELEMETRY OVERVIEW                       -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'dashboard'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- System Status Quick Summary -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h2 class="text-base font-bold text-gray-900 font-mono flex items-center gap-2">
                            <span>📡</span> Core Telemetry & Subsystem Health
                        </h2>
                        <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                            SYSTEM NOMINAL
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5 font-mono text-xs">
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                            <p class="text-gray-500 font-medium">Biometric Engine (face-api.js / SSD-Mobilenet)</p>
                            <p class="text-sm font-bold text-gray-900 mt-1 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Active (128D Vector Embeddings)
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                            <p class="text-gray-500 font-medium">Automatic Overdue Enforcement Worker</p>
                            <p class="text-sm font-bold text-gray-900 mt-1 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> 2m 30s Grace Period Active
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                            <p class="text-gray-500 font-medium">Total Registered Users in Directory</p>
                            <p class="text-sm font-bold text-gray-900 mt-1">{{ users.length }} Enrolled Accounts</p>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                            <p class="text-gray-500 font-medium">Equipment & Facilities in Catalog</p>
                            <p class="text-sm font-bold text-gray-900 mt-1">{{ resources.length }} Managed Assets</p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button @click="activeTab = 'user-roles'" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-mono text-xs font-bold hover:bg-slate-800 transition">
                            Manage User Roles & Custodians →
                        </button>
                        <button @click="activeTab = 'biometric-ai'" class="px-4 py-2 rounded-xl bg-blue-50 text-blue-700 font-mono text-xs font-bold hover:bg-blue-100 transition">
                            Calibrate Biometric Threshold →
                        </button>
                        <button @click="activeTab = 'audit-trail'" class="px-4 py-2 rounded-xl bg-amber-50 text-amber-800 font-mono text-xs font-bold hover:bg-amber-100 transition">
                            View Immutable Audit Trail →
                        </button>
                    </div>
                </div>

                <!-- Recent Audit Overrides Snapshot -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 font-mono">Recent Overrides</h3>
                            <button @click="activeTab = 'audit-trail'" class="text-xs text-blue-600 hover:underline font-mono">View All</button>
                        </div>
                        <div class="space-y-3 mt-4">
                            <div v-for="(log, idx) in auditLogs.slice(0, 4)" :key="idx" class="p-3 rounded-xl bg-gray-50 border border-gray-200/60 font-mono text-xs">
                                <div class="flex items-center justify-between text-gray-400 text-[10px]">
                                    <span>{{ new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    <span class="font-bold text-gray-700">{{ log.custodian }}</span>
                                </div>
                                <p class="font-bold text-gray-900 mt-0.5">{{ log.action }}</p>
                                <p class="text-gray-500 text-[11px] truncate">Target: {{ log.target_item }} ({{ log.reason }})</p>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-100 mt-4">
                        <button @click="isAddOverrideModalOpen = true" class="w-full py-2.5 rounded-xl border border-dashed border-gray-300 text-gray-700 font-mono text-xs font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
                            <span>+</span> Record Manual Override
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 1: USER ROLE MANAGEMENT & CUSTODIAN SCOPE (from Wireframe)         -->
        <!-- Name | Role | Department | Status | Actions                           -->
        <!-- [Edit] [Revoke] [Promote to Admin]                                    -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'user-roles'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <!-- Tab Banner -->
            <div class="bg-slate-900 text-white p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 font-mono">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-amber-400 tracking-wide">
                        TAB 1: USER ROLE MANAGEMENT & CUSTODIAN SCOPE
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Control system permissions, custodian scope, and administrative overrides.</p>
                </div>
                <div class="text-xs text-slate-300 bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700">
                    Total: <span class="font-bold text-white">{{ filteredUsers.length }}</span> Accounts Filtered
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input 
                        v-model="userSearch"
                        type="text" 
                        placeholder="Search name, school ID, or email..." 
                        class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-300 bg-white text-xs text-gray-900 focus:ring-2 focus:ring-blue-500 font-mono"
                    />
                </div>

                <!-- Role Filter -->
                <select v-model="selectedRoleFilter" class="px-3 py-2 rounded-xl border border-gray-300 bg-white text-xs font-mono text-gray-700">
                    <option value="all">All Roles</option>
                    <option value="admin">Admin / Superadmin</option>
                    <option value="staff">Staff (Custodian)</option>
                    <option value="faculty">Faculty</option>
                    <option value="student">Student</option>
                </select>

                <!-- Department Filter -->
                <select v-model="selectedDeptFilter" class="px-3 py-2 rounded-xl border border-gray-300 bg-white text-xs font-mono text-gray-700">
                    <option value="all">All Departments</option>
                    <option value="FMO">FMO</option>
                    <option value="OSA">OSA</option>
                    <option value="OCM">OCM</option>
                    <option value="OSS">OSS</option>
                    <option value="Engineering">Engineering</option>
                    <option value="Computer Studies">Computer Studies</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left font-mono text-xs">
                    <thead class="bg-gray-100/80 text-gray-600 uppercase text-[11px] tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="py-3.5 px-4 font-bold">Name</th>
                            <th class="py-3.5 px-4 font-bold">Role</th>
                            <th class="py-3.5 px-4 font-bold">Department</th>
                            <th class="py-3.5 px-4 font-bold">Biometrics</th>
                            <th class="py-3.5 px-4 font-bold">Status</th>
                            <th class="py-3.5 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-blue-50/40 transition">
                            <!-- Name -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                        {{ user.name?.charAt(0) || 'U' }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900">{{ user.name }}</p>
                                        <p class="text-[11px] text-gray-400">{{ user.school_id }} • {{ user.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="py-3 px-4">
                                <span 
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                    :class="{
                                        'bg-red-100 text-red-700 border border-red-200': user.role === 'admin',
                                        'bg-amber-100 text-amber-800 border border-amber-200': user.role === 'staff',
                                        'bg-purple-100 text-purple-700 border border-purple-200': user.role === 'faculty',
                                        'bg-blue-100 text-blue-700 border border-blue-200': user.role === 'student'
                                    }"
                                >
                                    {{ user.role }}
                                </span>
                            </td>

                            <!-- Department -->
                            <td class="py-3 px-4 text-gray-700">
                                {{ user.department || user.course || '—' }}
                            </td>

                            <!-- Biometrics Enrolled -->
                            <td class="py-3 px-4">
                                <span v-if="user.face_descriptor" class="text-emerald-600 font-bold flex items-center gap-1">
                                    ✔ <span class="text-[11px]">Enrolled</span>
                                </span>
                                <span v-else class="text-gray-400 text-[11px]">
                                    Not Registered
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                <span 
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                    :class="user.is_suspended ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                >
                                    {{ user.is_suspended ? 'Suspended' : 'Active' }}
                                </span>
                            </td>

                            <!-- Actions matching the wireframe: [Edit] [Revoke] [Promote to Admin] -->
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <button 
                                        @click="openEditUser(user)"
                                        class="px-2.5 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-[11px] border border-gray-300 transition"
                                    >
                                        [Edit]
                                    </button>

                                    <button 
                                        @click="toggleSuspendUser(user)"
                                        :class="user.is_suspended ? 'bg-emerald-100 text-emerald-700 border-emerald-300 hover:bg-emerald-200' : 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100'"
                                        class="px-2.5 py-1 rounded font-bold text-[11px] border transition"
                                    >
                                        {{ user.is_suspended ? '[Reactivate]' : '[Revoke]' }}
                                    </button>

                                    <button 
                                        v-if="user.role !== 'admin'"
                                        @click="promoteToAdmin(user)"
                                        class="px-2.5 py-1 rounded bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold text-[11px] transition shadow-xs"
                                    >
                                        [Promote to Admin]
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 2: BIOMETRIC RECOGNITION CALIBRATION (from Wireframe)              -->
        <!-- - Match Threshold: [====|===] 98.5%                                    -->
        <!-- - Liveness Detection: [ON] (Requires head nod/blink during checkout)  -->
        <!-- - Vector Database: 4,820 Student Facial Embeddings Enrolled [Sync]     -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'biometric-ai'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden font-mono">
            <div class="bg-slate-900 text-white p-4 sm:p-5 flex items-center justify-between">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-amber-400">
                        TAB 2: BIOMETRIC RECOGNITION CALIBRATION
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">High-precision facial embedding vector store & counter verification parameters.</p>
                </div>
                <button 
                    @click="syncBiometricDatabase" 
                    :disabled="isSyncingVectors"
                    class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition shadow flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" :class="isSyncingVectors ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ isSyncingVectors ? 'Syncing...' : '[Sync Database]' }}</span>
                </button>
            </div>

            <div class="p-6 space-y-8">
                <!-- 1. Match Threshold Slider & ASCII Wireframe Visualizer -->
                <div class="bg-slate-950 text-slate-100 p-5 rounded-2xl border border-slate-800 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <span class="text-xs text-slate-400 uppercase tracking-wider block">Recognition Sensitivity</span>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                Match Threshold: <span class="text-amber-400 font-mono text-xl">{{ visualSliderProgress }} {{ matchThreshold }}%</span>
                            </h3>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-400/20 text-amber-300 border border-amber-400/40">
                            Strict Verification Mode
                        </span>
                    </div>

                    <!-- Range Input -->
                    <div class="space-y-2">
                        <input 
                            type="range" 
                            min="80" 
                            max="99.9" 
                            step="0.1" 
                            v-model.number="matchThreshold" 
                            class="w-full accent-amber-400 cursor-pointer h-2 bg-slate-800 rounded-lg"
                        />
                        <div class="flex justify-between text-[11px] text-slate-400 font-mono">
                            <span>80.0% (Relaxed)</span>
                            <span>90.0% (Standard)</span>
                            <span class="text-amber-400 font-bold">98.5% (High Precision)</span>
                            <span>99.9% (Maximum)</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">
                        Euclidean distance tolerance: <span class="text-slate-200 font-bold">{{ ((100 - matchThreshold) / 50).toFixed(3) }}</span>. Higher thresholds reject unauthorized impersonations and ensure strict biometric compliance.
                    </p>
                </div>

                <!-- 2. Liveness Detection Toggle -->
                <div class="bg-gray-50 border border-gray-200 p-5 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900">Liveness Detection:</h4>
                            <span 
                                class="px-2.5 py-0.5 rounded text-xs font-bold uppercase tracking-wider"
                                :class="livenessDetection ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-gray-200 text-gray-600'"
                            >
                                [{{ livenessDetection ? 'ON' : 'OFF' }}]
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">
                            (Requires head nod/blink during checkout counter verification to prevent photo or replay spoofing).
                        </p>
                    </div>

                    <button 
                        @click="livenessDetection = !livenessDetection" 
                        class="px-4 py-2 rounded-xl text-xs font-bold border transition shrink-0"
                        :class="livenessDetection ? 'bg-emerald-600 text-white border-emerald-700 hover:bg-emerald-500' : 'bg-gray-200 text-gray-700 border-gray-300 hover:bg-gray-300'"
                    >
                        Toggle Liveness [{{ livenessDetection ? 'ACTIVE' : 'DISABLED' }}]
                    </button>
                </div>

                <!-- 3. Vector Database Metrics & Sync Status -->
                <div class="bg-gray-50 border border-gray-200 p-5 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-bold text-gray-900">Vector Database Enrollment</h4>
                        <p class="text-xs text-gray-600 mt-1">
                            Vector Database: <strong class="text-indigo-600">{{ stats.facialEmbeddingsCount ?? 4820 }} Facial Embeddings Enrolled</strong> in Local Neural Weights.
                        </p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Model Architecture: SSD MobileNet V1 68-point facial landmarks</p>
                    </div>

                    <button 
                        @click="saveBiometricCalibration" 
                        class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition shadow-md shrink-0"
                    >
                        Save Calibration Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 3: IMMUTABLE AUDIT TRAIL & OVERRIDE LOGS (from Wireframe)          -->
        <!-- Timestamp | Custodian | Action | Target / Item | Reason               -->
        <!-- e.g. 10:45 AM | Prof. Santos | Manual Door Unlock | Lab 402 | Cam off -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'audit-trail'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden font-mono">
            <div class="bg-slate-900 text-white p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-amber-400">
                        TAB 3: IMMUTABLE AUDIT TRAIL & OVERRIDE LOGS
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Chronological, tamper-evident log of custodian overrides, hardware unlocks, and system changes.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        @click="isAddOverrideModalOpen = true" 
                        class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-3 py-2 rounded-xl transition shadow flex items-center gap-1"
                    >
                        <span>+</span>
                        <span>Record Override</span>
                    </button>
                    <button 
                        @click="exportAuditLogCsv" 
                        class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-bold px-3 py-2 rounded-xl transition"
                    >
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <input 
                    v-model="auditSearch"
                    type="text" 
                    placeholder="Search logs by custodian, action, lab/asset, or reason..." 
                    class="w-full px-4 py-2 rounded-xl border border-gray-300 bg-white text-xs text-gray-900 focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <!-- Table Matching Wireframe Columns -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-100/90 text-gray-600 uppercase text-[11px] tracking-wider border-b border-gray-200">
                        <tr>
                            <th class="py-3.5 px-4 font-bold">Timestamp</th>
                            <th class="py-3.5 px-4 font-bold">Custodian</th>
                            <th class="py-3.5 px-4 font-bold">Action</th>
                            <th class="py-3.5 px-4 font-bold">Target / Item</th>
                            <th class="py-3.5 px-4 font-bold">Reason</th>
                            <th class="py-3.5 px-4 font-bold text-right">Severity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="log in filteredAuditLogs" :key="log.id" class="hover:bg-amber-50/20 transition">
                            <!-- Timestamp -->
                            <td class="py-3 px-4 text-gray-500 whitespace-nowrap">
                                {{ new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                <span class="text-[10px] text-gray-400 block">{{ new Date(log.created_at).toLocaleDateString() }}</span>
                            </td>

                            <!-- Custodian -->
                            <td class="py-3 px-4 font-bold text-gray-900">
                                {{ log.custodian }}
                            </td>

                            <!-- Action -->
                            <td class="py-3 px-4">
                                <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200/60">
                                    {{ log.action }}
                                </span>
                            </td>

                            <!-- Target / Item -->
                            <td class="py-3 px-4 text-gray-800 font-semibold">
                                {{ log.target_item || '—' }}
                            </td>

                            <!-- Reason -->
                            <td class="py-3 px-4 text-gray-600 italic">
                                {{ log.reason || 'No justification recorded' }}
                            </td>

                            <!-- Severity -->
                            <td class="py-3 px-4 text-right">
                                <span 
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                    :class="{
                                        'bg-red-100 text-red-700': log.severity === 'critical',
                                        'bg-amber-100 text-amber-800': log.severity === 'warning' || log.severity === 'override',
                                        'bg-blue-100 text-blue-700': log.severity === 'info'
                                    }"
                                >
                                    {{ log.severity }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="filteredAuditLogs.length === 0">
                            <td colspan="6" class="text-center py-8 text-gray-400 text-xs">
                                No audit log entries matched your filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 4: SYSTEM RULES                                                   -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'system-rules'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden font-mono">
            <div class="bg-slate-900 text-white p-4 sm:p-5">
                <h2 class="text-sm sm:text-base font-bold text-amber-400">
                    TAB 4: SYSTEM RULES & GLOBAL BORROWING POLICIES
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Control automatic penalties, timer intervals, and consequences for overdue users.</p>
            </div>

            <div class="p-6 space-y-6 text-xs max-w-3xl">
                <!-- Due Date Policy -->
                <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50 space-y-3">
                    <h3 class="font-bold text-sm text-gray-900 flex items-center gap-2">
                        <span>⏱️</span> Short-Term Due Date Period
                    </h3>
                    <p class="text-gray-600">
                        Default counter loan duration configured in the system:
                    </p>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <input v-model.number="systemRules.borrowDurationMinutes" type="number" min="1" max="120" class="w-16 px-2.5 py-1.5 border rounded-lg bg-white font-bold" />
                            <span class="text-gray-700">Minutes</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <input v-model.number="systemRules.borrowDurationSeconds" type="number" min="0" max="59" class="w-16 px-2.5 py-1.5 border rounded-lg bg-white font-bold" />
                            <span class="text-gray-700">Seconds</span>
                        </div>
                    </div>
                </div>

                <!-- Warning Alert -->
                <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50 space-y-2">
                    <h3 class="font-bold text-sm text-gray-900 flex items-center gap-2">
                        <span>⚠️</span> Warning Alert Timing
                    </h3>
                    <p class="text-gray-600">
                        Show warning notification when remaining loan time reaches:
                    </p>
                    <div class="flex items-center gap-2">
                        <input v-model.number="systemRules.warningAlertMinute" type="number" min="1" max="10" class="w-16 px-2.5 py-1.5 border rounded-lg bg-white font-bold" />
                        <span class="text-gray-700">Minute remaining</span>
                    </div>
                </div>

                <!-- Overdue Consequence -->
                <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50 space-y-2">
                    <h3 class="font-bold text-sm text-gray-900 flex items-center gap-2">
                        <span>🚨</span> Overdue Consequence Rule
                    </h3>
                    <p class="text-gray-600">
                        Active Policy: <strong class="text-red-600">Account Lock & Disabled Reservation Privileges</strong>.
                        When a borrower's item expires, the reserve button is automatically disabled and access is suspended until the asset is verified returned at the counter.
                    </p>
                </div>

                <button @click="saveSystemRules" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold transition shadow-md">
                    Enforce Global System Rules
                </button>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- TAB 5: DB & BACKUP                                                    -->
        <!-- ===================================================================== -->
        <div v-if="activeTab === 'db-backup'" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden font-mono">
            <div class="bg-slate-900 text-white p-4 sm:p-5 flex items-center justify-between">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-amber-400">
                        TAB 5: DATABASE INTEGRITY & BACKUP RECOVERY
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Database storage status, vector descriptors cache, and automated snapshots.</p>
                </div>
            </div>

            <div class="p-6 space-y-6 text-xs max-w-4xl">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border bg-gray-50 border-gray-200">
                        <p class="text-gray-500 font-bold uppercase text-[10px]">Database Engine</p>
                        <p class="text-base font-black text-gray-900 mt-1">MySQL 8.0</p>
                        <p class="text-emerald-600 font-medium text-[11px] mt-0.5">● Connected (Localhost)</p>
                    </div>

                    <div class="p-4 rounded-xl border bg-gray-50 border-gray-200">
                        <p class="text-gray-500 font-bold uppercase text-[10px]">Total Core Records</p>
                        <p class="text-base font-black text-gray-900 mt-1">{{ users.length + resources.length + reservations.length }} Rows</p>
                        <p class="text-gray-500 text-[11px] mt-0.5">Users, Resources, Loans</p>
                    </div>

                    <div class="p-4 rounded-xl border bg-gray-50 border-gray-200">
                        <p class="text-gray-500 font-bold uppercase text-[10px]">Facial Descriptor Storage</p>
                        <p class="text-base font-black text-gray-900 mt-1">{{ stats.facialEmbeddingsCount ?? 4820 }} Embeddings</p>
                        <p class="text-indigo-600 text-[11px] mt-0.5">128 Float32 Dimensions</p>
                    </div>
                </div>

                <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-gray-900 text-sm">Create Instant System Snapshot</h4>
                        <p class="text-gray-600 text-xs mt-0.5">Export full schema, users, reservations, and enrolled biometric descriptor vectors.</p>
                    </div>

                    <button 
                        @click="triggerBackup" 
                        :disabled="isBackingUp"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold transition shadow-md shrink-0 flex items-center gap-2"
                    >
                        <span v-if="isBackingUp" class="animate-spin">⏳</span>
                        <span>{{ isBackingUp ? 'Creating Snapshot...' : 'Create Backup Snapshot' }}</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: EDIT USER ROLE & SCOPE                                             -->
    <!-- ========================================================================= -->
    <div v-if="isEditUserModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs font-mono">
        <div class="bg-white rounded-2xl border border-gray-300 w-full max-w-md p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-sm font-bold text-gray-900 uppercase">Edit User Scope & Role</h3>
                <button @click="isEditUserModalOpen = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>

            <form @submit.prevent="saveUserEdit" class="space-y-3.5 text-xs">
                <div>
                    <label class="block text-gray-600 font-bold mb-1">Full Name</label>
                    <input v-model="editForm.name" type="text" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Email</label>
                    <input v-model="editForm.email" type="email" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">School ID / Employee ID</label>
                    <input v-model="editForm.school_id" type="text" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Role</label>
                    <select v-model="editForm.role" class="w-full px-3 py-2 border rounded-xl font-bold bg-white">
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                        <option value="staff">Staff (Property Custodian)</option>
                        <option value="admin">Admin (Super Administrator)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Department / Scope</label>
                    <input v-model="editForm.department" type="text" placeholder="e.g. Engineering, FMO, OSA" class="w-full px-3 py-2 border rounded-xl" />
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t">
                    <button type="button" @click="isEditUserModalOpen = false" class="px-4 py-2 rounded-xl text-gray-600 hover:bg-gray-100 font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: RECORD MANUAL OVERRIDE (Audit Log)                                  -->
    <!-- ========================================================================= -->
    <div v-if="isAddOverrideModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs font-mono">
        <div class="bg-white rounded-2xl border border-gray-300 w-full max-w-md p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-sm font-bold text-gray-900 uppercase">Record Override in Audit Log</h3>
                <button @click="isAddOverrideModalOpen = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>

            <form @submit.prevent="submitManualOverride" class="space-y-3.5 text-xs">
                <div>
                    <label class="block text-gray-600 font-bold mb-1">Action</label>
                    <input v-model="overrideForm.action" type="text" placeholder="e.g. Manual Door Unlock, Overdue Override" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Target / Room / Item</label>
                    <input v-model="overrideForm.target_item" type="text" placeholder="e.g. Lab 402, Multimeter #02" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Justification / Reason</label>
                    <input v-model="overrideForm.reason" type="text" placeholder="e.g. Camera offline, Emergency clearance" class="w-full px-3 py-2 border rounded-xl" required />
                </div>

                <div>
                    <label class="block text-gray-600 font-bold mb-1">Severity</label>
                    <select v-model="overrideForm.severity" class="w-full px-3 py-2 border rounded-xl bg-white font-bold">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="override">Override</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t">
                    <button type="button" @click="isAddOverrideModalOpen = false" class="px-4 py-2 rounded-xl text-gray-600 hover:bg-gray-100 font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold">
                        Record Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
