<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const showUserMenu = ref(false);
const menuRef = ref(null);
const onDocClick = (e) => {
    if (menuRef.value && !menuRef.value.contains(e.target)) showUserMenu.value = false;
};
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

const logout = () => {
    if (confirm('Bạn có chắc chắn muốn đăng xuất không?')) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <nav class="main-header navbar navbar-expand border-bottom">
            <ul class="navbar-nav">
                <li class="nav-item d-none d-sm-inline-block">
                    <Link :href="route('admin.dashboard')" class="nav-link">Thống kê</Link>
                </li>
                <li class="nav-item dropdown" ref="menuRef">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        @click.prevent="showUserMenu = !showUserMenu">Quản lý tài khoản</a>
                    <div class="dropdown-menu show" v-if="showUserMenu">
                        <Link :href="route('admin.user.index')" class="dropdown-item">Danh sách</Link>
                        <Link :href="route('admin.user.create')" class="dropdown-item">Thêm tài khoản</Link>
                    </div>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item d-flex align-items-center"><span class="nav-link">{{ user?.name }}</span></li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="#" @click.prevent="logout" title="Đăng xuất">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="d-flex flex-grow-1" id="admin-bg">
            <!-- Sidebar -->
            <div class="d-flex flex-column flex-shrink-0 p-3 bg-light" style="width: 250px;">
                <a class="navbar-brand" href="#">My<span>App</span></a>
                <hr>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item"><Link :href="route('admin.dashboard')" class="nav-link">Thống kê</Link></li>
                    <li class="nav-item"><Link :href="route('admin.user.index')" class="nav-link">Danh sách tài khoản</Link></li>
                    <li class="nav-item"><Link :href="route('admin.user.create')" class="nav-link">Thêm tài khoản</Link></li>
                </ul>
            </div>

            <!-- Content -->
            <div class="content-wrapper flex-grow-1 p-3">
                <div class="cute-border w-100 h-100"><slot /></div>
            </div>
        </div>

        <footer id="footer" class="footer">
            <div class="container">
                <div class="hm-footer-copyright">
                    <div class="row">
                        <div class="col-sm-5"><p>© 2025 MyApp - Hà Xuân Phúc</p></div>
                        <div class="col-sm-7"><div class="footer-social-admin"><span><i class="fa fa-envelope"></i> support@myapp.com</span></div></div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
