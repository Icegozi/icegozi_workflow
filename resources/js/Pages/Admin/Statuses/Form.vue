<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    status: { type: Object, default: null },
});

const isEdit = !!props.status;
const COLORS = ['#6c757d', '#006adc', '#f76808', '#18794e', '#e5484d', '#8e4ec6', '#ffb224'];

const form = useForm({
    name: props.status?.name ?? '',
    key: props.status?.key ?? '',
    color: props.status?.color ?? COLORS[0],
    position: props.status?.position ?? 0,
    is_default: props.status?.is_default ?? false,
    is_completed: props.status?.is_completed ?? false,
});

const submit = () => {
    if (isEdit) form.put(route('admin.status.update', props.status.id));
    else form.post(route('admin.status.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa trạng thái' : 'Thêm trạng thái'" />
    <AdminLayout>
        <h3>{{ isEdit ? 'Sửa trạng thái' : 'Thêm trạng thái' }}</h3>

        <form @submit.prevent="submit" class="mt-3" style="max-width:560px;">
            <div class="form-group">
                <label class="small font-weight-bold">Tên trạng thái</label>
                <TextInput v-model="form.name" required group-class="mb-0" />
                <div v-if="form.errors.name" class="text-danger small">{{ form.errors.name }}</div>
            </div>

            <div class="form-group">
                <label class="small font-weight-bold">Khoá (key)</label>
                <TextInput v-model="form.key" placeholder="Để trống sẽ tự sinh từ tên" group-class="mb-0" />
                <span class="small text-muted">Chỉ chữ, số, gạch dưới/ngang. Dùng cho logic hệ thống.</span>
                <div v-if="form.errors.key" class="text-danger small">{{ form.errors.key }}</div>
            </div>

            <div class="form-group">
                <label class="small font-weight-bold">Màu</label>
                <div class="d-flex align-items-center" style="gap:6px;">
                    <button v-for="c in COLORS" :key="c" type="button" class="color-dot"
                        :class="{ sel: form.color === c }" :style="{ backgroundColor: c }" @click="form.color = c"></button>
                    <input type="text" class="form-control form-control-sm ml-2" style="max-width:120px;" v-model="form.color">
                    <span class="status-badge ml-2" :style="{ color: form.color, borderColor: form.color }">{{ form.name || 'Xem trước' }}</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold">Thứ tự</label>
                    <TextInput type="number" v-model="form.position" group-class="mb-0" />
                </div>
                <div class="form-group col-md-8 d-flex align-items-end">
                    <div>
                        <Checkbox id="isDefault" v-model="form.is_default" label="Mặc định khi tạo task" class="mr-3" />
                        <Checkbox id="isCompleted" v-model="form.is_completed" label="Coi là hoàn thành" />
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <Btn variant="black" outline class="mr-2" :disabled="form.processing">
                    {{ isEdit ? 'Lưu thay đổi' : 'Tạo trạng thái' }}
                </Btn>
                <Link :href="route('admin.status.index')" class="btn btn-danger">Hủy</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.color-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #ccc;
    cursor: pointer;
    padding: 0;
}

.color-dot.sel {
    box-shadow: 0 0 0 2px #333;
}

.status-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 3px 12px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: var(--app-surface);
}
</style>
