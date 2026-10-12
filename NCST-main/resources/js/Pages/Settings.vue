<script setup>
</script>

<template>
    <div class="py-10 space-y-10">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">System Settings</h1>
                <p class="text-gray-600 text-sm mt-1">Manage your application preferences and security.</p>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 gap-8">
                <!-- Notifications Section -->
                <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 shadow-lg overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-300">
                        <h3 class="text-lg font-medium text-gray-900">Notifications</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-gray-900 font-medium text-sm">Email Notifications</h4>
                                <p class="text-gray-600 text-xs mt-1">Receive an email when your reservation is approved or items are due.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-ncst-gold"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-gray-900 font-medium text-sm">System Alerts</h4>
                                <p class="text-gray-600 text-xs mt-1">Show pop-ups within the application for critical events.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-ncst-gold"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="bg-white shadow-sm border border-gray-300 rounded-2xl border border-gray-300 shadow-lg overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-300">
                        <h3 class="text-lg font-medium text-gray-900">Security</h3>
                    </div>
                    <div class="p-6">
                        <form @submit.prevent="" class="space-y-4 max-w-xl">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-600">Current Password</label>
                                <input type="password" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-gray-900 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-600">New Password</label>
                                <input type="password" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-gray-900 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-600">Confirm New Password</label>
                                <input type="password" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-gray-900 focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold transition-colors">
                            </div>
                            <div class="pt-4">
                                <button type="button" class="px-6 py-2.5 bg-blue-50 text-blue-600 border border-gray-200 font-bold rounded-xl hover:bg-ncst-gold hover:text-white transition-colors">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    </div>
</template>
