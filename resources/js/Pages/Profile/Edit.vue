<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import FormField from '@/Components/FormField.vue';
import Btn from '@/Components/Btn.vue';
import { SOCIAL_PLATFORMS, makeSocialForm, avatarSrc } from '@/composables/useSocialLinks';

const props = defineProps({
    profile: { type: Object, required: true },
});

const page = usePage();
// User thường dùng layout người dùng; admin dùng layout quản trị.
const layout = computed(() => (props.profile.is_admin ? AdminLayout : AuthenticatedLayout));

const form = useForm({
    name: props.profile.name,
    username: props.profile.username || '',
    email: props.profile.email,
    avatar: null,
    social: makeSocialForm(props.profile.social),
    password: '',
    password_confirmation: '',
});

// ---- Ảnh đại diện + xem trước ----
const preview = ref(props.profile.avatar_url);
const fileInput = ref(null);

const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.avatar = file;
    preview.value = URL.createObjectURL(file);
};
const pickAvatar = () => fileInput.value?.click();

// Sau khi lưu, back() nạp lại props -> đồng bộ ảnh mới (khi không có file đang chờ).
watch(() => props.profile.avatar_url, (url) => {
    if (!form.avatar) preview.value = url;
});

const avatarDisplay = computed(() => avatarSrc(preview.value, props.profile.email, 160));

const submit = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password', 'password_confirmation');
            form.avatar = null;
        },
    });
};
</script>

<template>
    <Head title="Hồ sơ cá nhân" />
    <component :is="layout">
        <div class="profile-page">
            <h3 class="mb-1">Hồ sơ cá nhân</h3>
            <p class="text-muted small mb-4">Cập nhật ảnh đại diện, thông tin đăng nhập và liên kết mạng xã hội.</p>

            <div v-if="form.recentlySuccessful" class="alert alert-success py-2 small">
                <i class="fas fa-check-circle mr-1"></i>Đã lưu thay đổi.
            </div>

            <form @submit.prevent="submit">
                <div class="row">
                    <!-- Ảnh đại diện -->
                    <div class="col-12 col-lg-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body text-center">
                                <h6 class="section-title mb-3"><i class="fas fa-image mr-2"></i>Ảnh đại diện</h6>
                                <img :src="avatarDisplay" alt="avatar" class="avatar-preview mb-3">
                                <div>
                                    <input ref="fileInput" type="file" accept="image/*" class="d-none"
                                        @change="onAvatarChange">
                                    <Btn type="button" variant="black" outline icon="fas fa-upload"
                                        class="btn-sm" @click="pickAvatar">Chọn ảnh</Btn>
                                </div>
                                <p class="text-muted small mt-2 mb-0">JPG/PNG/WEBP, tối đa 2MB.</p>
                                <div v-if="form.errors.avatar" class="invalid-feedback d-block small">
                                    {{ form.errors.avatar }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Thông tin -->
                    <div class="col-12 col-lg-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="section-title mb-3"><i class="fas fa-id-card mr-2"></i>Thông tin tài khoản</h6>
                                <div class="row">
                                    <FormField label="Họ và tên" :error="form.errors.name" class="col-12 col-md-6">
                                        <TextInput v-model="form.name" icon="fas fa-user" required group-class="mb-0" />
                                    </FormField>
                                    <FormField label="Tên đăng nhập" :error="form.errors.username" class="col-12 col-md-6">
                                        <TextInput v-model="form.username" icon="fas fa-at" required group-class="mb-0"
                                            placeholder="chữ, số, _ -" />
                                    </FormField>
                                    <FormField label="Email" :error="form.errors.email" class="col-12">
                                        <TextInput type="email" v-model="form.email" icon="fas fa-envelope" required group-class="mb-0" />
                                    </FormField>
                                </div>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-circle-info mr-1"></i>Bạn có thể đăng nhập bằng
                                    <strong>tên đăng nhập</strong> hoặc <strong>email</strong>.
                                </p>

                                <hr class="my-3" style="opacity:.15;">

                                <!-- Mạng xã hội -->
                                <h6 class="section-title mb-3"><i class="fas fa-share-nodes mr-2"></i>Mạng xã hội</h6>
                                <div class="row">
                                    <FormField v-for="p in SOCIAL_PLATFORMS" :key="p.key" :label="p.label"
                                        :error="form.errors[`social.${p.key}`]" class="col-12 col-md-6">
                                        <TextInput type="url" v-model="form.social[p.key]" :icon="p.icon"
                                            :placeholder="p.placeholder" group-class="mb-0" />
                                    </FormField>
                                </div>

                                <hr class="my-3" style="opacity:.15;">

                                <!-- Đổi mật khẩu -->
                                <h6 class="section-title mb-1"><i class="fas fa-key mr-2"></i>Đổi mật khẩu</h6>
                                <p class="text-muted small mb-3">Để trống nếu không muốn đổi.</p>
                                <div class="row">
                                    <FormField label="Mật khẩu mới" :error="form.errors.password" class="col-12 col-md-6">
                                        <TextInput type="password" v-model="form.password" icon="fas fa-lock"
                                            placeholder="••••••••" group-class="mb-0" />
                                    </FormField>
                                    <FormField label="Xác nhận mật khẩu" class="col-12 col-md-6">
                                        <TextInput type="password" v-model="form.password_confirmation" icon="fas fa-lock"
                                            placeholder="••••••••" group-class="mb-0" />
                                    </FormField>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <Btn variant="black" icon="fas fa-save" :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </Btn>
                </div>
            </form>
        </div>
    </component>
</template>

<style scoped>
.profile-page {
    max-width: 1000px;
    margin: 0 auto;
}

.section-title {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--app-accent, #663300);
}

.avatar-preview {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--app-border, #e4e6ea);
}
</style>
