<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    boardName: { type: String, default: '' },
    taskCode: { type: String, required: true },
    ownerName: { type: String, default: 'chủ sở hữu bảng' },
});

const page = usePage();
const sent = computed(() => !!page.props.flash?.success);

const form = useForm({ note: '' });

const submit = () => {
    form.post(route('tasks.request-access.submit', props.taskId), { preserveScroll: true });
};

const goHome = () => router.visit(route('dashboard'));
</script>

<template>
    <Head title="Xin quyền truy cập công việc" />
    <AuthenticatedLayout>
        <div class="request-access">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="lock-icon mb-3"><i class="fas fa-lock"></i></div>
                    <h4 class="mb-1">Bạn chưa có quyền xem công việc này</h4>
                    <p class="text-muted mb-4">
                        Công việc <strong>{{ taskCode }}</strong> thuộc bảng <strong>{{ boardName }}</strong>.
                        Gửi yêu cầu tới <strong>{{ ownerName }}</strong> để được cấp quyền xem.
                    </p>

                    <div v-if="sent" class="alert alert-success text-left">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ page.props.flash.success }}
                    </div>

                    <form v-else @submit.prevent="submit" class="text-left">
                        <div class="form-group">
                            <label class="small font-weight-bold mb-1">Lời nhắn (tuỳ chọn)</label>
                            <textarea v-model="form.note" class="form-control" rows="3"
                                placeholder="Ví dụ: Mình cần xem công việc này để phối hợp..."
                                maxlength="1000"></textarea>
                            <div v-if="form.errors.note" class="text-danger small mt-1">{{ form.errors.note }}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <Btn type="button" variant="white" class="btn-sm" @click="goHome">Về trang chủ</Btn>
                            <Btn variant="black" icon="fas fa-paper-plane" :disabled="form.processing">
                                {{ form.processing ? 'Đang gửi...' : 'Gửi yêu cầu' }}
                            </Btn>
                        </div>
                    </form>

                    <div v-if="sent" class="mt-3">
                        <Btn type="button" variant="white" class="btn-sm" @click="goHome">Về trang chủ</Btn>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.request-access {
    max-width: 520px;
    margin: 40px auto;
}

.lock-icon {
    width: 64px;
    height: 64px;
    line-height: 64px;
    margin: 0 auto;
    border-radius: 50%;
    background: var(--app-bg-subtle, #f4f6f9);
    color: var(--app-accent, #663300);
    font-size: 1.5rem;
}
</style>
