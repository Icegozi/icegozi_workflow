import { ref, watch } from 'vue';
import axios from 'axios';

// Gộp sâu: đổ giá trị server đè lên default (giữ default cho khoá server thiếu — vd chart mới thêm sau).
function mergeDeep(base, override) {
    if (Array.isArray(base) || typeof base !== 'object' || base === null) {
        return override === undefined ? base : override;
    }
    const out = { ...base };
    for (const key of Object.keys(base)) {
        if (override && key in override) {
            out[key] = mergeDeep(base[key], override[key]);
        }
    }
    return out;
}

/**
 * Nạp thiết lập biểu đồ của user theo scope ('board' | 'admin'), tự lưu (debounce) khi đổi.
 * @param {string} scope
 * @param {object} defaults
 */
export function useChartSettings(scope, defaults) {
    const settings = ref(JSON.parse(JSON.stringify(defaults)));
    const loaded = ref(false);
    let ready = false;
    let timer = null;

    axios.get(route('chart-settings.show', scope))
        .then(({ data }) => {
            if (data && data.settings) {
                settings.value = mergeDeep(JSON.parse(JSON.stringify(defaults)), data.settings);
            }
        })
        .catch(() => { /* dùng default */ })
        .finally(() => { loaded.value = true; ready = true; });

    watch(settings, () => {
        if (!ready) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            axios.put(route('chart-settings.update', scope), { settings: settings.value }).catch(() => {});
        }, 600);
    }, { deep: true });

    return { settings, loaded };
}
