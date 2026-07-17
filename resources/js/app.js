import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle';
import $ from 'jquery';

window.$ = $;
window.jQuery = $;

import { createApp, Fragment, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import AppAlertModal from '@/Components/AppAlertModal.vue';
import { showAppAlert } from '@/composables/useAppAlert';

const appName = import.meta.env.VITE_APP_NAME || 'MyApp';
const httpErrorMessages = {
    403: 'Bạn không có quyền thực hiện thao tác này !',
    404: 'Không tìm thấy nội dung này !',
    402: 'Gói dịch vụ không hỗ trợ hoặc đã hết hạn mức .',
    409: 'Hãy reload lại trang để có thông tin mới nhất .',
};

const showHttpError = (status) => {
    const message = httpErrorMessages[status];
    if (message) {
        showAppAlert(message);
    }
};

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        showHttpError(error.response?.status);

        return Promise.reject(error);
    }
);

router.on('invalid', (event) => {
    const status = event.detail.response.status;
    if (!httpErrorMessages[status]) {
        return;
    }

    event.preventDefault();
    showHttpError(status);
});

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
