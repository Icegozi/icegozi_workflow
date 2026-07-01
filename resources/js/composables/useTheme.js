import { ref } from 'vue';

// Chế độ giao diện dark/light. Đồng bộ với data-theme trên <html> và lưu localStorage.
// Script inline trong app.blade.php đã áp theme trước khi Vue mount (chống nháy màu);
// composable này đọc lại trạng thái đó và cho phép chuyển đổi lúc chạy.

const STORAGE_KEY = 'app-theme';

function readInitial() {
    const fromDom = document.documentElement.getAttribute('data-theme');
    if (fromDom === 'dark' || fromDom === 'light') return fromDom;

    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'dark' || saved === 'light') return saved;

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

// ref dùng chung (module-level) để mọi component thấy cùng một trạng thái.
const theme = ref(readInitial());

function apply(value) {
    document.documentElement.setAttribute('data-theme', value);
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {
        // bỏ qua nếu localStorage bị chặn
    }
}

export function useTheme() {
    const setTheme = (value) => {
        theme.value = value === 'dark' ? 'dark' : 'light';
        apply(theme.value);
    };

    const toggle = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');

    return { theme, setTheme, toggle };
}
