<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Topbar from '@/Components/Topbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import NavDropdown from '@/Components/NavDropdown.vue';
import Footer from '@/Components/Footer.vue';

const page = usePage();
const ownedBoards = computed(() => page.props.ownedBoards || []);
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
                <NavDropdown label="Mời thành viên" menu-style="max-height: 500px; overflow-y: auto;">
                    <template v-if="ownedBoards.length">
                        <Link v-for="b in ownedBoards" :key="b.id" class="dropdown-item"
                            :href="route('boards.settings', b.id)">
                            <i class="fas fa-cog fa-fw"></i> {{ b.name }}
                        </Link>
                    </template>
                    <a v-else class="dropdown-item disabled" href="#">Vui lòng tạo thêm bảng!</a>
                </NavDropdown>
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
