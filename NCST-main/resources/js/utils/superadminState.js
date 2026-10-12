import { ref } from 'vue';

// Initialize from URL query parameter 'tab' if present, otherwise default to 'dashboard'
const initialTab = (typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null) || 'dashboard';

export const superadminActiveTab = ref(initialTab);

export const setSuperadminTab = (tab, router, currentUrl) => {
    superadminActiveTab.value = tab;
    
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }

    if (currentUrl && !currentUrl.startsWith('/superadmin') && router) {
        router.visit(`/superadmin?tab=${tab}`);
    }
};
