<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccountForm from '@/Components/AccountForm.vue';
import { makeSocialForm } from '@/composables/useSocialLinks';

const props = defineProps({
    user: { type: Object, required: true },
});

const fmt = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');

const form = useForm({
    _method: 'put',
    id: props.user.id,
    name: props.user.name,
    username: props.user.username || '',
    email: props.user.email,
    avatar: null,
    social: makeSocialForm(props.user.social),
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

const submit = () => form.post(route('admin.user.update', props.user.id));
</script>

<template>
    <Head title="Sửa người dùng" />
    <AdminLayout>
        <h3 class="mb-4">Cập nhật tài khoản</h3>

        <AccountForm :form="form" is-edit :meta="meta" :current-avatar="props.user.avatar_url"
            submit-label="Lưu thay đổi" :cancel-href="route('admin.user.index')" @submit="submit" />
    </AdminLayout>
</template>
