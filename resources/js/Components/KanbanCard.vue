<script setup>
import { computed } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
});

const emit = defineEmits(['open']);

// Cấu hình hiển thị theo độ ưu tiên 
const PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#e5484d', bg: '#ffe5e5' },
    high: { label: 'Cao', color: '#f76808', bg: '#ffefe5' },
    normal: { label: 'Bình thường', color: '#006adc', bg: '#e6f0ff' },
    low: { label: 'Thấp', color: '#18794e', bg: '#e5f5ec' },
};
const priority = computed(() => PRIORITY[props.task.priority] || null);

const checklistTotal = computed(() => props.task.checklist_total || 0);
const checklistDone = computed(() => props.task.checklist_done || 0);
const checklistComplete = computed(
    () => checklistTotal.value > 0 && checklistDone.value >= checklistTotal.value
);

// Trạng thái hạn: quá hạn / sắp tới (<=2 ngày) / bình thường
const dueState = computed(() => {
    if (!props.task.due_date) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = new Date(props.task.due_date);
    due.setHours(0, 0, 0, 0);
    const diffDays = Math.round((due - today) / 86400000);
    if (diffDays < 0) return 'overdue';
    if (diffDays <= 2) return 'soon';
    return 'normal';
});

const MAX_AVATARS = 3;
const visibleAssignees = computed(() => (props.task.assignees || []).slice(0, MAX_AVATARS));
const extraAssignees = computed(() => Math.max(0, (props.task.assignees?.length || 0) - MAX_AVATARS));

// Có ít nhất một thông tin phụ để hiển thị hàng meta?
const hasMeta = computed(() => {
    const t = props.task;
    return !!(
        t.formatted_due_date ||
        t.checklist_total ||
        t.has_description ||
        t.comments_count ||
        t.attachments_count
    );
});
</script>

<template>
    <div
        class="kanban-card"
        :class="{ 'is-done': task.status?.is_completed, 'is-overdue': dueState === 'overdue' && !task.status?.is_completed }"
        @click="emit('open')"
    >
        <!-- Mã công việc (kiểu ICE-0042) -->
        <div v-if="task.code" class="card-code">{{ task.code }}</div>

        <!-- Header: Priority + Status (trái) & Assignees (phải) -->
        <div class="card-header">
            <div class="card-tags">
                <div
                    v-if="priority"
                    class="priority-pill"
                    :style="{ color: priority.color, backgroundColor: priority.bg }"
                >
                    <span class="dot" :style="{ backgroundColor: priority.color }"></span>
                    {{ priority.label }}
                </div>

                <span v-if="task.status" class="status-badge"
                    :style="{ color: task.status.color, borderColor: task.status.color }">
                    {{ task.status.name }}
                </span>
            </div>

            <div v-if="visibleAssignees.length" class="card-assignees">
                <img v-for="a in visibleAssignees" :key="a.id"
                    :src="`https://i.pravatar.cc/32?u=${encodeURIComponent(a.email)}`"
                    :title="a.name" :alt="a.name" class="assignee-avatar" width="28" height="28">
                <span v-if="extraAssignees" class="assignee-more" :title="`Thêm ${extraAssignees} người`">
                    +{{ extraAssignees }}
                </span>
            </div>
        </div>

        <!-- Nhãn màu -->
        <div v-if="task.labels && task.labels.length" class="card-labels">
            <span v-for="l in task.labels" :key="l.id" class="label-chip"
                :style="{ backgroundColor: l.color }" :title="l.name || ''">
                {{ l.name }}
            </span>
        </div>

        <!-- Body: Title -->
        <h3 class="card-title">{{ task.title }}</h3>

        <!-- Footer: Meta Data -->
        <div v-if="hasMeta" class="card-meta">
            <span v-if="task.formatted_due_date" class="meta-badge" :class="`due-${dueState}`"
                :title="`Hạn: ${task.formatted_due_date}`">
                <i class="far fa-clock"></i> {{ task.formatted_due_date }}
            </span>
            <span v-if="checklistTotal" class="meta-badge" :class="{ 'check-complete': checklistComplete }"
                title="Tiến độ checklist">
                <i class="far fa-check-square"></i> {{ checklistDone }}/{{ checklistTotal }}
            </span>
            
            <div class="meta-icons">
                <span v-if="task.has_description" class="meta-icon" title="Có mô tả">
                    <i class="fas fa-align-left"></i>
                </span>
                <span v-if="task.comments_count" class="meta-icon" title="Bình luận">
                    <i class="far fa-comment"></i> <strong>{{ task.comments_count }}</strong>
                </span>
                <span v-if="task.attachments_count" class="meta-icon" title="Tệp đính kèm">
                    <i class="fas fa-paperclip"></i> <strong>{{ task.attachments_count }}</strong>
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kanban-card {
    background: var(--app-surface);
    border: 1px solid #f1f2f4;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 12px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(9, 30, 66, 0.04);
    transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.kanban-card:hover {
    box-shadow: 0 8px 16px rgba(9, 30, 66, 0.08);
    transform: translateY(-3px);
    border-color: #e4e6ea;
}

/* Mã công việc */
.card-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #7a869a;
}

/* Nhãn màu */
.card-labels {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.label-chip {
    display: inline-flex;
    align-items: center;
    min-width: 28px;
    height: 18px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
    white-space: nowrap;
}

/* Card Header */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.card-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: var(--app-surface);
    line-height: 1.2;
}

/* Tùy biến Priority mượt mà hơn */
.priority-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

.priority-pill .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

/* Title lớn hơn, Typography hiện đại */
.card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--app-text);
    line-height: 1.4;
    margin: 0;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Assignees bay lên góc phải */
.card-assignees {
    display: flex;
    align-items: center;
}

.assignee-avatar {
    border-radius: 50%;
    border: 2px solid #fff;
    margin-left: -8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.assignee-avatar:first-child {
    margin-left: 0;
}

.assignee-avatar:hover {
    transform: translateY(-2px) scale(1.1);
    z-index: 10;
}

.assignee-more {
    margin-left: -8px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #44546f;
    background: #f1f2f4;
    border: 2px solid #fff;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

/* Hàng Footer (Meta data) */
.card-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #44546f;
    padding: 4px 8px;
    border-radius: 6px;
    background: #f1f2f4;
}

/* Màu sắc hạn chót làm mềm lại */
.due-overdue {
    background: #ffeceb;
    color: #c9372c;
}

.due-soon {
    background: #fff7d6;
    color: #976400;
}

.check-complete {
    background: #dcfce0;
    color: #1f7a33;
}

/* Nhóm các icon (comment, đính kèm) lại cho gọn */
.meta-icons {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto; /* Đẩy bộ icon sang góc phải */
}

.meta-icon {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #7a869a;
}

.meta-icon strong {
    font-weight: 600;
    font-size: 0.75rem;
}

/* Nhấn mạnh thẻ quá hạn */
.is-overdue {
    border-left: 4px solid #663300;
}

/* Hiệu ứng mờ khi Done */
.is-done {
    opacity: 0.65;
    background: #fbfbfc;
    box-shadow: none;
}
.is-done:hover {
    opacity: 1;
    box-shadow: 0 4px 12px rgba(9, 30, 66, 0.06);
}
.is-done .card-title {
    color: #8993a4;
    text-decoration: line-through;
}
</style>