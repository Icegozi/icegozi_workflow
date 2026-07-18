<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import Topbar from '@/Components/Topbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import Footer from '@/Components/Footer.vue';

const page = usePage();

const isRouteActive = (routeName) => {
    // Đọc URL để class active được cập nhật sau mỗi lần Inertia điều hướng.
    void page.url;
    return route().current(routeName);
};

// Các trang board và task nằm trong board vẫn thuộc khu vực “Bảng của tôi”.
const isBoardsActive = () => (
    isRouteActive('user.dashboard')
    || isRouteActive('boards.*')
    || isRouteActive('tasks.edit')
    || isRouteActive('tasks.permalink')
);
</script>

<template>
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <Topbar></Topbar>

        <div class="app-shell-body d-flex flex-grow-1 client-bg">
            <!-- Sidebar -->
            <Sidebar>
                <li class="nav-item">
                    <Link
                        :href="route('my-tasks.index')"
                        class="nav-link"
                        :class="{ active: isRouteActive('my-tasks.*') }"
                        :aria-current="isRouteActive('my-tasks.*') ? 'page' : undefined"
                        title="Task của tôi"
                    >
                        <i class="fas fa-user-check fa-fw"></i>
                        <span class="nav-label">Task của tôi</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link
                        :href="route('user.dashboard')"
                        class="nav-link"
                        :class="{ active: isBoardsActive() }"
                        :aria-current="isBoardsActive() ? 'page' : undefined"
                        title="Bảng của tôi"
                    >
                        <i class="fas fa-columns fa-fw"></i>
                        <span class="nav-label">Bảng của tôi</span>
                    </Link>
                </li>
            </Sidebar>

            <!-- Content -->
            <main class="layout-content app-main-content flex-grow-1 p-3">
                <div class="cute-border w-100 h-100">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Footer -->
        <Footer />
    </div>
</template>

<style scoped>
.layout-content {
    flex: 1;
    /* min-width:0 cho phép con (vd bảng Kanban) cuộn ngang trong vùng này
       thay vì nong rộng cả trang ra ngoài viewport */
    min-width: 0;
    background-image: none;
    margin: 0;
}
</style>
