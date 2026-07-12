<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import Btn from '@/Components/Btn.vue';
import { renderMarkdown } from '@/composables/useMarkdown';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    editQuery: { type: Object, default: null },
    columns: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'move-task']);

const loading = ref(true);
const task = ref(null);
const showMoveSheet = ref(false);

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
const checklistPct = computed(() =>
    checklistTotal.value ? Math.round((checklistDone.value / checklistTotal.value) * 100) : 0
);

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;

// Mở trang chỉnh sửa riêng theo mã task (URL kiểu ICE-0042)
const goEdit = () => {
    router.visit(route('tasks.edit', { taskCode: task.value.code, ...(props.editQuery || {}) }));
};

// Permalink trang chi tiết (dành cho người chỉ có quyền xem, không có nút Chỉnh sửa)
const taskUrl = computed(() =>
    task.value ? route('tasks.permalink', { boardCode: task.value.board_code, taskCode: task.value.task_code }) : ''
);
const linkCopied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(taskUrl.value);
    } catch {
        const el = document.createElement('textarea');
        el.value = taskUrl.value;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 1500);
};

const moveTargets = computed(() => {
    return props.columns.filter((column) => {
        return column.id !== task.value?.column_id;
    });
});

const moveTask = (column) => {
    if (!task.value || !column) {
        return;
    }

    showMoveSheet.value = false;
    emit('move-task', {
        taskId: task.value.id,
        columnId: column.id,
    });
    emit('close');
};
</script>

