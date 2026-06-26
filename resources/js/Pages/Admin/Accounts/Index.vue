<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const statusBadge = (s) => ({
    active: ['badge-primary', 'Kích hoạt'],
    inactive: ['badge-warning', 'Không kích hoạt'],
    banned: ['badge-danger', 'Bị khóa'],
}[s] || ['badge-light', s]);

const fmt = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');

const destroy = (user) => {
    if (confirm(`Xoá người dùng "${user.name}"?`)) {
        router.delete(route('admin.user.destroy', user.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Danh sách tài khoản" />
    <AdminLayout>
        <div class="container">
            <h3 class="mb-4">Danh sách người dùng</h3>

            <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
            <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr class="text-center">
                        <th>ID</th><th>Họ tên</th><th>Email</th><th>Quyền</th>
                        <th>Trạng thái</th><th>Ngày tạo</th><th>Cập nhật</th><th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in props.users.data" :key="u.id">
                        <td class="text-center">{{ u.id }}</td>
                        <td><Link :href="route('admin.user.show', u.id)">{{ u.name }}</Link></td>
                        <td>{{ u.email }}</td>
                        <td class="text-center">
                            <span v-if="u.is_admin" class="badge badge-success">Admin</span>
                            <span v-else class="badge badge-secondary">User</span>
                        </td>
                        <td class="text-center">
                            <span class="badge" :class="statusBadge(u.status)[0]">{{ statusBadge(u.status)[1] }}</span>
                        </td>
                        <td class="text-center">{{ fmt(u.created_at) }}</td>
                        <td class="text-center">{{ fmt(u.updated_at) }}</td>
                        <td class="text-center">
                            <a href="#" class="btn btn-sm btn-danger text-white" @click.prevent="destroy(u)">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <tr v-if="!props.users.data.length">
                        <td colspan="8" class="text-center">Không có người dùng nào.</td>
                    </tr>
                </tbody>
            </table>

            <nav v-if="props.users.links && props.users.links.length > 3" class="d-flex justify-content-end mt-4">
                <ul class="pagination">
                    <li v-for="(link, i) in props.users.links" :key="i" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" class="page-link" :href="link.url" v-html="link.label" preserve-scroll />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </AdminLayout>
</template>
