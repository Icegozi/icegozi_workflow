<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    users: { type: Object, required: true },
});

const columns = [
    { key: 'id', label: 'ID', align: 'center', width: '60px' },
    { key: 'name', label: 'Họ tên' },
    { key: 'email', label: 'Email' },
    { key: 'is_admin', label: 'Quyền', align: 'center', width: '90px' },
    { key: 'status', label: 'Trạng thái', align: 'center', width: '130px' },
    { key: 'created_at', label: 'Ngày tạo', align: 'center' },
    { key: 'updated_at', label: 'Cập nhật', align: 'center' },
];

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

            <DataTable :columns="columns" :rows="props.users.data" empty-text="Không có người dùng nào.">
                <template #cell-name="{ row }">
                    <Link :href="route('admin.user.show', row.id)">{{ row.name }}</Link>
                </template>
                <template #cell-is_admin="{ row }">
                    <span v-if="row.is_admin" class="badge badge-success">Admin</span>
                    <span v-else class="badge badge-secondary">User</span>
                </template>
                <template #cell-status="{ row }">
                    <span class="badge" :class="statusBadge(row.status)[0]">{{ statusBadge(row.status)[1] }}</span>
                </template>
                <template #cell-created_at="{ row }">{{ fmt(row.created_at) }}</template>
                <template #cell-updated_at="{ row }">{{ fmt(row.updated_at) }}</template>
                <template #actions="{ row }">
                    <a href="#" class="btn btn-sm btn-danger text-white" @click.prevent="destroy(row)">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </template>
                <template #footer>
                    <nav v-if="props.users.links && props.users.links.length > 3" class="d-flex justify-content-end mt-4">
                        <ul class="pagination">
                            <li v-for="(link, i) in props.users.links" :key="i" class="page-item"
                                :class="{ active: link.active, disabled: !link.url }">
                                <Link v-if="link.url" class="page-link" :href="link.url" v-html="link.label" preserve-scroll />
                                <span v-else class="page-link" v-html="link.label" />
                            </li>
                        </ul>
                    </nav>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
