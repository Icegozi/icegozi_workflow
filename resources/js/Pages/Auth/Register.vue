<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Btn from '@/Components/Btn.vue';

const props = defineProps({
    email: { type: String, default: '' },
});

const page = usePage();
const csrf = computed(() => page.props.csrf_token);
const errors = computed(() => page.props.errors || {});
</script>

<template>
    <Head title="Đăng ký tài khoản" />
    <GuestLayout>
        <div class="register-box">
            <div class="card card-outline card-secondary">
                <div class="card-header text-center">
                    <a href="#" class="h1"><b>My</b>App</a>
                </div>
                <div class="card-body">
                    <p class="login-box-msg">Tạo tài khoản mới</p>

                    <div v-for="(msg, key) in errors" :key="key" class="alert alert-danger small p-2">{{ msg }}</div>

                    <form :action="route('register')" method="POST">
                        <input type="hidden" name="_token" :value="csrf">
                        <TextInput type="text" name="name" placeholder="Họ tên" icon="fas fa-user" required />
                        <TextInput type="email" name="email" placeholder="Email"
                            icon="fas fa-envelope" required />
                        <TextInput type="password" name="password" placeholder="Mật khẩu" icon="fas fa-lock" required />
                        <TextInput type="password" name="password_confirmation" placeholder="Nhập lại mật khẩu"
                            icon="fas fa-lock" required />

                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <Btn variant="black" class="btn-block font-weight-bold">Đăng ký</Btn>
                            </div>
                            <div class="col-md-6 d-flex align-items-center justify-content-md-end justify-content-center">
                                <Link :href="route('login.form')" class="text-dark" style="text-decoration: none;">
                                    Tôi đã có tài khoản
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
