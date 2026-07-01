<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const loading = ref(true);
const task = ref(null);

const loadTask = async () => {
    const { data } = await axios.get(route('tasks.show', props.taskId));
    task.value = data.task;
    loading.value = false;
};

onMounted(loadTask);

const PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#e5484d', bg: '#ffe5e5' },
    high: { label: 'Cao', color: '#f76808', bg: '#ffefe5' },
    normal: { label: 'Bình thường', color: '#006adc', bg: '#e6f0ff' },
    low: { label: 'Thấp', color: '#18794e', bg: '#e5f5ec' },
};
const priority = computed(() => (task.value ? PRIORITY[task.value.priority] || null : null));

const checklistDone = computed(() => (task.value?.checklists || []).filter((c) => c.is_done).length);
const checklistTotal = computed(() => (task.value?.checklists || []).length);

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;

// Mở trang chỉnh sửa riêng theo mã task (URL kiểu ICE-0042)
const goEdit = () => {
    router.visit(route('tasks.edit', task.value.code));
};
</script>

<template>
    <Modal max-width="1000px" align="top" header-class="bg-dark text-light" @close="emit('close')">
        <template #header>
            <h5 class="modal-card__title d-flex align-items-center">
                <span v-if="task?.code" class="task-code mr-2">{{ task.code }}</span>
                Chi tiết công việc
            </h5>
        </template>

        <div v-if="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
        <div v-else class="row">
            <!-- Cột trái -->
            <div class="col-lg-8">
                <p class="text-muted mb-1">Trong danh sách: <strong>{{ task.column_name }}</strong></p>
                <h4 class="font-weight-bold mb-3">{{ task.title }}</h4>

                <h6 class="font-weight-bold"><i class="fas fa-user-friends mr-2"></i>NGƯỜI PHỤ TRÁCH</h6>
                <div class="d-flex align-items-center flex-wrap mb-3">
                    <span v-for="a in task.assignees" :key="a.id" class="mr-2 mb-1 d-inline-flex align-items-center">
                        <img :src="avatar(a.email)" class="rounded-circle mr-1" width="28" height="28" :title="a.name">
                        <span class="small">{{ a.name }}</span>
                    </span>
                    <span v-if="!task.assignees || !task.assignees.length" class="text-muted small">Chưa có ai.</span>
                </div>

                <h6 class="font-weight-bold"><i class="fas fa-align-left mr-2"></i>MÔ TẢ</h6>
                <p class="mb-3" style="white-space:pre-wrap;">
                    <span v-if="task.description">{{ task.description }}</span>
                    <span v-else class="text-muted">Chưa có mô tả.</span>
                </p>

                <h6 class="font-weight-bold">
                    <i class="fas fa-tasks mr-2"></i>CHECKLIST
                    <span v-if="checklistTotal" class="text-muted small ml-1">({{ checklistDone }}/{{ checklistTotal }})</span>
                </h6>
                <div class="mb-3">
                    <div v-for="item in task.checklists" :key="item.id" class="d-flex align-items-center mb-1">
                        <i class="mr-2" :class="item.is_done ? 'far fa-check-square text-success' : 'far fa-square text-muted'"></i>
                        <span :class="{ 'text-muted text-decoration-line-through': item.is_done }">{{ item.title }}</span>
                    </div>
                    <span v-if="!task.checklists || !task.checklists.length" class="text-muted small">Chưa có mục nào.</span>
                </div>

                <hr>
                <h6 class="font-weight-bold"><i class="fas fa-comments mr-2"></i>BÌNH LUẬN</h6>
                <div v-for="c in task.comments" :key="c.id" class="d-flex mb-2">
                    <img :src="c.user_avatar || avatar(c.user_name, 40)" class="rounded-circle mr-2" width="32" height="32">
                    <div class="flex-grow-1">
                        <div><strong>{{ c.user_name }}</strong> <small class="text-muted">{{ c.time_ago }}</small></div>
                        <div style="white-space:pre-wrap;">{{ c.content }}</div>
                    </div>
                </div>
                <span v-if="!task.comments || !task.comments.length" class="text-muted small">Chưa có bình luận.</span>
            </div>

            <!-- Cột phải -->
            <div class="col-lg-4">
                <h6 class="text-muted small font-weight-bold">THÔNG TIN</h6>
                <div class="mb-2">
                    <div class="small text-muted">Nhãn</div>
                    <div v-if="task.labels && task.labels.length" class="d-flex flex-wrap" style="gap:4px;">
                        <span v-for="l in task.labels" :key="l.id" class="label-chip"
                            :style="{ backgroundColor: l.color }">{{ l.name }}</span>
                    </div>
                    <span v-else>—</span>
                </div>
                <div class="mb-2">
                    <div class="small text-muted">Trạng thái</div>
                    <span v-if="task.status" class="status-badge"
                        :style="{ color: task.status.color, borderColor: task.status.color }">
                        {{ task.status.name }}
                    </span>
                    <span v-else>—</span>
                </div>
                <div class="mb-2">
                    <div class="small text-muted">Độ ưu tiên</div>
                    <span v-if="priority" class="priority-pill" :style="{ color: priority.color, backgroundColor: priority.bg }">
                        <span class="dot" :style="{ backgroundColor: priority.color }"></span>{{ priority.label }}
                    </span>
                    <span v-else>—</span>
                </div>
                <div class="mb-3">
                    <div class="small text-muted">Ngày hết hạn</div>
                    <strong>{{ task.formatted_due_date || '—' }}</strong>
                </div>

                <div v-if="canEdit" class="mb-3">
                    <button class="btn btn-primary btn-block btn-sm" @click="goEdit">
                        <i class="fas fa-pen mr-1"></i>Chỉnh sửa
                    </button>
                </div>

                <h6 class="text-muted small font-weight-bold">LỊCH SỬ</h6>
                <div style="max-height:300px; overflow-y:auto;">
                    <div v-for="h in task.task_histories" :key="h.id" class="d-flex mb-2 small">
                        <img :src="h.user_avatar" class="rounded-circle mr-2" width="24" height="24">
                        <div><strong>{{ h.user_name }}</strong> {{ h.action }} <span v-if="h.note">— {{ h.note }}</span>
                            <div class="text-muted" style="font-size:.7rem;">{{ h.updated_at }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.task-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.15);
    padding: 2px 8px;
    border-radius: 6px;
}

.priority-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: #fff;
}

.label-chip {
    display: inline-flex;
    align-items: center;
    min-width: 28px;
    height: 20px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
}

.priority-pill .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
</style>
