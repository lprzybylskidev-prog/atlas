import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

createInertiaApp({
    title: (title) => (title ? `${title} - Atlas` : 'Atlas'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        if (page === undefined) {
            throw new Error(`Page not found: ${name}`);
        }

        return page.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});
