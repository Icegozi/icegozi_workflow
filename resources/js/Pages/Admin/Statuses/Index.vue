<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import DataTable from '@/Components/DataTable.vue';
import { showAppConfirm } from '@/composables/useAppAlert';

defineProps({
    statuses: { type: Array, default: () => [] },
});

const columns = [
    { key: 'position', label: '#', width: '60px' },
    { key: 'name', label: 'Trạng thái' },
    { key: 'is_default', label: 'Mặc định', align: 'center', width: '110px' },
    { key: 'is_completed', label: 'Hoàn thành', align: 'center', width: '120px' },
    { key: 'tasks_count', label: 'Đang dùng', align: 'center', width: '110px' },
];

const destroy = async (s) => {
    const warn = s.tasks_count
        ? `Trạng thái "${s.name}" đang gắn với ${s.tasks_count} công việc. Xoá sẽ gỡ trạng thái khỏi các công việc đó. Tiếp tục?`
        : `Xoá trạng thái "${s.name}"?`;
    if (await showAppConfirm(warn, 'danger')) {
        router.delete(route('admin.status.destroy', s.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Trạng thái" />
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Trạng thái (dùng chung)</h3>
            <Btn :href="route('admin.status.create')" variant="success" icon="fas fa-plus" class="btn-sm">
                Thêm trạng thái
            </Btn>
        </div>

        <DataTable :columns="columns" :rows="statuses" empty-text="Chưa có trạng thái nào.">
            <template #cell-name="{ row }">
                <span class="status-badge" :style="{ color: row.color, borderColor: row.color }">{{ row.name }}</span>
                <code class="ml-2 small text-muted">{{ row.key }}</code>
            </template>
            <template #cell-is_default="{ row }">
                <i v-if="row.is_default" class="fas fa-check text-success"></i>
                <span v-else class="text-muted">—</span>
            </template>
            <template #cell-is_completed="{ row }">
                <i v-if="row.is_completed" class="fas fa-check text-success"></i>
                <span v-else class="text-muted">—</span>
            </template>
            <template #actions="{ row }">
                <Btn :href="route('admin.status.edit', row.id)" variant="secondary" outline
                    icon="fas fa-pencil-alt" class="btn-sm mr-1" />
                <Btn type="button" variant="danger" class="btn-sm" @click="destroy(row)">
                    <i class="fas fa-trash-alt"></i>
                </Btn>
            </template>
        </DataTable>
    </AdminLayout>
</template>

<style scoped>
.status-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 3px 12px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: var(--app-surface);
}
</style>
