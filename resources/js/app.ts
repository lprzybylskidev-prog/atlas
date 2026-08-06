import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import { registerNetworkHandling } from './Services/networkHandling';
import { registerRealtimeEvents } from './Services/realtimeEvents';

createInertiaApp({
    title: (title) => (title ? `${title} - Atlas` : 'Atlas'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];

        if (page === undefined) {
            throw new Error(`Page not found: ${name}`);
        }

        return page().then((module) => module.default);
    },
    setup({ el, App, props, plugin }) {
        registerNetworkHandling();
        registerRealtimeEvents();

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});
