import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AuthenticatedLayout from './Layouts/AuthenticatedLayout.vue';

// Reload if restored from browser back-forward cache (bfcache)
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => title ? `${title} - NCST System` : 'NCST System',
    resolve: (name) => {
        const page = resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        page.then((module) => {
            if (!name.startsWith('Auth/') && name !== 'Welcome' && name !== 'LandingPage' && name !== 'PrintFacilityForm') {
                module.default.layout = module.default.layout === undefined ? AuthenticatedLayout : module.default.layout;
            }
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