<template>
    <Modal max-width="960px" align="center" @close="emit('close')">
        <template #header>
            <h5 class="modal-card__title d-flex align-items-center mb-0">
                Chi tiết công việc
                <span v-if="task?.display_code" class="task-code ml-2">#{{ task.display_code }}</span>
            </h5>
        </template>

        <div v-if="loading" class="text-center py-5 text-muted">
            <i class="fas fa-spinner fa-spin fa-lg"></i>
            <div class="mt-2">Đang tải...</div>
        </div>

        <div v-else class="row tm-body">
            <!-- Cột trái -->
            <div class="col-lg-8 tm-main mb-4 mb-lg-0">
                <p class="tm-breadcrumb">
                    <i class="fas fa-columns mr-1"></i>Trong danh sách:
                    <strong>{{ task.column_name }}</strong>
                </p>
                <h4 class="tm-title">{{ task.title }}</h4>

                <!-- Người phụ trách -->
                <h6 class="sect"><i class="fas fa-user-friends"></i>Người phụ trách</h6>
                <div class="d-flex align-items-center flex-wrap mb-4" style="gap:8px;">
                    <span v-for="a in task.assignees" :key="a.id" class="assignee-pill">
                        <img :src="a.avatar_url || avatar(a.email)" class="rounded-circle" width="24" height="24" :title="a.name">
                        <span>{{ a.name }}</span>
                    </span>
                    <span v-if="!task.assignees || !task.assignees.length" class="text-muted small">Chưa có ai.</span>
                </div>

                <!-- Mô tả -->
                <h6 class="sect"><i class="fas fa-align-left"></i>Mô tả</h6>
                <div class="tm-box mb-4">
                    <div v-if="task.description" class="md-content" v-html="renderMarkdown(task.description)"></div>
                    <span v-else class="text-muted small"><em>Chưa có mô tả.</em></span>
                </div>

                <!-- Checklist -->
                <h6 class="sect">
                    <i class="fas fa-tasks"></i>Checklist
                    <span v-if="checklistTotal" class="sect-count">{{ checklistDone }}/{{ checklistTotal }}</span>
                </h6>
                <div class="mb-4">
                    <div v-if="checklistTotal" class="checklist-progress mb-2">
                        <div class="checklist-progress__bar" :style="{ width: checklistPct + '%' }"></div>
                    </div>
                    <div v-for="item in task.checklists" :key="item.id" class="checklist-item">
                        <i :class="item.is_done ? 'far fa-check-square text-success' : 'far fa-square text-muted'"></i>
                        <span :class="{ done: item.is_done }">{{ item.title }}</span>
                    </div>
                    <span v-if="!task.checklists || !task.checklists.length" class="text-muted small">Chưa có mục nào.</span>
                </div>

                <!-- Bình luận -->
                <h6 class="sect"><i class="fas fa-comments"></i>Bình luận</h6>
                <div class="comment-list">
                    <div v-for="c in task.comments" :key="c.id" class="comment">
                        <img :src="c.user_avatar || avatar(c.user_name, 40)" class="rounded-circle comment__avatar"
                            width="34" height="34">
                        <div class="comment__body">
                            <div class="comment__head">
                                <strong>{{ c.user_name }}</strong>
                                <small class="text-muted">{{ c.time_ago }}</small>
                            </div>
                            <div class="comment__content md-content" v-html="renderMarkdown(c.content)"></div>
                        </div>
                    </div>
                    <div v-if="!task.comments || !task.comments.length" class="text-muted small text-center py-3">
                        Chưa có bình luận.
                    </div>
                </div>
            </div>

            <!-- Cột phải -->
            <div class="col-lg-4 tm-side">
                <div class="tm-panel mb-3">
                    <h6 class="side-title">Thông tin</h6>

                    <div class="info-row">
                        <span class="info-label">Nhãn</span>
                        <div v-if="task.labels && task.labels.length" class="d-flex flex-wrap" style="gap:4px;">
                            <span v-for="l in task.labels" :key="l.id" class="label-chip"
                                :style="{ backgroundColor: l.color }">{{ l.name }}</span>
                        </div>
                        <span v-else class="info-empty">—</span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Trạng thái</span>
                        <span v-if="task.status" class="status-badge"
                            :style="{ color: task.status.color, borderColor: task.status.color }">
                            {{ task.status.name }}
                        </span>
                        <span v-else class="info-empty">—</span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Độ ưu tiên</span>
                        <span v-if="priority" class="priority-pill" :style="{ '--pill': priority.color }">
                            <span class="dot"></span>{{ priority.label }}
                        </span>
                        <span v-else class="info-empty">—</span>
                    </div>

                    <div class="info-row" :class="{ 'mb-0': canEdit }">
                        <span class="info-label">Ngày hết hạn</span>
                        <strong class="tm-due">{{ task.formatted_due_date || '—' }}</strong>
                    </div>

                    <!-- Chỉ có quyền xem: hiển thị đường dẫn trang chi tiết (không có nút Chỉnh sửa) -->
                    <div v-if="!canEdit" class="info-row mb-0">
                        <span class="info-label">Đường dẫn công việc</span>
                        <div class="link-chip" @click="copyLink" :title="taskUrl">
                            <i class="fas fa-link link-chip__icon"></i>
                            <span class="link-chip__url">{{ taskUrl }}</span>
                            <span class="link-chip__copy">
                                <i :class="linkCopied ? 'fas fa-check text-success' : 'fas fa-copy'"></i>
                            </span>
                            <Link :href="taskUrl" class="link-chip__open" title="Xem trang chi tiết công việc"
                                aria-label="Xem trang chi tiết công việc" @click.stop>
                                <i class="fas fa-external-link-alt"></i>
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="canEdit" class="tm-mobile-actions mb-3">
                    <Btn type="button" variant="black" icon="fas fa-pen"
                        class="btn-block tm-edit-trigger" @click="goEdit">
                        Chỉnh sửa
                    </Btn>

                    <Btn v-if="moveTargets.length" type="button" variant="secondary" outline
                        icon="fas fa-right-left" class="btn-block tm-move-trigger"
                        @click="showMoveSheet = true">
                        Di chuyển
                    </Btn>
                </div>

                <div class="tm-panel">
                    <h6 class="side-title">Lịch sử</h6>
                    <div class="history-scroll">
                        <div v-for="h in task.task_histories" :key="h.id" class="history-item">
                            <img :src="h.user_avatar" class="rounded-circle" width="24" height="24">
                            <div class="history-item__text">
                                <!-- note là HTML dựng sẵn (dữ liệu người dùng đã escape ở server) -->
                                <div v-if="h.note" class="history-note" v-html="h.note"></div>
                                <div v-else class="history-note"><strong>{{ h.user_name }}</strong> {{ h.action }}</div>
                                <div class="history-item__time">{{ h.updated_at }}</div>
                            </div>
                        </div>
                        <div v-if="!task.task_histories || !task.task_histories.length"
                            class="text-muted small text-center py-2">Chưa có lịch sử.</div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>

    <Teleport to="body">
        <div v-if="showMoveSheet" class="tm-move-sheet-backdrop" @click.self="showMoveSheet = false">
            <section class="tm-move-sheet" role="dialog" aria-modal="true" aria-labelledby="move-task-title">
                <div class="tm-move-sheet__handle"></div>
                <header class="tm-move-sheet__header">
                    <div>
                        <h6 id="move-task-title">Di chuyển công việc</h6>
                        <p>Chọn cột đích. Công việc sẽ được thêm vào cuối cột.</p>
                    </div>
                    <button type="button" class="tm-move-sheet__close" aria-label="Đóng"
                        @click="showMoveSheet = false">
                        <i class="fas fa-times"></i>
                    </button>
                </header>

                <div class="tm-move-sheet__options">
                    <button v-for="column in moveTargets" :key="column.id" type="button"
                        class="tm-move-sheet__option" @click="moveTask(column)">
                        <span class="tm-move-sheet__column">{{ column.name }}</span>
                        <span class="tm-move-sheet__count">{{ column.tasks?.length || 0 }} công việc</span>
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
/* ---------------- Header ---------------- */
.task-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: var(--app-accent, #663300);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
}

