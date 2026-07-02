<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccountForm from '@/Components/AccountForm.vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const fmt = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');

const form = useForm({
    id: props.user.id,
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    status: props.user.status,
    is_admin: !!props.user.is_admin,
});

const meta = computed(() => ({
    created_at: fmt(props.user.created_at),
    updated_at: fmt(props.user.updated_at),
    email_verified_at: props.user.email_verified_at ? fmt(props.user.email_verified_at) : null,
}));

const submit = () => form.put(route('admin.user.update', props.user.id));
</script>

<template>
    <Head title="Sửa người dùng" />
    <AdminLayout>
        <h3 class="mb-4">Cập nhật tài khoản</h3>

        <AccountForm :form="form" is-edit :meta="meta" submit-label="Lưu thay đổi"
            :cancel-href="route('admin.user.index')" @submit="submit" />
    </AdminLayout>
</template>
