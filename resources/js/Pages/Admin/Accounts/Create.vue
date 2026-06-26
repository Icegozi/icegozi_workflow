<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Btn from '@/Components/Btn.vue';

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
        <h3>Thêm tài khoản mới</h3>

        <form @submit.prevent="submit">
            <table class="table table-bordered mt-4">
                <tbody>
                    <tr>
                        <th scope="row">Họ và tên</th>
                        <td>
                            <input type="text" class="form-control" v-model="form.name" required>
                            <div v-if="form.errors.name" class="text-danger small">{{ form.errors.name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email</th>
                        <td>
                            <input type="email" class="form-control" v-model="form.email" required>
                            <div v-if="form.errors.email" class="text-danger small">{{ form.errors.email }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <hr>
                            <p class="text-muted"><em>Nên đặt mật khẩu ≥ 8 ký tự, có chữ hoa, chữ thường và ký tự đặc biệt.</em></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Mật khẩu</th>
                        <td>
                            <input type="password" class="form-control" v-model="form.password" placeholder="Nhập mật khẩu" required>
                            <div v-if="form.errors.password" class="text-danger small">{{ form.errors.password }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Xác nhận mật khẩu</th>
                        <td><input type="password" class="form-control" v-model="form.password_confirmation" placeholder="Xác nhận mật khẩu" required></td>
                    </tr>
                    <tr><th scope="row" colspan="2"><hr></th></tr>
                    <tr>
                        <th scope="row">Trạng thái</th>
                        <td>
                            <select class="form-control" v-model="form.status" required>
                                <option value="active">Kích hoạt</option>
                                <option value="inactive">Không kích hoạt</option>
                                <option value="banned">Bị khóa</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Là quản trị viên</th>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isAdmin" v-model="form.is_admin">
                                <label class="form-check-label" for="isAdmin">Là quản trị viên (Is Admin)</label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-end">
                <Btn variant="black" outline class="mr-2" :disabled="form.processing">Thêm tài khoản</Btn>
                <Link :href="route('admin.user.index')" class="btn btn-danger">Hủy</Link>
            </div>
        </form>
    </AdminLayout>
</template>
