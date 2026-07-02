<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AccountForm from '@/Components/AccountForm.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    is_admin: false,
});

const submit = () => form.post(route('admin.user.store'));
</script>

<template>
    <Head title="Thêm người dùng" />
    <AdminLayout>
        <h3 class="mb-4">Thêm tài khoản mới</h3>

        <AccountForm :form="form" submit-label="Thêm tài khoản"
            :cancel-href="route('admin.user.index')" @submit="submit" />
    </AdminLayout>
</template>
