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
const modalCanEdit = ref(false);
const modalCanManage = ref(false);
const openTask = (t) => {
    modalTaskId.value = t.id;
    modalBoardId.value = t.board_id;
    modalCanEdit.value = !!t.can_edit;
    modalCanManage.value = !!t.can_manage;
};
const closeTask = () => { modalTaskId.value = null; };
</script>

<template>
    <Head title="Task của tôi" />
    <AuthenticatedLayout>
        <div class="mt-page">
            <!-- Hero header -->
            <header class="mt-hero">
                <div class="mt-hero__icon"><i class="fas fa-user-check"></i></div>
                <div class="mt-hero__text">
                    <span class="mt-eyebrow">Công việc của bạn</span>
                    <h1 class="mt-hero__title">Task của tôi</h1>
                </div>
                <span class="mt-count"><i class="fas fa-list-check mr-1"></i>{{ tasks.length }} công việc</span>
            </header>

            <div v-if="!tasks.length" class="mt-empty">
                <i class="fas fa-mug-hot"></i>
                <p class="mb-0">Bạn chưa được giao công việc nào.</p>
            </div>

            <section v-for="g in grouped" :key="g.key" class="mt-group" :style="{ '--mt-accent': g.color }">
                <div class="mt-group__head">
                    <span class="mt-group__icon"><i class="fas" :class="g.icon"></i></span>
                    <h5 class="mt-group__title">{{ g.label }}</h5>
                    <span class="mt-group__count">{{ g.items.length }}</span>
                </div>

                <div class="mt-task-list">
                    <div v-for="t in g.items" :key="t.id" class="mt-task" @click="openTask(t)">
                        <div class="mt-task-main">
                            <div class="mt-tags">
                                <span class="task-code">{{ t.code }}</span>
                                <span v-for="l in t.labels" :key="l.id" class="label-chip" :style="{ backgroundColor: l.color }">{{ l.name }}</span>
                            </div>
                            <div class="mt-title" :class="{ done: t.status?.is_completed }">{{ t.title }}</div>
                            <div class="mt-sub">
                                <i class="fas fa-columns"></i>
                                <span class="mt-board" :title="t.board_name">{{ t.board_name }}</span>
                                <span class="mt-col"> · {{ t.column_name }}</span>
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
            </section>
        </div>

        <!-- Xem trước task; "Chỉnh sửa" trong modal sẽ mở trang edit (và quay lại về đây) -->
        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :board-id="modalBoardId"
            :can-edit="modalCanEdit" :can-manage="modalCanManage"
            :edit-query="{ return: 'my-tasks' }" @close="closeTask" />
    </AuthenticatedLayout>
</template>

<style scoped>
.mt-page {
    max-width: 1120px;
    margin: 0 auto;
    padding: 20px 16px 40px;
}

/* ---- Hero header (điểm nhấn nâu thương hiệu) ---- */
.mt-hero {
    position: relative;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    margin-bottom: 26px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(9, 30, 66, 0.05);
    overflow: hidden;
}
.mt-hero::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, var(--app-accent), var(--app-accent-2));
}
.mt-hero__icon {
    flex-shrink: 0;
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    font-size: 1.2rem;
    color: #fff;
    background: linear-gradient(135deg, var(--app-accent), var(--app-accent-2));
    box-shadow: 0 4px 12px rgba(102, 51, 0, 0.35);
}
.mt-hero__text {
    flex: 1;
    min-width: 0;
}
.mt-eyebrow {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--app-accent);
}
.mt-hero__title {
    margin: 2px 0 0;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--app-text);
}
.mt-count {
    flex-shrink: 0;
    font-size: 0.82rem;
    font-weight: 600;
    color: #fff;
    background: var(--app-accent);
    border-radius: 20px;
    padding: 6px 14px;
    white-space: nowrap;
}

/* ---- Trạng thái rỗng ---- */
.mt-empty {
    text-align: center;
    color: var(--app-text-muted);
    background: var(--app-surface);
    border: 1px dashed var(--app-border);
    border-radius: 16px;
    padding: 48px 20px;
}
.mt-empty i {
    display: block;
    margin-bottom: 10px;
    font-size: 2rem;
    color: var(--app-accent-2);
}

/* ---- Nhóm theo hạn (marker dùng màu ngữ nghĩa qua --mt-accent) ---- */
.mt-group {
    margin-bottom: 26px;
}
.mt-group__head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.mt-group__icon {
    width: 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    font-size: 0.8rem;
    color: var(--mt-accent, var(--app-accent));
    background: color-mix(in srgb, var(--mt-accent, var(--app-accent)) 15%, transparent);
}
.mt-group__title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--app-text);
}
.mt-group__count {
    min-width: 22px;
    text-align: center;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--mt-accent, var(--app-accent));
    background: color-mix(in srgb, var(--mt-accent, var(--app-accent)) 15%, transparent);
    border-radius: 20px;
    padding: 1px 8px;
}

/* ---- Danh sách & thẻ task ---- */
.mt-task-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.mt-task {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-left: 3px solid var(--mt-accent, var(--app-accent));
    border-radius: 12px;
    padding: 13px 16px;
    cursor: pointer;
    transition: box-shadow 0.18s ease, transform 0.18s ease;
}
.mt-task:hover {
    box-shadow: 0 8px 20px rgba(102, 51, 0, 0.14);
    transform: translateY(-2px);
}
.mt-task-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.mt-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 2px;
}
.mt-title {
    font-weight: 600;
    font-size: 0.98rem;
    line-height: 1.35;
    color: var(--app-text);
    /* Tiêu đề dài (kể cả chuỗi không dấu cách) xuống dòng thay vì tràn ngang. */
    overflow-wrap: anywhere;
}
.mt-title.done {
    text-decoration: line-through;
    color: var(--app-text-muted);
}

/* Dòng "tên bảng · tên cột": chỉ CẮT riêng tên bảng (dài) kèm …, còn tên cột LUÔN
   hiển thị -> không mất info. Phần meta bên phải đã flex-shrink:0. */
.mt-sub {
    display: flex;
    align-items: center;
    min-width: 0;
    margin-top: 2px;
    font-size: 0.78rem;
    color: var(--app-text-muted);
}
.mt-sub > i {
    margin-right: 5px;
    color: var(--app-accent-2);
}
.mt-board {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mt-col {
    flex-shrink: 0;
    white-space: nowrap;
}

.mt-task-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.task-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--app-text-muted);
    background: rgba(127, 127, 127, 0.1);
    padding: 1px 7px;
    border-radius: 5px;
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
    color: var(--app-text);
    white-space: nowrap;
}

.due-overdue { color: #c9372c; }
.due-today { color: #976400; }

/* Dark mode: làm sáng màu hạn cho đủ tương phản trên nền tối. */
[data-theme="dark"] .due-overdue { color: #ff8a84; }
[data-theme="dark"] .due-today { color: #e0b64d; }

/* ---- Responsive: màn hẹp thì meta xuống hàng dưới ---- */
@media (max-width: 575.98px) {
    .mt-page {
        padding: 4px 0 20px;
    }

    .mt-hero {
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
        padding: 14px 14px 14px 18px;
    }

    .mt-hero__icon {
        width: 40px;
        height: 40px;
    }

    .mt-hero__title {
        font-size: 1.25rem;
    }

    .mt-count {
        margin-left: 50px;
        padding: 5px 11px;
    }

    .mt-group {
        margin-bottom: 20px;
    }

    .mt-task {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 12px;
    }
    .mt-task-meta {
        justify-content: flex-start;
        flex-wrap: wrap;
    }
}
</style>
