<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TaskModal from '@/Components/TaskModal.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

const PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#e5484d' },
    high: { label: 'Cao', color: '#f76808' },
    normal: { label: 'Bình thường', color: '#006adc' },
    low: { label: 'Thấp', color: '#18794e' },
};

// Thứ tự & nhãn của các nhóm hạn
const GROUPS = [
    { key: 'overdue', label: 'Quá hạn', icon: 'fa-triangle-exclamation', color: '#c9372c' },
    { key: 'today', label: 'Hôm nay', icon: 'fa-star', color: '#976400' },
    { key: 'week', label: 'Trong tuần', icon: 'fa-calendar-week', color: '#006adc' },
    { key: 'later', label: 'Sau này', icon: 'fa-calendar', color: '#44546f' },
    { key: 'none', label: 'Không có hạn', icon: 'fa-inbox', color: '#7a869a' },
];

const grouped = computed(() =>
    GROUPS.map((g) => ({ ...g, items: props.tasks.filter((t) => t.due_group === g.key) }))
        .filter((g) => g.items.length)
);

// Click task -> mở modal xem trước; từ modal bấm "Chỉnh sửa" mới sang trang edit.
const modalTaskId = ref(null);
const modalBoardId = ref(null);
const openTask = (t) => { modalTaskId.value = t.id; modalBoardId.value = t.board_id; };
const closeTask = () => { modalTaskId.value = null; };
</script>

<template>
    <Head title="Task của tôi" />
    <AuthenticatedLayout>
        <div class="p-3 mb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="mb-0"><i class="fas fa-user-check"></i> Task của tôi</h3>
            <span class="text-muted small">{{ tasks.length }} công việc được giao</span>
        </div>

        <div class="px-3 pb-4">
            <div v-if="!tasks.length" class="text-center text-muted py-5">
                <i class="fas fa-mug-hot fa-2x mb-2 d-block"></i>
                Bạn chưa được giao công việc nào.
            </div>

            <div v-for="g in grouped" :key="g.key" class="mb-4">
                <h5 class="group-title" :style="{ color: g.color }">
                    <i class="fas" :class="g.icon"></i> {{ g.label }}
                    <span class="badge badge-light ml-1">{{ g.items.length }}</span>
                </h5>

                <div class="mt-task-list">
                    <div v-for="t in g.items" :key="t.id" class="mt-task" @click="openTask(t)">
                        <div class="mt-task-main">
                            <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                                <span class="task-code">{{ t.code }}</span>
                                <span v-for="l in t.labels" :key="l.id" class="label-chip" :style="{ backgroundColor: l.color }">{{ l.name }}</span>
                            </div>
                            <div class="mt-title" :class="{ done: t.status?.is_completed }">{{ t.title }}</div>
                            <div class="text-muted small">
                                <i class="fas fa-columns mr-1"></i>{{ t.board_name }} · {{ t.column_name }}
                            </div>
                        </div>
                        <div class="mt-task-meta">
                            <span v-if="t.status" class="status-badge" :style="{ color: t.status.color, borderColor: t.status.color }">
                                {{ t.status.name }}
                            </span>
                            <span v-if="PRIORITY[t.priority]" class="prio-dot" :style="{ backgroundColor: PRIORITY[t.priority].color }"
                                :title="PRIORITY[t.priority].label"></span>
                            <span v-if="t.formatted_due_date" class="due" :class="`due-${t.due_group}`">
                                <i class="far fa-clock"></i> {{ t.formatted_due_date }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Xem trước task; "Chỉnh sửa" trong modal sẽ mở trang edit (và quay lại về đây) -->
        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :board-id="modalBoardId"
            :can-edit="true" :edit-query="{ return: 'my-tasks' }" @close="closeTask" />
    </AuthenticatedLayout>
</template>

<style scoped>
.group-title {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mt-task-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mt-task {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.mt-task:hover {
    box-shadow: 0 6px 14px rgba(9, 30, 66, 0.08);
    transform: translateY(-2px);
}

.mt-task-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.mt-title {
    font-weight: 600;
    color: var(--app-text);
}

.mt-title.done {
    text-decoration: line-through;
    color: #8993a4;
}

.mt-task-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.task-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.7rem;
    font-weight: 700;
    color: #7a869a;
}

.label-chip {
    display: inline-flex;
    align-items: center;
    height: 18px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    color: #fff;
}

.status-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: var(--app-surface);
    white-space: nowrap;
}

.prio-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.due {
    font-size: 0.75rem;
    font-weight: 500;
    color: #44546f;
    white-space: nowrap;
}

.due-overdue { color: #c9372c; }
.due-today { color: #976400; }
</style>