/* ---------------- Bố cục chung ---------------- */
.tm-breadcrumb {
    font-size: 0.8rem;
    color: var(--app-text-muted);
    margin-bottom: 6px;
}

.tm-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--app-text);
    line-height: 1.35;
    margin: 0 0 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--app-border);
    word-break: break-word;
}

.sect,
.side-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--app-accent, #663300);
}

.sect {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
}

.sect-count {
    font-weight: 600;
    color: var(--app-text-muted);
    text-transform: none;
    letter-spacing: 0;
}

.side-title { margin: 0 0 14px; }

/* ---------------- Người phụ trách ---------------- */
.assignee-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(127, 127, 127, 0.1);
    border-radius: 20px;
    padding: 3px 12px 3px 3px;
    font-size: 0.82rem;
    color: var(--app-text);
}

/* ---------------- Mô tả ---------------- */
.tm-box {
    background: rgba(127, 127, 127, 0.05);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    padding: 14px 16px;
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* ---------------- Checklist ---------------- */
.checklist-progress {
    height: 6px;
    border-radius: 4px;
    background: rgba(127, 127, 127, 0.2);
    overflow: hidden;
}

.checklist-progress__bar {
    height: 100%;
    background: #18794e;
    transition: width 0.25s ease;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 0;
    font-size: 0.9rem;
    color: var(--app-text);
}

.checklist-item .done {
    color: var(--app-text-muted);
    text-decoration: line-through;
}

/* ---------------- Bình luận ---------------- */
.comment {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-top: 1px solid var(--app-border);
}

.comment:first-child { border-top: 0; padding-top: 0; }

.comment__avatar { flex-shrink: 0; }

.comment__body { flex: 1; min-width: 0; }

.comment__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 3px;
    font-size: 0.9rem;
}

.comment__content {
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* ---------------- Cột phải ---------------- */
.tm-side { align-self: flex-start; }

.tm-panel {
    background: rgba(127, 127, 127, 0.04);
    border: 1px solid var(--app-border);
    border-radius: 12px;
    padding: 16px 18px;
}

.info-row {
    margin-bottom: 14px;
}

.info-label {
    display: block;
    font-size: 0.72rem;
    color: var(--app-text-muted);
    margin-bottom: 4px;
}

.info-empty { color: var(--app-text-muted); }

/* Đường dẫn công việc: hộp nhỏ, gọn, bấm cả hộp để sao chép */
.link-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    background: var(--app-bg);
    border: 1px solid var(--app-border);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}
.link-chip:hover {
    border-color: var(--app-accent);
    background: rgba(102, 51, 0, 0.05);
}
.link-chip__icon {
    color: var(--app-accent);
    font-size: 0.75rem;
    flex-shrink: 0;
}
.link-chip__url {
    flex: 1;
    min-width: 0;
    font-size: 0.75rem;
    color: var(--app-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.link-chip__copy {
    flex-shrink: 0;
    font-size: 0.75rem;
    color: var(--app-text-muted);
}
.link-chip__open {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--app-text-muted);
    font-size: 0.75rem;
    text-decoration: none;
}
.link-chip:hover .link-chip__copy,
.link-chip__open:hover { color: var(--app-accent); }

.tm-due { color: var(--app-text); font-size: 0.9rem; }

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 12px;
    border-radius: 20px;
    border: 1px solid currentColor;
    background: var(--app-surface);
}

.priority-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    color: var(--pill);
    background: color-mix(in srgb, var(--pill) 14%, transparent);
}

.priority-pill .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--pill);
}

[data-theme="dark"] .priority-pill {
    color: color-mix(in srgb, var(--pill) 62%, white);
    background: color-mix(in srgb, var(--pill) 22%, transparent);
}

[data-theme="dark"] .priority-pill .dot {
    background: color-mix(in srgb, var(--pill) 62%, white);
}

.label-chip {
    display: inline-flex;
    align-items: center;
    min-width: 28px;
    height: 22px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
}

/* ---------------- Lịch sử ---------------- */
.history-scroll {
    max-height: 280px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    gap: 8px;
    padding: 8px 0;
    font-size: 0.8rem;
    color: var(--app-text);
}

.history-item + .history-item { border-top: 1px solid var(--app-border); }

.history-item__time {
    color: var(--app-text-muted);
    font-size: 0.7rem;
    margin-top: 1px;
}

.history-note {
    line-height: 1.5;
    word-break: break-word;
}

