<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import Topbar from '@/Components/Topbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import Footer from '@/Components/Footer.vue';

const page = usePage();

const isRouteActive = (routeName) => {
    void page.url;
    return route().current(routeName);
};
</script>

<template>
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <Topbar></Topbar>

        <div class="d-flex flex-grow-1" id="admin-bg">
            <!-- Sidebar -->
            <Sidebar bg-class="bg-light">
                <li class="nav-item">
                    <Link
                        :href="route('admin.dashboard')"
                        class="nav-link"
                        :class="{ active: isRouteActive('admin.dashboard*') }"
                        :aria-current="isRouteActive('admin.dashboard*') ? 'page' : undefined"
                        title="Thống kê"
                    >
                        <i class="fas fa-chart-line fa-fw"></i>
                        <span class="nav-label">Thống kê</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link
                        :href="route('admin.user.index')"
                        class="nav-link"
                        :class="{ active: isRouteActive('admin.user.*') }"
                        :aria-current="isRouteActive('admin.user.*') ? 'page' : undefined"
                        title="Tài khoản"
                    >
                        <i class="fas fa-users fa-fw"></i>
                        <span class="nav-label">Tài khoản</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link
                        :href="route('admin.template.index')"
                        class="nav-link"
                        :class="{ active: isRouteActive('admin.template.*') }"
                        :aria-current="isRouteActive('admin.template.*') ? 'page' : undefined"
                        title="Mẫu bảng"
                    >
                        <i class="fas fa-clone fa-fw"></i>
                        <span class="nav-label">Mẫu bảng</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link
                        :href="route('admin.status.index')"
                        class="nav-link"
                        :class="{ active: isRouteActive('admin.status.*') }"
                        :aria-current="isRouteActive('admin.status.*') ? 'page' : undefined"
                        title="Trạng thái"
                    >
                        <i class="fas fa-flag fa-fw"></i>
                        <span class="nav-label">Trạng thái</span>
                    </Link>
                </li>
            </Sidebar>

            <!-- Content -->
            <main class="layout-content flex-grow-1 p-3">
                <div class="cute-border w-100 h-100"><slot /></div>
            </main>
        </div>

        <Footer />
    </div>
</template>

<style scoped>
.layout-content {
    flex: 1;
    background-image: none;
    margin: 0;
}
</style>
