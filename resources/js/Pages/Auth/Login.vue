<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Btn from '@/Components/Btn.vue';

const page = usePage();
const csrf = computed(() => page.props.csrf_token);
const errors = computed(() => page.props.errors || {});
</script>

<template>
    <Head title="Đăng nhập" />
    <GuestLayout>
        <div class="login-box">
            <div class="card card-outline card-secondary">
                <div class="card-header text-center">
                    <Link href="/" class="h1 text-decoration-none text-black mb-0">
                        My<span class="text-danger">App</span>
                    </Link>
                </div>
                <div class="card-body login-card-body">
                    <p class="login-box-msg">Đăng nhập để bắt đầu phiên làm việc</p>

                    <div v-if="errors.email" class="alert alert-danger small p-2">{{ errors.email }}</div>

                    <form :action="route('login')" method="POST">
                        <input type="hidden" name="_token" :value="csrf">
                        <TextInput type="email" name="email" placeholder="Email" icon="fas fa-envelope" required autofocus />
                        <TextInput type="password" name="password" placeholder="Mật khẩu" icon="fas fa-lock" required />

                        <div class="row">
                            <div class="col-7">
                                <Checkbox id="remember" name="remember" value="1" label="Ghi nhớ đăng nhập" />
                            </div>
                            <div class="col-5 text-end">
                                <Btn variant="black" class="btn-block font-weight-bold">Đăng nhập</Btn>
                            </div>
                        </div>
                    </form>

                    <p class="mb-0 mt-3">
                        <Link :href="route('register.form')" class="text-dark" style="text-decoration: none;">
                            Chưa có tài khoản? Đăng ký
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
<style>   
.app-sidebar .sidebar-brand span {
    color: var(--sb-accent);
}
</style>