.tm-move-trigger,
.tm-move-sheet-backdrop {
    display: none;
}

/* ---------------- Nội dung markdown đã render ---------------- */
.md-content { word-break: break-word; }
.md-content :deep(p) { margin: 0 0 0.5rem; }
.md-content :deep(p:last-child) { margin-bottom: 0; }
.md-content :deep(ul),
.md-content :deep(ol) { margin: 0 0 0.5rem; padding-left: 1.4rem; }
.md-content :deep(blockquote) {
    margin: 0 0 0.5rem;
    padding: 3px 12px;
    border-left: 3px solid var(--app-accent);
    background: rgba(102, 51, 0, 0.06);
    border-radius: 0 6px 6px 0;
}
.md-content :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82em;
    background: rgba(127, 127, 127, 0.15);
    padding: 1px 5px;
    border-radius: 4px;
}
.md-content :deep(pre) {
    padding: 10px 12px;
    background: rgba(127, 127, 127, 0.12);
    border-radius: 8px;
    overflow-x: auto;
}
.md-content :deep(pre code) { background: none; padding: 0; }
.md-content :deep(a) { color: var(--app-accent); }

[data-theme="dark"] .md-content :deep(a) { color: var(--app-accent-2); }
[data-theme="dark"] .md-content :deep(blockquote) {
    background: rgba(165, 118, 63, 0.12);
    border-left-color: var(--app-accent-2);
}

@media (max-width: 767.98px) {
    .tm-mobile-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));

        gap: 8px;
    }

    .tm-mobile-actions :deep(.btn) {
        display: inline-flex;
        width: 100% !important;
        height: 44px !important;
        min-width: 0;
        min-height: 44px !important;
        margin: 0 !important;

        white-space: nowrap;
    }

    .tm-mobile-actions :deep(.btn:only-child) {
        grid-column: 1 / -1;
    }

    .tm-move-trigger {
        display: inline-flex;
    }

    .tm-move-sheet-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1070;

        display: flex;
        align-items: flex-end;

        background: rgba(9, 30, 66, 0.48);
    }

    .tm-move-sheet {
        width: 100%;
        max-height: min(78dvh, 560px);
        padding: 8px 14px calc(16px + env(safe-area-inset-bottom));

        overflow-y: auto;

        color: var(--app-text);

        border-radius: 18px 18px 0 0;

        background: var(--app-surface);

        box-shadow: 0 -10px 30px rgba(9, 30, 66, 0.2);
    }

    .tm-move-sheet__handle {
        width: 38px;
        height: 4px;
        margin: 0 auto 12px;

        border-radius: 4px;

        background: var(--app-border);
    }

    .tm-move-sheet__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        gap: 12px;
        margin-bottom: 12px;
    }

    .tm-move-sheet__header h6 {
        margin: 0 0 3px;

        font-size: 1rem;
        font-weight: 700;
    }

    .tm-move-sheet__header p {
        margin: 0;

        color: var(--app-text-muted);

        font-size: 0.8rem;
        line-height: 1.45;
    }

    .tm-move-sheet__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        padding: 0;

        color: var(--app-text-muted);

        border: 1px solid var(--app-border);
        border-radius: 50%;

        background: transparent;
    }

    .tm-move-sheet__options {
        display: flex;
        flex-direction: column;

        gap: 8px;
    }

    .tm-move-sheet__option {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        align-items: center;

        gap: 10px;
        width: 100%;
        min-height: 52px;
        padding: 10px 12px;

        color: var(--app-text);
        text-align: left;

        border: 1px solid var(--app-border);
        border-radius: 12px;

        background: rgba(127, 127, 127, 0.05);
    }

    .tm-move-sheet__column {
        overflow: hidden;

        font-size: 0.9rem;
        font-weight: 700;

        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tm-move-sheet__count,
    .tm-move-sheet__option > i {
        color: var(--app-text-muted);

        font-size: 0.75rem;
    }

    .tm-body {
        margin-right: 0;
        margin-left: 0;
    }

    .tm-main,
    .tm-side {
        padding-right: 0;
        padding-left: 0;
    }

    .tm-main {
        margin-bottom: 12px !important;
    }

    .tm-title {
        height: auto;
        min-height: 22px;
        padding: 0;
        font-size: 1.1rem;
        line-height: 1.35;
        color: var(--app-text);
        overflow-wrap: anywhere;
    }

    .tm-box,
    .tm-panel {
        padding: 12px;
    }

    .comment {
        gap: 8px;
    }

    .comment__avatar {
        width: 30px;
        height: 30px;
    }

    .comment__head {
        align-items: flex-start;
        flex-direction: column;
        gap: 0;
    }
}
</style>
