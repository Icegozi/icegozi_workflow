<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import DataTable from '@/Components/DataTable.vue';

defineProps({
    templates: { type: Array, default: () => [] },
});

const columns = [
    { key: 'name', label: 'Mẫu', width: '220px' },
    { key: 'columns', label: 'Cột (quy trình)' },
    { key: 'status_ids', label: 'Trạng thái', align: 'center', width: '90px' },
    { key: 'labels', label: 'Nhãn', align: 'center', width: '90px' },
];

const destroy = (tpl) => {
    if (confirm(`Xoá mẫu "${tpl.name}"?`)) {
        router.delete(route('admin.template.destroy', tpl.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Mẫu bảng" />
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Mẫu bảng</h3>
            <Btn :href="route('admin.template.create')" variant="success" icon="fas fa-plus" class="btn-sm">
                Thêm mẫu
            </Btn>
        </div>

        <DataTable :columns="columns" :rows="templates" empty-text="Chưa có mẫu nào.">
            <template #cell-name="{ row }">
                <i class="fas mr-2" :class="row.icon"></i><strong>{{ row.name }}</strong>
                <div class="text-muted small">{{ row.description }}</div>
            </template>
            <template #cell-columns="{ row }">
                <span v-for="c in row.columns" :key="c" class="badge badge-light border mr-1 mb-1">{{ c }}</span>
            </template>
            <template #cell-status_ids="{ row }">{{ (row.status_ids || []).length }}</template>
            <template #cell-labels="{ row }">{{ (row.labels || []).length }}</template>
            <template #actions="{ row }">
                <Btn :href="route('admin.template.edit', row.id)" variant="secondary" outline
                    icon="fas fa-pencil-alt" class="btn-sm mr-1" />
                <Btn type="button" variant="danger" class="btn-sm" @click="destroy(row)">
                    <i class="fas fa-trash-alt"></i>
                </Btn>
            </template>
        </DataTable>
    </AdminLayout>
</template>
