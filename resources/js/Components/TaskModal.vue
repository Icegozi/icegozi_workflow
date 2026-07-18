<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import Btn from '@/Components/Btn.vue';
import { renderMarkdown } from '@/composables/useMarkdown';
import { avatarSrc } from '@/composables/useSocialLinks';
import { showAppAlert, showAppConfirm } from '@/composables/useAppAlert';

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
const moveSearch = ref('');
const moveSearchInput = ref(null);
const showHandoverPicker = ref(false);
const handoverMembers = ref([]);
const handoverWrap = ref(null);
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const loadTask = async () => {
    const { data } = await axios.get(route('tasks.show', props.taskId));
    task.value = data.task;
    loading.value = false;
};

onMounted(loadTask);
const closeHandoverOnOutsideClick = (event) => {
    if (showHandoverPicker.value && !handoverWrap.value?.contains(event.target)) {
        showHandoverPicker.value = false;
    }
};
onMounted(() => document.addEventListener('mousedown', closeHandoverOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', closeHandoverOnOutsideClick));

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

const normalized = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/đ/g, 'd');

const recentMoveStorageKey = computed(() => `board:${props.boardId}:recent-move-columns`);
const recentMoveIds = ref([]);

const readRecentMoveColumns = () => {
    try {
        const saved = JSON.parse(window.localStorage.getItem(recentMoveStorageKey.value) || '[]');
        recentMoveIds.value = Array.isArray(saved) ? saved.map(Number) : [];
    } catch {
        recentMoveIds.value = [];
    }
};

const recentMoveTargets = computed(() => recentMoveIds.value
    .map((id) => moveTargets.value.find((column) => column.id === id))
    .filter(Boolean));

const filteredMoveTargets = computed(() => {
    const query = normalized(moveSearch.value.trim());
    if (!query) return moveTargets.value;
    return moveTargets.value.filter((column) => normalized(column.name).includes(query));
});

const remainingMoveTargets = computed(() => {
    const recentIds = new Set(recentMoveTargets.value.map((column) => column.id));
    return moveTargets.value.filter((column) => !recentIds.has(column.id));
});

const openMoveSheet = async () => {
    moveSearch.value = '';
    readRecentMoveColumns();
    showMoveSheet.value = true;
    await nextTick();
    moveSearchInput.value?.focus();
};

const closeMoveSheet = () => {
    showMoveSheet.value = false;
    moveSearch.value = '';
};

const rememberMoveColumn = (columnId) => {
    const ids = [columnId, ...recentMoveIds.value.filter((id) => id !== columnId)].slice(0, 5);
    recentMoveIds.value = ids;
    try {
        window.localStorage.setItem(recentMoveStorageKey.value, JSON.stringify(ids));
    } catch {
        // Không ảnh hưởng đến thao tác chuyển task nếu trình duyệt chặn localStorage.
    }
};

const moveTask = (column) => {
    if (!task.value || !column) {
        return;
    }

    rememberMoveColumn(column.id);
    closeMoveSheet();
    emit('move-task', {
        taskId: task.value.id,
        columnId: column.id,
    });
    emit('close');
};
const canRequestHandover = computed(() => !props.canManage && task.value?.assignees?.some((user) => user.id === currentUserId.value));
const toggleHandoverPicker = async () => {
    showHandoverPicker.value = !showHandoverPicker.value;
    if (!showHandoverPicker.value || handoverMembers.value.length) return;
    try {
        const { data } = await axios.get(route('boards.assignedUsers', props.boardId));
        handoverMembers.value = (data.users || []).filter((user) => user.id !== currentUserId.value);
    } catch (error) {
        showHandoverPicker.value = false;
        showAppAlert('Không thể tải danh sách thành viên.');
    }
};
const requestHandover = async (user) => {
    try {
        await axios.post(route('tasks.handover-requests.store', props.taskId), { to_user_id: user.id });
        showHandoverPicker.value = false;
        showAppAlert('Đã gửi yêu cầu. Task chỉ được chuyển khi người nhận đồng ý.', 'success');
    } catch (error) { showAppAlert(error.response?.data?.message || 'Không thể gửi yêu cầu.'); }
};

const acceptHandover = async (request) => {
    if (!await showAppConfirm(`Nhận bàn giao task từ ${request.from_name}?`, 'warning')) return;
    try {
        await axios.post(route('task-handover-requests.accept', request.id));
        await loadTask();
        showAppAlert('Bạn đã nhận bàn giao task.', 'success');
    } catch (error) { showAppAlert(error.response?.data?.message || 'Không thể nhận bàn giao.'); }
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
                <div ref="handoverWrap" class="assignee-view mb-4">
                    <span v-for="a in task.assignees" :key="a.id" class="assignee-pill">
                        <img :src="avatarSrc(a.avatar_url)" class="rounded-circle" width="24" height="24" :title="a.name">
                        <span>{{ a.name }}</span>
                        <button v-if="canRequestHandover && a.id === currentUserId" type="button" class="assignee-handover"
                            title="Yêu cầu bàn giao" aria-label="Yêu cầu bàn giao task" @click="toggleHandoverPicker">
                            <i class="fas fa-right-left"></i>
                        </button>
                    </span>
                    <span v-if="!task.assignees || !task.assignees.length" class="text-muted small">Chưa có ai.</span>
                    <div v-if="showHandoverPicker" class="handover-popover">
                        <div class="handover-popover__head">
                            <strong>Yêu cầu bàn giao</strong>
                            <span>Chọn người nhận task</span>
                        </div>
                        <button v-for="member in handoverMembers" :key="member.id" type="button" class="handover-option"
                            @click="requestHandover(member)">
                            <img :src="avatarSrc(member.avatar_url)" class="rounded-circle" width="28" height="28" :alt="member.name">
                            <span><strong>{{ member.name }}</strong><small>{{ member.email }}</small></span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <span v-if="!handoverMembers.length" class="handover-popover__empty">Không có thành viên phù hợp.</span>
                    </div>
                </div>
                <article v-for="request in task.incoming_handover_requests" :key="request.id" class="handover-request-card">
                    <img :src="avatarSrc(request.from_avatar_url)" class="rounded-circle handover-request-card__avatar" width="40" height="40" :alt="request.from_name">
                    <div class="handover-request-card__body">
                        <span class="handover-request-card__eyebrow"><i class="fas fa-share"></i> Yêu cầu bàn giao</span>
                        <strong>{{ request.from_name }} muốn bàn giao task này cho bạn</strong>
                        <p>Nhận bàn giao để trở thành người phụ trách.</p>
                    </div>
                    <button type="button" class="handover-request-card__action" title="Nhận bàn giao"
                        aria-label="Nhận bàn giao" @click="acceptHandover(request)">
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </article>

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
                    <img :src="avatarSrc(c.user_avatar)" class="rounded-circle comment__avatar"
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
                        @click="openMoveSheet">
                        Di chuyển
                    </Btn>
                </div>

                <div class="tm-panel">
                    <h6 class="side-title">Lịch sử</h6>
                    <div class="history-scroll">
                        <div v-for="h in task.task_histories" :key="h.id" class="history-item">
                            <img :src="avatarSrc(h.user_avatar)" class="rounded-circle" width="24" height="24">
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
        <div v-if="showMoveSheet" class="tm-move-sheet-backdrop" @click.self="closeMoveSheet">
            <section class="tm-move-sheet" role="dialog" aria-modal="true" aria-labelledby="move-task-title" @keydown.esc="closeMoveSheet">
                <div class="tm-move-sheet__handle"></div>
                <header class="tm-move-sheet__header">
                    <div>
                        <h6 id="move-task-title">Di chuyển công việc</h6>
                        <p>Chọn cột đích. Công việc sẽ được thêm vào cuối cột.</p>
                    </div>
                    <button type="button" class="tm-move-sheet__close" aria-label="Đóng"
                        @click="closeMoveSheet">
                        <i class="fas fa-times"></i>
                    </button>
                </header>

                <label class="tm-move-sheet__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input ref="moveSearchInput" v-model="moveSearch" type="search"
                        placeholder="Tìm tên cột..." autocomplete="off" aria-label="Tìm cột đích">
                    <button v-if="moveSearch" type="button" aria-label="Xoá tìm kiếm" @click="moveSearch = ''">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </label>

                <div class="tm-move-sheet__options">
                    <template v-if="moveSearch">
                        <button v-for="column in filteredMoveTargets" :key="column.id" type="button"
                            class="tm-move-sheet__option" @click="moveTask(column)">
                            <span class="tm-move-sheet__column">{{ column.name }}</span>
                            <span class="tm-move-sheet__count">{{ column.tasks?.length || 0 }} công việc</span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                        <p v-if="!filteredMoveTargets.length" class="tm-move-sheet__empty">
                            Không tìm thấy cột phù hợp.
                        </p>
                    </template>

                    <template v-else>
                        <div v-if="recentMoveTargets.length" class="tm-move-sheet__section-title">
                            <i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Gần đây
                        </div>
                        <button v-for="column in recentMoveTargets" :key="`recent-${column.id}`" type="button"
                            class="tm-move-sheet__option tm-move-sheet__option--recent" @click="moveTask(column)">
                            <span class="tm-move-sheet__column">{{ column.name }}</span>
                            <span class="tm-move-sheet__count">{{ column.tasks?.length || 0 }} công việc</span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>

                        <div v-if="recentMoveTargets.length && remainingMoveTargets.length" class="tm-move-sheet__section-title">
                            Tất cả cột
                        </div>
                        <button v-for="column in remainingMoveTargets" :key="column.id" type="button"
                            class="tm-move-sheet__option" @click="moveTask(column)">
                            <span class="tm-move-sheet__column">{{ column.name }}</span>
                            <span class="tm-move-sheet__count">{{ column.tasks?.length || 0 }} công việc</span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </template>
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

.assignee-view { position: relative; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }

.assignee-handover {
    display: inline-grid;
    width: 21px;
    height: 21px;
    padding: 0;
    place-items: center;
    color: var(--app-accent, #663300);
    font-size: .68rem;
    border: 0;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
    transition: color .16s ease, background-color .16s ease, transform .16s ease;
}

.assignee-handover:hover,
.assignee-handover:focus-visible {
    color: #fff;
    outline: 0;
    background: var(--app-accent, #663300);
    transform: translateX(2px);
}

.handover-popover {
    position: absolute;
    z-index: 10;
    top: calc(100% + 7px);
    left: 0;
    width: min(330px, 100%);
    overflow: hidden;
    border: 1px solid var(--app-border);
    border-radius: 11px;
    background: var(--app-surface);
    box-shadow: 0 12px 28px rgba(9, 30, 66, .2);
}

.handover-popover__head { padding: 10px 12px; border-bottom: 1px solid var(--app-border); }
.handover-popover__head strong, .handover-popover__head span { display: block; }
.handover-popover__head strong { font-size: .82rem; color: var(--app-text); }
.handover-popover__head span { margin-top: 2px; font-size: .7rem; color: var(--app-text-muted); }

.handover-option {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 9px;
    padding: 9px 12px;
    text-align: left;
    color: var(--app-text);
    border: 0;
    border-bottom: 1px solid var(--app-border);
    background: transparent;
    cursor: pointer;
}

.handover-option:last-of-type { border-bottom: 0; }
.handover-option span { min-width: 0; flex: 1; }
.handover-option strong, .handover-option small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.handover-option strong { font-size: .8rem; }.handover-option small { color: var(--app-text-muted); font-size: .68rem; }
.handover-option > i { color: var(--app-text-muted); font-size: .72rem; }
.handover-option:hover { color: #fff; background: #663300; }
.handover-option:hover small, .handover-option:hover > i { color: rgba(255, 255, 255, .78); }
.handover-popover__empty { display: block; padding: 12px; color: var(--app-text-muted); font-size: .75rem; }

.handover-request-card {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 12px;
    margin-bottom: 16px;
    border: 1px solid rgba(102, 51, 0, .28);
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(102, 51, 0, .1), rgba(102, 51, 0, .025));
}

.handover-request-card__avatar { flex: 0 0 40px; border: 2px solid rgba(102, 51, 0, .2); }
.handover-request-card__body { min-width: 0; flex: 1; }
.handover-request-card__eyebrow { display: block; margin-bottom: 3px; color: #663300; font-size: .65rem; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
.handover-request-card__body strong { display: block; color: var(--app-text); font-size: .82rem; line-height: 1.35; }
.handover-request-card__body p { margin: 3px 0 0; color: var(--app-text-muted); font-size: .72rem; }
.handover-request-card__action {
    display: inline-grid;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    padding: 0;
    place-items: center;
    color: #663300;
    border: 1px solid rgba(102, 51, 0, .3);
    border-radius: 50%;
    background: rgba(255, 255, 255, .62);
    cursor: pointer;
    transition: color .18s ease, background-color .18s ease, transform .18s ease;
}
.handover-request-card__action:hover,
.handover-request-card__action:focus-visible { color: #fff; outline: 0; background: #663300; }

@media (max-width: 575.98px) {
    .handover-request-card { flex-wrap: wrap; }
    .handover-request-card__action { margin-left: auto; }
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

/* Hành động chính dùng màu nhận diện của ứng dụng; thao tác phụ chỉ nhấn bằng
   viền để không cạnh tranh thị giác với nút Chỉnh sửa. */
.tm-edit-trigger {
    color: #fff !important;
    border-color: var(--app-accent, #663300) !important;
    background: var(--app-accent, #663300) !important;
}

.tm-edit-trigger:hover,
.tm-edit-trigger:focus {
    color: #fff !important;
    border-color: var(--app-accent-dark, #4a2600) !important;
    background: var(--app-accent-dark, #4a2600) !important;
}

.tm-move-trigger:hover,
.tm-move-trigger:focus {
    color: var(--app-text) !important;
    border-color: var(--app-accent, #663300) !important;
    background: transparent !important;
    box-shadow: none !important;
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

    .tm-move-sheet__search {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 44px;
        margin-bottom: 12px;
        padding: 0 11px;

        color: var(--app-text-muted);

        border: 1px solid var(--app-border);
        border-radius: 10px;

        background: var(--app-bg);
    }

    .tm-move-sheet__search:focus-within {
        border-color: var(--app-accent);
        box-shadow: 0 0 0 3px rgba(102, 51, 0, 0.12);
    }

    .tm-move-sheet__search input {
        width: 100%;
        min-width: 0;
        height: 100%;
        padding: 0;

        color: var(--app-text);

        border: 0;
        outline: 0;
        background: transparent;
    }

    .tm-move-sheet__search button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;

        color: var(--app-text-muted);

        border: 0;
        border-radius: 50%;
        background: transparent;
    }

    .tm-move-sheet__options {
        display: flex;
        flex-direction: column;

        gap: 8px;
    }

    .tm-move-sheet__section-title {
        margin-top: 4px;
        padding: 5px 2px 1px;

        color: var(--app-text-muted);

        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .tm-move-sheet__option--recent {
        border-color: color-mix(in srgb, var(--app-accent) 35%, var(--app-border));
        background: color-mix(in srgb, var(--app-accent) 7%, var(--app-surface));
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

    .tm-move-sheet__empty {
        margin: 8px 0;
        padding: 18px 12px;

        color: var(--app-text-muted);
        text-align: center;

        border: 1px dashed var(--app-border);
        border-radius: 10px;
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

/* Desktop không thể dựa vào kéo qua hàng chục cột. Dùng cùng bộ chọn cột như
   mobile, nhưng hiển thị ở giữa màn hình để không che mất toàn bộ task modal. */
@media (min-width: 768px) {
    .tm-mobile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .tm-mobile-actions :deep(.btn) {
        flex: 1 1 150px;
    }

    .tm-move-trigger {
        display: inline-flex;
    }

    .tm-move-sheet-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1070;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 24px;
        background: rgba(9, 30, 66, 0.48);
    }

    .tm-move-sheet {
        width: min(560px, 100%);
        max-height: min(72dvh, 620px);
        padding: 20px;
        overflow-y: auto;

        color: var(--app-text);
        border-radius: 16px;
        background: var(--app-surface);
        box-shadow: 0 18px 48px rgba(9, 30, 66, 0.28);
    }

    .tm-move-sheet__handle { display: none; }

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

    .tm-move-sheet__close,
    .tm-move-sheet__search button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        color: var(--app-text-muted);
        background: transparent;
    }

    .tm-move-sheet__close {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border: 1px solid var(--app-border);
        border-radius: 50%;
    }

    .tm-move-sheet__search {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 44px;
        margin-bottom: 12px;
        padding: 0 11px;
        color: var(--app-text-muted);
        border: 1px solid var(--app-border);
        border-radius: 10px;
        background: var(--app-bg);
    }

    .tm-move-sheet__search:focus-within {
        border-color: var(--app-accent);
        box-shadow: 0 0 0 3px rgba(102, 51, 0, 0.12);
    }

    .tm-move-sheet__search input {
        width: 100%;
        min-width: 0;
        height: 100%;
        padding: 0;
        color: var(--app-text);
        border: 0;
        outline: 0;
        background: transparent;
    }

    .tm-move-sheet__search button {
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 50%;
    }

    .tm-move-sheet__options {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .tm-move-sheet__section-title {
        margin-top: 4px;
        padding: 5px 2px 1px;
        color: var(--app-text-muted);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
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

    .tm-move-sheet__option:hover {
        border-color: var(--app-accent);
        background: rgba(102, 51, 0, 0.06);
    }

    .tm-move-sheet__option--recent {
        border-color: color-mix(in srgb, var(--app-accent) 35%, var(--app-border));
        background: color-mix(in srgb, var(--app-accent) 7%, var(--app-surface));
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

    .tm-move-sheet__empty {
        margin: 8px 0;
        padding: 18px 12px;
        color: var(--app-text-muted);
        text-align: center;
        border: 1px dashed var(--app-border);
        border-radius: 10px;
    }
}
</style>
