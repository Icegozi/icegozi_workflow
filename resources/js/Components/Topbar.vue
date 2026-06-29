<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

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
    background-color: #1c1c1c;
    color: #dee2e6;
    padding: 0.5rem 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
    color: #dee2e6;
    display: flex;
    align-items: center;
}

.main-header .navbar-nav.ml-auto .nav-link {
    display: flex;
    align-items: center;
    padding: 8px;
    color: #dee2e6;
}

.main-header .navbar-nav.ml-auto .nav-link i {
    font-size: 16px;
}

/* Áp cho link bên trái (slot), nút dropdown và link bên phải */
.main-header .nav-link {
    color: #dee2e6;
    font-size: 14px;
    transition: color 0.3s ease, background-color 0.3s ease;
    display: flex;
    align-items: center;
}

.main-header .nav-link:hover {
    color: #ff545a;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 5px;
}
</style>
