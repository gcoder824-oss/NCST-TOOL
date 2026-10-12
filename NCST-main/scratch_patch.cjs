const fs = require('fs');
let content = fs.readFileSync('resources/js/Pages/WalkIn.vue', 'utf8');

const newTemplate = `
<template>
    <div class="h-[calc(100vh-2rem)] flex flex-col space-y-6">
        <!-- Header with "Register Walk-in" button -->
        <div class="flex justify-between items-center bg-ncst-navy-light p-6 rounded-2xl shadow-xl border border-ncst-gold/20 shrink-0">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-white capitalize">Walk-in: {{ defaultRole }}</h1>
                <p class="text-sm text-ncst-gold/70 mt-1">Dispense or lend items directly to walk-in users</p>
            </div>
            <button @click="isRegisterModalOpen = true" class="px-6 py-2.5 bg-ncst-gold hover:bg-yellow-300 text-ncst-navy font-bold rounded-xl transition-all shadow-[0_0_15px_rgba(245,196,0,0.2)] flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Register New {{ defaultRole }}
            </button>
        </div>

        <!-- Main Workspace: Inventory & Checkout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 min-h-0">
            <!-- Left: Inventory Grid -->
            <div class="lg:col-span-2 bg-ncst-navy-light rounded-2xl border border-ncst-gold/15 p-6 flex flex-col h-full overflow-hidden">
                <div class="flex justify-between items-center mb-4 shrink-0">
                    <h2 class="text-lg font-bold text-white">Select Items</h2>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 overflow-y-auto custom-scrollbar flex-1 pr-2 pb-4">
                    <div v-for="resource in resources" :key="resource.id" 
                         @click="addToCart(resource)"
                         class="bg-ncst-navy rounded-xl border border-ncst-gold/10 overflow-hidden hover:border-ncst-gold/40 cursor-pointer group transition-all duration-300 flex flex-col hover:shadow-[0_0_15px_rgba(245,196,0,0.1)]">
                        <div class="h-28 bg-ncst-navy-mid flex items-center justify-center p-2">
                            <img v-if="resource.image_url" :src="resource.image_url" class="max-h-full max-w-full object-contain" />
                            <svg v-else class="w-10 h-10 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <div class="p-3 flex-1 flex flex-col">
                            <h3 class="text-sm font-bold text-white line-clamp-1 group-hover:text-ncst-gold transition-colors">{{ resource.name }}</h3>
                            <p class="text-[10px] text-white/50 mt-1 uppercase font-mono tracking-wider">{{ resource.asset_code }}</p>
                            <div class="mt-auto pt-2 flex items-center justify-between">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-md" :class="resource.type === 'consumable' ? 'bg-orange-500/20 text-orange-400' : 'bg-blue-500/20 text-blue-400'">{{ resource.type }}</span>
                                <span class="text-xs font-bold text-ncst-gold">Stock: {{ resource.stock }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Cart & User Selection -->
            <div class="bg-ncst-navy-light rounded-2xl border border-ncst-gold/15 flex flex-col h-full overflow-hidden">
                <div class="p-6 border-b border-white/5 shrink-0">
                    <h2 class="text-lg font-bold text-white mb-4">Checkout</h2>
                    
                    <div>
                        <label class="block text-xs font-medium text-white/80 mb-1">Select {{ defaultRole }}</label>
                        <select v-model="selectedUser" class="appearance-none rounded-lg relative block w-full px-3 py-2.5 bg-ncst-navy border border-ncst-gold/20 text-white focus:outline-none focus:ring-1 focus:ring-ncst-gold focus:border-ncst-gold sm:text-sm transition-colors cursor-pointer">
                            <option value="" disabled>-- Choose a registered {{ defaultRole }} --</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.school_id }})
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-3">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-white/30 text-center">
                        <svg class="w-12 h-12 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <p class="text-sm">Cart is empty</p>
                        <p class="text-[10px] mt-1">Click items from inventory to add</p>
                    </div>
                    
                    <div v-for="(item, index) in cart" :key="item.id" class="bg-ncst-navy rounded-lg p-3 border border-white/5 flex items-center justify-between group">
                        <div class="min-w-0 flex-1 pr-3">
                            <p class="text-sm font-bold text-white truncate">{{ item.name }}</p>
                            <p class="text-[10px] text-white/50">{{ item.asset_code }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center bg-ncst-navy-light rounded-lg border border-ncst-gold/20">
                                <button @click="item.quantity > 1 ? item.quantity-- : removeFromCart(index)" class="w-6 h-6 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/5 rounded-l-lg">-</button>
                                <span class="text-xs font-bold text-white w-6 text-center">{{ item.quantity }}</span>
                                <button @click="item.quantity < item.stock ? item.quantity++ : null" class="w-6 h-6 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/5 rounded-r-lg" :class="{'opacity-50 cursor-not-allowed': item.quantity >= item.stock}">+</button>
                            </div>
                            <button @click="removeFromCart(index)" class="text-red-400 hover:text-red-300 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-white/5 shrink-0">
                    <button @click="submitWalkInBorrow" :disabled="!selectedUser || cart.length === 0" class="w-full py-3 bg-ncst-gold hover:bg-yellow-300 text-ncst-navy font-bold rounded-xl transition-all shadow-[0_0_15px_rgba(245,196,0,0.2)] hover:shadow-[0_0_20px_rgba(245,196,0,0.4)] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none">
                        Confirm & Process
                    </button>
                </div>
            </div>
        </div>

        <!-- Registration Modal (Popup) -->
        <Transition name="modal">
            <div v-if="isRegisterModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-ncst-navy/80 backdrop-blur-sm">
                <div class="absolute inset-0" @click="isRegisterModalOpen = false"></div>
                
                <div :class="['relative w-full space-y-6 bg-ncst-navy-light p-8 sm:p-10 rounded-2xl shadow-2xl border border-ncst-gold/20 overflow-hidden transition-all duration-700 ease-in-out', currentStep === 3 ? 'max-w-4xl' : 'max-w-md']">
                    <button @click="isRegisterModalOpen = false" class="absolute top-4 right-4 text-white/50 hover:text-white bg-ncst-navy p-2 rounded-full transition-colors z-50">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ncst-gold to-transparent opacity-50" aria-hidden="true"></div>
                    
                    <div class="flex flex-col items-center">
                        <img src="/ncst-logo.png" alt="NCST Logo" class="w-12 h-12 object-contain mb-3">
                        <h2 class="text-center text-2xl font-extrabold text-white tracking-tight">
                            Walk-in Registration
                        </h2>
                        <p class="mt-1 text-center text-xs text-ncst-gold/80">
                            Verify and create an account for Walk-in Users
                        </p>
                    </div>

                    <!-- REPLACEMENT_MARKER_REGISTRATION_FORM_START -->
                    <!-- REPLACEMENT_MARKER_REGISTRATION_FORM_END -->
                </div>
            </div>
        </Transition>
    </div>
</template>
`;

