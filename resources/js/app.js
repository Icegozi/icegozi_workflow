import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle';
import $ from 'jquery';

window.$ = $;
window.jQuery = $;

import { createApp, Fragment, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import AppAlertModal from '@/Components/AppAlertModal.vue';

const appName = import.meta.env.VITE_APP_NAME || 'MyApp';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, [
                h(App, props),
                h(AppAlertModal),
            ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0d6efd',
    },
});
