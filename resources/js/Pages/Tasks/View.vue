<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';
import TaskComments from '@/Components/TaskComments.vue';
import { renderMarkdown } from '@/composables/useMarkdown';
import { showAppAlert } from '@/composables/useAppAlert';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    boardName: { type: String, default: '' },
    boardCode: { type: Number, required: true },
    taskCode: { type: Number, required: true },
    code: { type: String, required: true },
    displayCode: { type: String, required: true },
    canEdit: { type: Boolean, default: false },
});

const loading = ref(true);
const task = ref(null);

const loadTask = async () => {
    try {
        const { data } = await axios.get(route('tasks.show', props.taskId));
        task.value = data.task;
        loading.value = false;
    } catch (e) {
        // Task đã bị xoá / không còn -> chuyển về board.
        if (e.response?.status === 404) {
            router.visit(route('boards.show', props.boardId));
            return;
        }
        loading.value = false;
    }
};
onMounted(loadTask);

const PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#e5484d', bg: '#ffe5e5' },
    high: { label: 'Cao', color: '#f76808', bg: '#ffefe5' },
    normal: { label: 'Bình thường', color: '#006adc', bg: '#e6f0ff' },
    low: { label: 'Thấp', color: '#18794e', bg: '#e5f5ec' },
};
const priority = computed(() => (task.value ? PRIORITY[task.value.priority] || null : null));

// Tích/bỏ tích checklist: mọi người xem được trang này đều được phép (kể cả quyền view).
// Không cho thêm/xoá mục — chỉ đổi trạng thái is_done. Cập nhật lạc quan rồi gọi API.
const togglingChecklist = ref(false);
const toggleChecklist = async (item) => {
    if (togglingChecklist.value) return;
    togglingChecklist.value = true;
    const prev = item.is_done;
    item.is_done = !prev;
    try {
        await axios.put(route('checklists.update', item.id), { is_done: item.is_done });
    } catch (e) {
        item.is_done = prev;
        showAppAlert(e.response?.data?.message || 'Không thể cập nhật mục checklist.');
    } finally {
        togglingChecklist.value = false;
    }
};

const checklistDone = computed(() => (task.value?.checklists || []).filter((c) => c.is_done).length);
const checklistTotal = computed(() => (task.value?.checklists || []).length);
const checklistPct = computed(() =>
    checklistTotal.value ? Math.round((checklistDone.value / checklistTotal.value) * 100) : 0
);

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;

// ---- Permalink + copy ---- (URL đẹp /b-{board_code}/tasks/{task_code})
const taskUrl = computed(() => route('tasks.permalink', { boardCode: props.boardCode, taskCode: props.taskCode }));
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

// Quay lại: nếu tới từ "Task của tôi" (?return=my-tasks) thì về đó, ngược lại về bảng.
const backToBoard = () => {
    const ret = new URLSearchParams(window.location.search).get('return');
    if (ret === 'my-tasks') {
        router.visit(route('my-tasks.index'));
    } else {
        router.visit(route('boards.show', props.boardId));
    }
};

// return=view: để trang Edit khi bấm "Quay lại" trở về đúng trang xem này.
const goEdit = () => router.visit(route('tasks.edit', { taskCode: props.code, return: 'view' }));
</script>

