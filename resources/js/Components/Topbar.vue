<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const { theme, toggle: toggleTheme } = useTheme();

const logout = () => {
    if (confirm('Bạn có chắc chắn muốn đăng xuất không?')) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <nav class="main-header navbar navbar-expand border-bottom">
        <!-- Điều hướng bên trái (tuỳ layout) -->
        <ul class="navbar-nav">
            <slot />
        </ul>

        <!-- Người dùng + đăng xuất (dùng chung) -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="#" @click.prevent="toggleTheme"
                    :title="theme === 'dark' ? 'Chuyển sáng' : 'Chuyển tối'">
                    <i :class="theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'"></i>
                </a>
            </li>
            <li class="nav-item d-flex align-items-center">
                <span class="nav-link">{{ user?.name }}</span>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="#" @click.prevent="logout" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
</template>

<!-- Không dùng scoped: các link điều hướng đến từ <slot> (do layout cha render),
     nên cần selector global, giới hạn phạm vi bằng tiền tố .main-header. -->
<style>
.main-header.navbar {
    background-color: var(--app-surface, #ffffff);
    color: var(--app-text, #212529);
    padding: 0.5rem 1rem;
    border-bottom: 2px solid var(--app-accent, #ff545a) !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.main-header .navbar-nav {
    display: flex;
    align-items: center;
}

.main-header .navbar-nav.ml-auto .nav-item {
    margin-left: 15px;
    font-weight: 500;
    color: var(--app-text, #212529);
    display: flex;
    align-items: center;
}

.main-header .navbar-nav.ml-auto .nav-link {
    display: flex;
    align-items: center;
    padding: 8px;
    color: var(--app-text, #212529);
}

.main-header .navbar-nav.ml-auto .nav-link i {
    font-size: 16px;
}

/* Áp cho link bên trái (slot), nút dropdown và link bên phải */
.main-header .nav-link {
    color: var(--app-text, #212529);
    font-size: 14px;
    transition: color 0.3s ease, background-color 0.3s ease;
    display: flex;
    align-items: center;
}

.main-header .nav-link:hover {
    color: var(--app-accent, #ff545a);
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 5px;
}
</style>
