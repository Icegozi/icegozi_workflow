<script setup>
import { Link } from '@inertiajs/vue3';
import Topbar from '@/Components/Topbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import NavDropdown from '@/Components/NavDropdown.vue';
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <Topbar>
            <li class="nav-item d-none d-sm-inline-block">
                <Link :href="route('admin.dashboard')" class="nav-link">Thống kê</Link>
            </li>
            <NavDropdown label="Quản lý tài khoản">
                <Link :href="route('admin.user.index')" class="dropdown-item">Danh sách</Link>
                <Link :href="route('admin.user.create')" class="dropdown-item">Thêm tài khoản</Link>
            </NavDropdown>
            <li class="nav-item d-none d-sm-inline-block">
                <Link :href="route('admin.template.index')" class="nav-link">Mẫu bảng</Link>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <Link :href="route('admin.status.index')" class="nav-link">Trạng thái</Link>
            </li>
        </Topbar>

        <div class="d-flex flex-grow-1" id="admin-bg">
            <!-- Sidebar -->
            <Sidebar bg-class="bg-light">
                <li class="nav-item">
                    <Link :href="route('admin.dashboard')" class="nav-link">
                        <i class="fas fa-chart-line fa-fw"></i> Thống kê
                    </Link>
                </li>
                <li class="nav-item">
                    <Link :href="route('admin.user.index')" class="nav-link">
                        <i class="fas fa-users fa-fw"></i> Danh sách tài khoản
                    </Link>
                </li>
                <li class="nav-item">
                    <Link :href="route('admin.user.create')" class="nav-link">
                        <i class="fas fa-user-plus fa-fw"></i> Thêm tài khoản
                    </Link>
                </li>
                <li class="nav-item">
                    <Link :href="route('admin.template.index')" class="nav-link">
                        <i class="fas fa-clone fa-fw"></i> Mẫu bảng
                    </Link>
                </li>
                <li class="nav-item">
                    <Link :href="route('admin.status.index')" class="nav-link">
                        <i class="fas fa-flag fa-fw"></i> Trạng thái
                    </Link>
                </li>
            </Sidebar>

            <!-- Content -->
            <div class="content-wrapper flex-grow-1 p-3">
                <div class="cute-border w-100 h-100"><slot /></div>
            </div>
        </div>

        <Footer />
    </div>
</template>

<style scoped>
.content-wrapper {
    flex: 1;
    background-image: none;
    margin: 0;
}
</style>
