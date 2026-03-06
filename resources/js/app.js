import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import MainLayout from './Layouts/MainLayout.vue';
import '../css/app.css';

createInertiaApp({
    title: (title) => title ? `${title} — Numen` : 'Numen by byte5.labs',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        // Only apply MainLayout if the page hasn't explicitly set a layout
        // Pages with `layout: null` or `layout: false` opt out of the default layout
        if (page.default.layout === undefined) {
            page.default.layout = MainLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#6366f1',
        showSpinner: true,
    },
});
