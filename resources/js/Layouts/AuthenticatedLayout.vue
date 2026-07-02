<script setup>
import { Link } from '@inertiajs/vue3';
import Topbar from '@/Components/Topbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <Topbar></Topbar>

        <div class="d-flex flex-grow-1 client-bg">
            <!-- Sidebar -->
            <Sidebar>
                <li class="nav-item">
                    <Link :href="route('user.dashboard')" class="nav-link active">
                        <i class="fas fa-columns fa-fw"></i> Bảng của tôi
                    </Link>
                </li>
                <li class="nav-item">
                    <Link :href="route('my-tasks.index')" class="nav-link">
                        <i class="fas fa-user-check fa-fw"></i> Task của tôi
                    </Link>
                </li>
            </Sidebar>

            <!-- Content -->
            <div class="content-wrapper flex-grow-1 p-3">
                <div class="cute-border w-100 h-100">
                    <slot />
                </div>
            </div>
        </div>

        <!-- Footer -->
        <Footer />
    </div>
</template>

<style scoped>
.content-wrapper {
    flex: 1;
    /* min-width:0 cho phép con (vd bảng Kanban) cuộn ngang trong vùng này
       thay vì nong rộng cả trang ra ngoài viewport */
    min-width: 0;
    background-image: none;
    margin: 0;
}
</style>
