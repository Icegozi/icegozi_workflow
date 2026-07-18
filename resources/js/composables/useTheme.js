import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const theme = ref(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

const systemTheme = () => (
    window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'
);

const apply = (value) => {
    document.documentElement.setAttribute('data-theme', value);
};

export function useTheme() {
    const page = usePage();
    const accountTheme = computed(() => page.props.auth?.user?.theme);

    const syncTheme = (value) => {
        theme.value = value === 'dark' || value === 'light' ? value : systemTheme();
        apply(theme.value);
    };

    syncTheme(accountTheme.value);
    watch(accountTheme, syncTheme);

    const setTheme = async (value) => {
        const nextTheme = value === 'dark' ? 'dark' : 'light';
        const previousTheme = theme.value;

        syncTheme(nextTheme);

        try {
            const { data } = await axios.put(route('profile.theme.update'), { theme: nextTheme });
            page.props.auth.user.theme = data.theme;
        } catch (error) {
            syncTheme(previousTheme);
            throw error;
        }
    };

    const toggle = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');

    return { theme, setTheme, toggle };
}
