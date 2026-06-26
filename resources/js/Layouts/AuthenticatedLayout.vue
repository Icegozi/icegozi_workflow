<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const ownedBoards = computed(() => page.props.ownedBoards || []);

const showBoardsDropdown = ref(false);
const dropdownRef = ref(null);

const onDocClick = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        showBoardsDropdown.value = false;
    }
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
                    <Link href="/" class="nav-link">Trang chủ</Link>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <Link :href="route('user.dashboard')" class="nav-link">Bảng của tôi</Link>
                </li>
                <li class="nav-item dropdown" ref="dropdownRef">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        @click.prevent="showBoardsDropdown = !showBoardsDropdown">Mời thành viên</a>
                    <div class="dropdown-menu show" v-if="showBoardsDropdown"
                        style="max-height: 500px; overflow-y: auto;">
                        <template v-if="ownedBoards.length">
                            <Link v-for="b in ownedBoards" :key="b.id" class="dropdown-item"
                                :href="route('boards.settings', b.id)">
                                <i class="fas fa-cog fa-fw mr-2"></i> {{ b.name }}
                            </Link>
                        </template>
                        <a v-else class="dropdown-item disabled" href="#">Vui lòng tạo thêm bảng!</a>
                    </div>
                </li>
            </ul>

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

        <div class="d-flex flex-grow-1 client-bg">
            <!-- Sidebar -->
            <div class="d-flex flex-column flex-shrink-0 p-3 bg" style="width: 250px;">
                <a class="navbar-brand" href="#">My<span>App</span></a>
                <hr>
                <ul class="nav nav-pills flex-column mb-auto">
                    <li class="nav-item">
                        <Link :href="route('user.dashboard')" class="nav-link active">Bảng của tôi</Link>
                    </li>
                </ul>
            </div>

            <!-- Content -->
            <div class="content-wrapper flex-grow-1 p-3">
                <div class="cute-border w-100 h-100">
                    <slot />
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer id="footer" class="footer">
            <div class="container">
                <div class="hm-footer-copyright">
                    <div class="row">
                        <div class="col-sm-5"><p>© 2025 MyApp - Hà Xuân Phúc</p></div>
                        <div class="col-sm-7">
                            <div class="footer-social">
                                <span><i class="fa fa-envelope"></i> support@myapp.com</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
