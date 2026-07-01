<script setup>
import { Link } from '@inertiajs/vue3';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectInput from '@/Components/SelectInput.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    form: { type: Object, required: true },        // instance useForm
    isEdit: { type: Boolean, default: false },
    meta: { type: Object, default: null },          // {created_at, updated_at, email_verified_at} đã format
    submitLabel: { type: String, default: 'Lưu' },
    cancelHref: { type: String, default: null },
});
const emit = defineEmits(['submit']);

const statuses = { active: 'Kích hoạt', inactive: 'Không kích hoạt', banned: 'Bị khóa' };
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <div class="card shadow-sm form-card">
            <div class="card-body">
                
                <!-- THÔNG TIN CƠ BẢN -->
                <h6 class="section-title mb-3"><i class="fas fa-id-card mr-2"></i>Thông tin cơ bản</h6>
                <div class="row">
                    <FormField label="Họ và tên" :error="props.form.errors.name" class="col-12 col-md-6 mb-3">
                        <TextInput v-model="props.form.name" icon="fas fa-user" required group-class="mb-0" />
                    </FormField>
                    <FormField label="Email" :error="props.form.errors.email" class="col-12 col-md-6 mb-3">
                        <TextInput type="email" v-model="props.form.email" icon="fas fa-envelope" required group-class="mb-0" />
                    </FormField>
                </div>

                <hr class="my-3 text-muted" style="opacity: 0.2;">

                <!-- MẬT KHẨU -->
                <h6 class="section-title mb-1"><i class="fas fa-key mr-2"></i>Mật khẩu</h6>
                <p class="text-muted small mb-3">
                    {{ isEdit
                        ? 'Để trống nếu không muốn đổi mật khẩu.'
                        : 'Nên ≥ 8 ký tự, gồm chữ hoa, chữ thường và ký tự đặc biệt.' }}
                </p>
                <div class="row">
                    <FormField :label="isEdit ? 'Mật khẩu mới' : 'Mật khẩu'" :error="props.form.errors.password" class="col-12 col-md-6 mb-3">
                        <TextInput type="password" v-model="props.form.password" icon="fas fa-lock"
                            :required="!isEdit" placeholder="••••••••" group-class="mb-0" />
                    </FormField>
                    <FormField label="Xác nhận mật khẩu" class="col-12 col-md-6 mb-3">
                        <TextInput type="password" v-model="props.form.password_confirmation" icon="fas fa-lock"
                            :required="!isEdit" placeholder="••••••••" group-class="mb-0" />
                    </FormField>
                </div>

                <hr class="my-3 text-muted" style="opacity: 0.2;">

                <!-- PHÂN QUYỀN & TRẠNG THÁI -->
                <h6 class="section-title mb-3"><i class="fas fa-user-shield mr-2"></i>Phân quyền &amp; trạng thái</h6>
                <div class="row align-items-center">
                    <FormField label="Trạng thái" :error="props.form.errors.status" class="col-12 col-md-6 mb-3 mb-md-0">
                        <SelectInput v-model="props.form.status" :options="statuses" required />
                    </FormField>
                    <div class="col-12 col-md-6">
                        <div class="admin-toggle p-2 border rounded mt-4">
                            <Checkbox id="isAdmin" v-model="props.form.is_admin" label="Là quản trị viên (Admin)" class="mb-0" />
                        </div>
                    </div>
                </div>

                <!-- THÔNG TIN HỆ THỐNG (Chỉ hiện khi Edit) -->
                <template v-if="isEdit && meta">
                    <hr class="my-4 text-muted" style="opacity: 0.2;">
                    <h6 class="section-title mb-3"><i class="fas fa-circle-info mr-2"></i>Thông tin hệ thống</h6>
                    <div class="row bg-light rounded p-3 mx-0">
                        <div class="col-12 col-sm-4 mb-2 mb-sm-0">
                            <span class="d-block text-muted small">Ngày tạo</span>
                            <strong>{{ meta.created_at || '—' }}</strong>
                        </div>
                        <div class="col-12 col-sm-4 mb-2 mb-sm-0">
                            <span class="d-block text-muted small">Cập nhật lần cuối</span>
                            <strong>{{ meta.updated_at || '—' }}</strong>
                        </div>
                        <div class="col-12 col-sm-4">
                            <span class="d-block text-muted small">Xác thực email</span>
                            <strong>{{ meta.email_verified_at || 'Chưa xác thực' }}</strong>
                        </div>
                    </div>
                </template>
                
            </div>
        </div>

        <!-- NÚT HÀNH ĐỘNG -->
        <div class="d-flex flex-wrap justify-content-end mt-4 gap-2">
            <Btn variant="black" outline class="mr-2 mb-2 mb-sm-0" :disabled="props.form.processing">
                {{ submitLabel }}
            </Btn>
            <Link v-if="cancelHref" :href="cancelHref" class="btn btn-danger mb-2 mb-sm-0">
                Hủy
            </Link>
        </div>
    </form>
</template>

<style scoped>
/* Khung ngoài của form */
.form-card {
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 12px;
}

/* Tiêu đề các phần */
.section-title {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--app-accent, #663300);
    margin-bottom: 12px;
}

/* Khu vực checkbox Admin */
.admin-toggle {
    background: rgba(127, 127, 127, 0.06);
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 8px;
    padding: 8px 12px;
    /* Căn giữa checkbox theo chiều dọc để đồng bộ với input bên cạnh */
    display: flex;
    align-items: center;
    height: 100%; 
    min-height: 42px; 
}

/* Lưới thông tin hệ thống */
.meta-grid {
    display: grid;
    /* Nới rộng grid lên breakpoint của tablet (md) để tối ưu hiển thị */
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.meta-grid > div {
    display: flex;
    flex-direction: column;
    background: rgba(127, 127, 127, 0.05);
    border-radius: 8px;
    padding: 10px 12px;
}

.meta-grid span {
    font-size: 0.72rem;
    color: #8993a4;
    margin-bottom: 4px;
}

.meta-grid strong {
    font-size: 0.85rem;
    word-break: break-word; /* Tránh tràn chữ nếu email/ngày tháng quá dài trên mobile */
}

/* Ép các nút bấm full chiều ngang trên màn hình điện thoại nhỏ */
@media (max-width: 575.98px) {
    .meta-grid {
        grid-template-columns: 1fr;
    }
    
    .admin-toggle {
        margin-top: 8px; /* Tạo khoảng cách với input phía trên khi rớt dòng */
    }

    form .d-flex.justify-content-end {
        flex-direction: column;
    }

    form .d-flex.justify-content-end > * {
        width: 100%;
        margin-right: 0 !important;
        margin-bottom: 8px;
        text-align: center;
    }
}
</style>
