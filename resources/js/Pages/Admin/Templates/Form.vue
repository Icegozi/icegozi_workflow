<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    template: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
});

const isEdit = !!props.template;
const LABEL_COLORS = ['#e5484d', '#f76808', '#ffb224', '#18794e', '#006adc', '#8e4ec6', '#7a869a'];

const form = useForm({
    name: props.template?.name ?? '',
    icon: props.template?.icon ?? 'fa-columns',
    description: props.template?.description ?? '',
    position: props.template?.position ?? 0,
    columns: props.template?.columns?.length ? [...props.template.columns] : [''],
    status_ids: props.template?.status_ids ? [...props.template.status_ids] : [],
    labels: props.template?.labels ? props.template.labels.map((l) => ({ ...l })) : [],
});

const addColumn = () => form.columns.push('');
const removeColumn = (i) => form.columns.splice(i, 1);

const toggleStatus = (id) => {
    const i = form.status_ids.indexOf(id);
    if (i === -1) form.status_ids.push(id); else form.status_ids.splice(i, 1);
};

const addLabel = () => form.labels.push({ name: '', color: LABEL_COLORS[0] });
const removeLabel = (i) => form.labels.splice(i, 1);

const submit = () => {
    if (isEdit) form.put(route('admin.template.update', props.template.id));
    else form.post(route('admin.template.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa mẫu bảng' : 'Thêm mẫu bảng'" />
    <AdminLayout>
        <h3>{{ isEdit ? 'Sửa mẫu bảng' : 'Thêm mẫu bảng' }}</h3>

        <form @submit.prevent="submit" class="mt-3" style="max-width:760px;">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label class="small font-weight-bold">Tên mẫu</label>
                    <TextInput v-model="form.name" required group-class="mb-0" />
                    <div v-if="form.errors.name" class="text-danger small">{{ form.errors.name }}</div>
                </div>
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold">Icon (FontAwesome)</label>
                    <TextInput v-model="form.icon" placeholder="fa-columns" group-class="mb-0" />
                    <span class="small text-muted"><i class="fas mr-1" :class="form.icon"></i>xem trước</span>
                </div>
            </div>

            <div class="form-group">
                <label class="small font-weight-bold">Mô tả</label>
                <TextInput v-model="form.description" group-class="mb-0" />
            </div>

            <!-- Cột (quy trình) -->
            <div class="form-group">
                <label class="small font-weight-bold">Cột — quy trình triển khai</label>
                <div v-for="(c, i) in form.columns" :key="i" class="input-group input-group-sm mb-1">
                    <div class="input-group-prepend"><span class="input-group-text">{{ i + 1 }}</span></div>
                    <input type="text" class="form-control" v-model="form.columns[i]" placeholder="Tên cột..." maxlength="255">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-danger" @click="removeColumn(i)"
                            :disabled="form.columns.length <= 1">&times;</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light" @click="addColumn"><i class="fas fa-plus mr-1"></i>Thêm cột</button>
                <div v-if="form.errors.columns" class="text-danger small">{{ form.errors.columns }}</div>
            </div>

            <!-- Trạng thái áp dụng (tập con global) -->
            <div class="form-group">
                <label class="small font-weight-bold">Trạng thái áp dụng</label>
                <div class="d-flex flex-wrap" style="gap:6px;">
                    <button v-for="s in statuses" :key="s.id" type="button"
                        class="status-pick" :class="{ active: form.status_ids.includes(s.id) }"
                        :style="{ color: s.color, borderColor: s.color }" @click="toggleStatus(s.id)">
                        <i v-if="form.status_ids.includes(s.id)" class="fas fa-check mr-1"></i>{{ s.name }}
                    </button>
                    <span v-if="!statuses.length" class="text-muted small">Chưa có trạng thái global nào.</span>
                </div>
            </div>

            <!-- Nhãn -->
            <div class="form-group">
                <label class="small font-weight-bold">Nhãn</label>
                <div v-for="(l, i) in form.labels" :key="i" class="d-flex align-items-center mb-1" style="gap:6px;">
                    <input type="text" class="form-control form-control-sm" style="max-width:220px;" v-model="l.name" placeholder="Tên nhãn..." maxlength="255">
                    <button v-for="c in LABEL_COLORS" :key="c" type="button" class="color-dot"
                        :class="{ sel: l.color === c }" :style="{ backgroundColor: c }" @click="l.color = c"></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLabel(i)">&times;</button>
                </div>
                <button type="button" class="btn btn-sm btn-light" @click="addLabel"><i class="fas fa-plus mr-1"></i>Thêm nhãn</button>
            </div>

            <div class="form-group" style="max-width:160px;">
                <label class="small font-weight-bold">Thứ tự</label>
                <TextInput type="number" v-model="form.position" group-class="mb-0" />
            </div>

            <div class="d-flex justify-content-end">
                <Btn variant="black" outline class="mr-2" :disabled="form.processing">
                    {{ isEdit ? 'Lưu thay đổi' : 'Tạo mẫu' }}
                </Btn>
                <Btn :href="route('admin.template.index')" variant="danger">Hủy</Btn>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.status-pick {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 20px;
    padding: 3px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    opacity: 0.7;
}

.status-pick.active {
    opacity: 1;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.12) inset;
}

.color-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--app-surface);
    box-shadow: 0 0 0 1px var(--app-border);
    cursor: pointer;
    padding: 0;
}

.color-dot.sel {
    box-shadow: 0 0 0 2px var(--app-text);
}
</style>