const extractRegex = /<!-- Stepper -->[\s\S]*?<!-- Success Overlay -->/;
const match = content.match(extractRegex);

if (match) {
    let registrationForm = match[0];
    
    // Also include Success Overlay since it is inside the same container
    const successOverlayRegex = /<!-- Success Overlay -->[\s\S]*?<\/div>\s*<\/div>/;
    const successMatch = content.match(successOverlayRegex);
    if(successMatch) {
       const successPart = content.match(/<!-- Success Overlay -->[\s\S]*?<\/div>\s*(?=\s*<\/div>\s*<\/div>\s*<\/template>)/);
       if(successPart) {
           registrationForm += "\n            " + successPart[0];
       } else {
           // Try alternate match
           const successPartAlt = content.match(/<!-- Success Overlay -->[\s\S]*?<\/div>\s*(?=\s*<\/div>\s*<\/div>\s*<\/AuthenticatedLayout>)/);
           if(successPartAlt) {
              registrationForm += "\n            " + successPartAlt[0];
           }
       }
    }

    const finalTemplate = newTemplate.replace('<!-- REPLACEMENT_MARKER_REGISTRATION_FORM_START -->\n                    <!-- REPLACEMENT_MARKER_REGISTRATION_FORM_END -->', registrationForm);

    const oldTemplateRegex = /<template>[\s\S]*?<\/template>/;
    content = content.replace(oldTemplateRegex, finalTemplate);

    fs.writeFileSync('resources/js/Pages/WalkIn.vue', content);
    console.log('Template successfully replaced!');
} else {
    console.error('Could not find Stepper in original file.');
}