<template>
    <Head :title="`Công việc #${displayCode}`" />
    <AuthenticatedLayout>
        <div class="task-view">
            <!-- Thanh công cụ -->
            <div class="tv-toolbar">
                <button type="button" class="task-back" title="Quay lại" aria-label="Quay lại" @click="backToBoard">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </button>
                <span class="task-code">#{{ displayCode }}</span>
                <span class="flex-grow-1"></span>
                <Btn v-if="canEdit" type="button" variant="black" class="btn-sm" icon="fas fa-pen" @click="goEdit">
                    Chỉnh sửa
                </Btn>
            </div>

            <div v-if="loading" class="text-center py-5 text-muted">
                <i class="fas fa-spinner fa-spin fa-lg"></i>
                <div class="mt-2">Đang tải...</div>
            </div>

            <div v-else class="row tv-body">
                <!-- Cột trái -->
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <p class="tv-breadcrumb">
                        <i class="fas fa-columns mr-1"></i>Trong danh sách:
                        <strong>{{ task.column_name }}</strong>
                    </p>
                    <h4 class="tv-title">{{ task.title }}</h4>

                    <h6 class="sect"><i class="fas fa-user-friends"></i>Người phụ trách</h6>
                    <div class="d-flex align-items-center flex-wrap mb-4" style="gap:8px;">
                        <span v-for="a in task.assignees" :key="a.id" class="assignee-pill">
                            <img :src="a.avatar_url || avatar(a.email)" class="rounded-circle" width="24" height="24" :title="a.name">
                            <span>{{ a.name }}</span>
                        </span>
                        <span v-if="!task.assignees || !task.assignees.length" class="text-muted small">Chưa có ai.</span>
                    </div>

                    <h6 class="sect"><i class="fas fa-align-left"></i>Mô tả</h6>
                    <div class="tv-box mb-4">
                        <div v-if="task.description" class="md-content" v-html="renderMarkdown(task.description)"></div>
                        <span v-else class="text-muted small"><em>Chưa có mô tả.</em></span>
                    </div>

                    <h6 class="sect">
                        <i class="fas fa-tasks"></i>Checklist
                        <span v-if="checklistTotal" class="sect-count">{{ checklistDone }}/{{ checklistTotal }}</span>
                    </h6>
                    <div class="mb-4">
                        <div v-if="checklistTotal" class="checklist-progress mb-2">
                            <div class="checklist-progress__bar" :style="{ width: checklistPct + '%' }"></div>
                        </div>
                        <div v-for="item in task.checklists" :key="item.id" class="checklist-item">
                            <button type="button" class="checklist-toggle" :disabled="togglingChecklist"
                                :title="item.is_done ? 'Bỏ đánh dấu hoàn thành' : 'Đánh dấu hoàn thành'"
                                @click="toggleChecklist(item)">
                                <i :class="item.is_done ? 'far fa-check-square text-success' : 'far fa-square text-muted'"></i>
                            </button>
                            <span :class="{ done: item.is_done }">{{ item.title }}</span>
                        </div>
                        <span v-if="!task.checklists || !task.checklists.length" class="text-muted small">Chưa có mục nào.</span>
                    </div>

                    <TaskComments :task-id="taskId" :board-id="boardId"
                        :comments="task.comments || []" @updated="loadTask" />
                </div>

                <!-- Cột phải -->
                <div class="col-lg-4 tv-side">
                    <div class="tv-panel mb-3">
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

                        <div class="info-row">
                            <span class="info-label">Ngày hết hạn</span>
                            <strong class="tv-due">{{ task.formatted_due_date || '—' }}</strong>
                        </div>

                        <div class="info-row mb-0">
                            <span class="info-label">Đường dẫn công việc</span>
                            <div class="link-chip" @click="copyLink" :title="taskUrl">
                                <i class="fas fa-link link-chip__icon"></i>
                                <span class="link-chip__url">{{ taskUrl }}</span>
                                <span class="link-chip__copy">
                                    <i :class="linkCopied ? 'fas fa-check text-success' : 'fas fa-copy'"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="tv-panel">
                        <h6 class="side-title">Lịch sử</h6>
                        <div class="history-scroll">
                            <div v-for="h in task.task_histories" :key="h.id" class="history-item">
                                <img :src="h.user_avatar" class="rounded-circle" width="24" height="24">
                                <div class="history-item__text">
                                    <!-- note là HTML dựng sẵn (đã escape ở server) -->
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
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.task-view {
    max-width: 1100px;
    margin: 0 auto;
}

/* ---------------- Thanh công cụ ---------------- */
.tv-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

/* Đồng bộ nút quay lại dạng icon tròn với trang Hồ sơ cá nhân. */
.task-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    padding: 0;
    border: 1px solid var(--app-border);
    border-radius: 50%;
    color: var(--app-text);
    background: var(--app-surface);
    cursor: pointer;
    transition: color 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}

.task-back:hover,
.task-back:focus-visible {
    border-color: var(--app-accent);
    color: #fff;
    background: var(--app-accent);
    transform: translateX(-2px);
    outline: none;
}

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

/* Đường dẫn công việc: hộp nhỏ gọn, bấm cả hộp để sao chép (giống modal) */
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
.link-chip:hover .link-chip__copy { color: var(--app-accent); }

/* ---------------- Bố cục ---------------- */
.tv-breadcrumb {
    font-size: 0.8rem;
    color: var(--app-text-muted);
    margin-bottom: 6px;
}

.tv-title {
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

.tv-box {
    background: rgba(127, 127, 127, 0.05);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    padding: 14px 16px;
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
}

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

.checklist-toggle {
    border: 0;
    background: transparent;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    font-size: 1rem;
    flex-shrink: 0;
}
.checklist-toggle:disabled { cursor: default; }
.checklist-toggle:hover:not(:disabled) i { opacity: 0.75; }

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

.tv-side { align-self: flex-start; }

.tv-panel {
    background: rgba(127, 127, 127, 0.04);
    border: 1px solid var(--app-border);
    border-radius: 12px;
    padding: 16px 18px;
}

.info-row { margin-bottom: 14px; }

.info-label {
    display: block;
    font-size: 0.72rem;
    color: var(--app-text-muted);
    margin-bottom: 4px;
}

.info-empty { color: var(--app-text-muted); }
.tv-due { color: var(--app-text); font-size: 0.9rem; }

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

/* ---------------- Markdown ---------------- */
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
    .tv-toolbar {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 8px;
        margin-bottom: 14px;
    }

    .tv-toolbar .flex-grow-1 {
        display: none;
    }

    .tv-toolbar .task-code {
        align-self: center;
        justify-self: start;
        margin-left: 0;
    }

    .tv-body {
        margin-right: 0;
        margin-left: 0;
    }

    .tv-body > [class*="col-"] {
        padding-right: 0;
        padding-left: 0;
    }

    .tv-title {
        font-size: 1.12rem;
        margin-bottom: 16px;
    }

    .tv-box,
    .tv-panel {
        padding: 12px;
    }

    .tv-side {
        margin-top: 12px;
    }

    .comment {
        gap: 8px;
    }

    .comment__head {
        align-items: flex-start;
        flex-direction: column;
        gap: 0;
    }
}
</style>
