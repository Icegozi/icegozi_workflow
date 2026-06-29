<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectInput from '@/Components/SelectInput.vue';

const statuses = { active: 'Kích hoạt', inactive: 'Không kích hoạt', banned: 'Bị khóa' };

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

const submit = () => form.put(route('admin.user.update', props.user.id));
</script>

<template>
    <Head title="Sửa người dùng" />
    <AdminLayout>
        <h3>Cập nhật tài khoản</h3>

        <form @submit.prevent="submit">
            <table class="table table-bordered mt-4">
                <tbody>
                    <tr>
                        <th scope="row">Họ và tên</th>
                        <td>
                            <TextInput v-model="form.name" required group-class="mb-0" />
                            <div v-if="form.errors.name" class="text-danger small">{{ form.errors.name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email</th>
                        <td>
                            <TextInput type="email" v-model="form.email" required group-class="mb-0" />
                            <div v-if="form.errors.email" class="text-danger small">{{ form.errors.email }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                            <p class="text-muted"><em>Để trống mật khẩu nếu không muốn thay đổi.</em></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Mật khẩu mới</th>
                        <td>
                            <TextInput type="password" v-model="form.password" placeholder="Nhập mật khẩu mới" group-class="mb-0" />
                            <div v-if="form.errors.password" class="text-danger small">{{ form.errors.password }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Xác nhận mật khẩu mới</th>
                        <td><TextInput type="password" v-model="form.password_confirmation" placeholder="Xác nhận mật khẩu mới" group-class="mb-0" /></td>
                    </tr>
                    <tr><th scope="row" colspan="2"><hr></th></tr>
                    <tr>
                        <th scope="row">Trạng thái</th>
                        <td>
                            <SelectInput v-model="form.status" :options="statuses" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Là quản trị viên</th>
                        <td>
                            <Checkbox id="isAdmin" v-model="form.is_admin" label="Là quản trị viên (Is Admin)" />
                        </td>
                    </tr>
                    <tr><th scope="row">Ngày tạo</th><td>{{ fmt(props.user.created_at) }}</td></tr>
                    <tr><th scope="row">Cập nhật lần cuối</th><td>{{ fmt(props.user.updated_at) }}</td></tr>
                    <tr><th scope="row">Email đã xác thực lúc</th><td>{{ props.user.email_verified_at ? fmt(props.user.email_verified_at) : 'Chưa xác thực' }}</td></tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-end">
                <Btn variant="black" outline class="mr-2" :disabled="form.processing">Lưu thay đổi</Btn>
                <Link :href="route('admin.user.index')" class="btn btn-danger">Hủy</Link>
            </div>
        </form>
    </AdminLayout>
</template>
