<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Btn from '@/Components/Btn.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import TaskComments from '@/Components/TaskComments.vue';
import { renderMarkdown } from '@/composables/useMarkdown';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    boardName: { type: String, default: '' },
    boardCode: { type: Number, required: true },
    taskCode: { type: Number, required: true },
    code: { type: String, required: true },
    displayCode: { type: String, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    statuses: { type: Array, default: () => [] },
    boardLabels: { type: Array, default: () => [] },
});

const loading = ref(true);
const task = ref(null);
const title = ref('');
const description = ref('');
const dueDate = ref('');
const priority = ref('normal');
const statusId = ref(null);
const newChecklistItem = ref('');
const boardMembers = ref([]);
const showAssigneePicker = ref(false);
const saving = ref(false);

// ---- Nhãn ----
const labels = ref([...props.boardLabels]);   // bảng nhãn của board (có thể thêm mới)
const showLabelPanel = ref(false);
const newLabelName = ref('');
const LABEL_COLORS = ['#e5484d', '#f76808', '#ffb224', '#18794e', '#006adc', '#8e4ec6', '#7a869a'];
const newLabelColor = ref(LABEL_COLORS[0]);


// ---- Điều khiển picker / bottom sheet ----
const closeAssigneePicker = () => {
    showAssigneePicker.value = false;
};

const closeLabelPanel = () => {
    showLabelPanel.value = false;
};

const toggleAssigneePicker = () => {
    showLabelPanel.value = false;
    showAssigneePicker.value = !showAssigneePicker.value;
};

const toggleLabelPanel = () => {
    showAssigneePicker.value = false;
    showLabelPanel.value = !showLabelPanel.value;
};

const closeAllPickers = () => {
    closeAssigneePicker();
    closeLabelPanel();
};

const isMobileViewport = () =>
    typeof window !== 'undefined' && window.matchMedia('(max-width: 575.98px)').matches;

let bodyOverflowBeforeSheet = '';
let bodyScrollLocked = false;

const syncMobileSheetScroll = () => {
    if (typeof document === 'undefined') return;

    const shouldLock = isMobileViewport()
        && (showAssigneePicker.value || showLabelPanel.value);

    if (shouldLock && !bodyScrollLocked) {
        bodyOverflowBeforeSheet = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        bodyScrollLocked = true;
    } else if (!shouldLock && bodyScrollLocked) {
        document.body.style.overflow = bodyOverflowBeforeSheet;
        bodyScrollLocked = false;
    }
};

watch([showAssigneePicker, showLabelPanel], syncMobileSheetScroll);

const PRIORITIES = [
    { value: 'low', label: 'Thấp' },
    { value: 'normal', label: 'Bình thường' },
    { value: 'high', label: 'Cao' },
    { value: 'urgent', label: 'Khẩn cấp' },
];

const statusOptions = computed(() => props.statuses.map((s) => ({ value: s.id, label: s.name })));

// ---- Permalink công việc (https://{miền}/b-{board_code}/tasks/{task_code}) ----
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
    window.setTimeout(() => { linkCopied.value = false; }, 1500);
};

// Nạp task từ server. resetForm=true chỉ dùng khi mở trang: gán lại các ô nhập.
// Các thao tác phụ (bình luận, checklist, nhãn...) gọi resetForm=false để KHÔNG
// ghi đè phần người dùng đang gõ dở ở tiêu đề/mô tả (tránh mất dữ liệu chưa lưu).
const fetchTask = async (resetForm = false) => {
    let data;
    try {
        ({ data } = await axios.get(route('tasks.show', props.taskId)));
    } catch (e) {
        // Task đã bị xoá / không còn -> chuyển về board.
        if (e.response?.status === 404) {
            router.visit(route('boards.show', props.boardId));
            return;
        }
        throw e;
    }
    task.value = data.task;
    if (resetForm) {
        title.value = data.task.title;
        description.value = data.task.description || '';
        dueDate.value = data.task.due_date || '';
        priority.value = data.task.priority || 'normal';
        statusId.value = data.task.status?.id ?? null;
    }
    loading.value = false;
};

const loadMembers = async () => {
    try {
        const { data } = await axios.get(route('boards.assignedUsers', props.boardId));
        boardMembers.value = data.users || [];
    } catch (e) { /* ignore */ }
};

const assignableMembers = computed(() => {
    const assigned = new Set((task.value?.assignees || []).map((a) => a.id));
    return boardMembers.value.filter((u) => !assigned.has(u.id));
});

onMounted(async () => {
    await fetchTask(true);
    loadMembers();   // cần cho cả gợi ý @mention, không chỉ khi canManage
});


const returnTo = () => new URLSearchParams(window.location.search).get('return');

// Quay lại theo nơi đã tới:
//  - ?return=view     -> về trang XEM chi tiết công việc (/tasks/{id})
//  - ?return=my-tasks -> về danh sách "Task của tôi"
//  - còn lại          -> về bảng
const backToBoard = () => {
    const ret = returnTo();
    if (ret === 'view') {
        router.visit(route('tasks.permalink', { boardCode: props.boardCode, taskCode: props.taskCode }));
    } else if (ret === 'my-tasks') {
        router.visit(route('my-tasks.index'));
    } else {
        router.visit(route('boards.show', props.boardId));
    }
};

// Task đã bị xoá -> luôn về trang board (không về view của chính nó vì đã không còn).
const backAfterDelete = () => {
    router.visit(route('boards.show', props.boardId));
};

const saveTask = async () => {
    saving.value = true;
    try {
        await axios.put(route('tasks.update', props.taskId), {
            title: title.value,
            description: description.value,
            due_date: dueDate.value || null,
            priority: priority.value,
            status_id: statusId.value,
        });
        await fetchTask(false);
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể lưu thay đổi.');
    } finally {
        saving.value = false;
    }
};

const deleteTask = async () => {
    if (!confirm('Xoá công việc này?')) return;
    try {
        await axios.delete(route('tasks.destroy', props.taskId));
        backAfterDelete();
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể xoá công việc.');
    }
};

// ---- Checklist ----
const addChecklist = async () => {
    const t = newChecklistItem.value.trim();
    if (!t) return;
    try {
        await axios.post(route('checklists.store', props.taskId), { title: t });
        newChecklistItem.value = '';
        await fetchTask(false);
    } catch (e) { alert(e.response?.data?.message || 'Không thể thêm mục.'); }
};
const toggleChecklist = async (item) => {
    try {
        await axios.put(route('checklists.update', item.id), { is_done: !item.is_done });
        await fetchTask(false);
    } catch (e) { alert('Không thể cập nhật.'); }
};
const deleteChecklist = async (item) => {
    try {
        await axios.delete(route('checklists.destroy', item.id));
        await fetchTask(false);
    } catch (e) { alert('Không thể xoá mục.'); }
};

const checklistDone = computed(() => (task.value?.checklists || []).filter((c) => c.is_done).length);
const checklistTotal = computed(() => (task.value?.checklists || []).length);
const checklistPct = computed(() =>
    checklistTotal.value ? Math.round((checklistDone.value / checklistTotal.value) * 100) : 0
);

// ---- Người phụ trách ----
const addAssignee = async (user) => {
    showAssigneePicker.value = false;   // đóng ngay để tránh click lần 2 cùng người -> 409
    try {
        await axios.post(route('tasks.assignees.store', props.taskId), { user_id: user.id });
        await fetchTask(false);
    } catch (e) {
        showAssigneePicker.value = true;
        alert(e.response?.data?.message || 'Không thể thêm người phụ trách.');
    }
};
const removeAssignee = async (user) => {
    try {
        await axios.delete(route('tasks.assignees.destroy', [props.taskId, user.id]));
        await fetchTask(false);
    } catch (e) { alert('Không thể gỡ người phụ trách.'); }
};

const hasLabel = (id) => (task.value?.labels || []).some((l) => l.id === id);

const toggleLabel = async (label) => {
    try {
        if (hasLabel(label.id)) {
            await axios.delete(route('tasks.labels.detach', [props.taskId, label.id]));
        } else {
            await axios.post(route('tasks.labels.attach', props.taskId), { label_id: label.id });
        }
        await fetchTask(false);
    } catch (e) { alert(e.response?.data?.message || 'Không thể cập nhật nhãn.'); }
};

const createLabel = async () => {
    const name = newLabelName.value.trim();
    try {
        const { data } = await axios.post(route('labels.store', props.boardId), {
            name: name || null,
            color: newLabelColor.value,
        });
        labels.value.push(data.label);
        newLabelName.value = '';
        await toggleLabel(data.label);   // gắn luôn vào task
    } catch (e) { alert(e.response?.data?.message || 'Không thể tạo nhãn.'); }
};

// Xoá hẳn nhãn khỏi bảng (gỡ khỏi mọi công việc do cascade ở DB).
const deleteLabel = async (label) => {
    if (!confirm(`Xoá nhãn "${label.name || 'Nhãn'}" khỏi bảng? Nhãn sẽ bị gỡ khỏi mọi công việc.`)) return;
    try {
        await axios.delete(route('labels.destroy', label.id));
        labels.value = labels.value.filter((l) => l.id !== label.id);
        if (task.value?.labels) {
            task.value.labels = task.value.labels.filter((l) => l.id !== label.id);
        }
    } catch (e) { alert(e.response?.data?.message || 'Không thể xoá nhãn.'); }
};

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;

// ---- Đóng popover khi click ra ngoài ----
const labelWrap = ref(null);
const assigneeWrap = ref(null);

const onDocClick = (e) => {
    // Nội dung bottom sheet được Teleport ra body, không xử lý như click ngoài popover.
    if (e.target.closest?.('.mobile-sheet-layer')) return;

    if (showLabelPanel.value && labelWrap.value && !labelWrap.value.contains(e.target)) {
        closeLabelPanel();
    }
    if (showAssigneePicker.value && assigneeWrap.value && !assigneeWrap.value.contains(e.target)) {
        closeAssigneePicker();
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape') closeAllPickers();
};

// mousedown (không phải click) để không xung đột với nút bật/tắt popover.
onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', syncMobileSheetScroll);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocClick);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', syncMobileSheetScroll);

    if (bodyScrollLocked) {
        document.body.style.overflow = bodyOverflowBeforeSheet;
        bodyScrollLocked = false;
    }
});
</script>

<template>
    <Head :title="`#${displayCode} - Chỉnh sửa`" />
    <AuthenticatedLayout>
        <div class="task-edit">
            <!-- Header -->
            <header class="te-header">
                <Btn type="button" variant="white" icon="fas fa-arrow-left" class="btn-sm" @click="backToBoard">
                    Quay lại
                </Btn>
                <span class="task-code">#{{ displayCode }}</span>
                <div class="te-header__title">
                    <div class="text-muted small text-truncate" :title="boardName">{{ boardName }}</div>
                    <h4 class="mb-0 text-truncate">{{ title || 'Công việc' }}</h4>
                </div>
            </header>

            <div v-if="loading" class="text-center py-5 text-muted">
                <i class="fas fa-spinner fa-spin fa-lg"></i>
                <div class="mt-2">Đang tải...</div>
            </div>

            <div v-else class="row te-body">
                <!-- Cột trái -->
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="panel">
                        <p class="text-muted small mb-2">
                            <i class="fas fa-columns mr-1"></i>Trong danh sách:
                            <strong>{{ task.column_name }}</strong>
                        </p>

                        <label class="sect-label mb-1">Tiêu đề</label>
                        <TextInput v-model="title" placeholder="Tiêu đề công việc..." group-class="mb-4"
                            :readonly="!canEdit" />

                        <!-- Người phụ trách -->
                        <h6 class="sect"><i class="fas fa-user-friends"></i>Người phụ trách</h6>
                        <div class="entity-list assignee-list mb-4">
                            <span v-for="a in task.assignees" :key="a.id" class="assignee-pill">
                                <img :src="a.avatar_url || avatar(a.email)" class="rounded-circle assignee-pill__avatar"
                                    width="24" height="24" :title="a.name" :alt="a.name">
                                <span class="assignee-pill__name" :title="a.name">{{ a.name }}</span>
                                <button v-if="canManage" type="button" class="pill-x"
                                    @click="removeAssignee(a)" title="Gỡ người phụ trách"
                                    :aria-label="`Gỡ ${a.name} khỏi công việc`">&times;</button>
                            </span>

                            <span v-if="!task.assignees || !task.assignees.length" class="entity-empty text-muted small">
                                Chưa có ai.
                            </span>

                            <div v-if="canManage" ref="assigneeWrap" class="entity-picker assignee-picker">
                                <button type="button" class="btn-round"
                                    @click="toggleAssigneePicker"
                                    title="Thêm người" aria-label="Thêm người phụ trách">
                                    <i class="fas fa-plus"></i>
                                </button>

                                <div v-if="showAssigneePicker" class="popover-card assignee-popover desktop-picker-panel">
                                    <div class="list-group list-group-flush">
                                        <a v-for="u in assignableMembers" :key="u.id" href="#"
                                            class="list-group-item list-group-item-action assignee-option py-2"
                                            @click.prevent="addAssignee(u)">
                                            <img :src="u.avatar_url || avatar(u.email, 24)"
                                                class="rounded-circle assignee-option__avatar" width="22" height="22"
                                                :alt="u.name">
                                            <span class="assignee-option__name">{{ u.name }}</span>
                                        </a>
                                        <span v-if="!boardMembers.length" class="list-group-item small text-muted">
                                            Không có thành viên.
                                        </span>
                                        <span v-else-if="!assignableMembers.length" class="list-group-item small text-muted">
                                            Mọi thành viên đã được giao.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nhãn -->
                        <h6 class="sect"><i class="fas fa-tags"></i>Nhãn</h6>
                        <div class="entity-list label-list mb-4">
                            <span v-for="l in (task.labels || [])" :key="l.id" class="label-chip"
                                :style="{ backgroundColor: l.color }" :title="l.name || 'Nhãn'">
                                <span class="label-chip__text">{{ l.name || 'Nhãn' }}</span>
                                <button v-if="canEdit" type="button" class="chip-x"
                                    @click="toggleLabel(l)" title="Gỡ nhãn"
                                    :aria-label="`Gỡ nhãn ${l.name || 'Nhãn'}`">&times;</button>
                            </span>

                            <span v-if="!task.labels || !task.labels.length" class="entity-empty text-muted small">
                                Chưa gắn nhãn.
                            </span>

                            <div v-if="canEdit" ref="labelWrap" class="entity-picker label-picker">
                                <button type="button" class="btn btn-sm btn-outline-secondary label-add-btn"
                                    @click="toggleLabelPanel">
                                    <i class="fas fa-plus mr-1"></i>Nhãn
                                </button>

                                <div v-if="showLabelPanel" class="popover-card label-popover desktop-picker-panel p-2">
                                    <div class="small text-muted mb-1">Chọn nhãn</div>
                                    <div class="label-option-list mb-2">
                                        <div v-for="l in labels" :key="l.id" class="label-row-wrap">
                                            <button type="button" class="label-row" :class="{ active: hasLabel(l.id) }"
                                                :style="{ backgroundColor: l.color }" @click="toggleLabel(l)">
                                                <span class="label-row__name">{{ l.name || 'Nhãn' }}</span>
                                                <i v-if="hasLabel(l.id)" class="fas fa-check label-row__check"></i>
                                            </button>
                                            <button type="button" class="label-del" title="Xoá nhãn khỏi bảng"
                                                :aria-label="`Xoá nhãn ${l.name || 'Nhãn'} khỏi bảng`"
                                                @click="deleteLabel(l)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                        <span v-if="!labels.length" class="text-muted small">Chưa có nhãn nào.</span>
                                    </div>

                                    <div class="small text-muted mb-1">Tạo nhãn mới</div>
                                    <input type="text" class="form-control form-control-sm mb-2"
                                        v-model="newLabelName" placeholder="Tên (tuỳ chọn)" maxlength="255">

                                    <div class="label-color-list mb-2">
                                        <button v-for="c in LABEL_COLORS" :key="c" type="button" class="color-dot"
                                            :class="{ sel: newLabelColor === c }" :style="{ backgroundColor: c }"
                                            @click="newLabelColor = c" :aria-label="`Chọn màu ${c}`"></button>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-success btn-block"
                                        @click="createLabel">Tạo &amp; gắn</button>
                                </div>
                            </div>
                        </div>

                        <!-- Mô tả -->
                        <h6 class="sect"><i class="fas fa-align-left"></i>Mô tả</h6>
                        <div class="mb-4">
                            <MarkdownEditor v-if="canEdit" v-model="description" :min-rows="4" :task-id="taskId"
                                placeholder="Thêm mô tả chi tiết hơn... (hỗ trợ Markdown)" />
                            <div v-else-if="description" class="md-content" v-html="renderMarkdown(description)"></div>
                            <div v-else class="text-muted small">Chưa có mô tả.</div>
                        </div>

                        <!-- Checklist -->
                        <h6 class="sect">
                            <i class="fas fa-tasks"></i>Checklist
                            <span v-if="checklistTotal" class="text-muted small ml-1 font-weight-normal">
                                {{ checklistDone }}/{{ checklistTotal }}
                            </span>
                        </h6>
                        <div class="mb-4">
                            <div v-if="checklistTotal" class="checklist-progress mb-2">
                                <div class="checklist-progress__bar" :style="{ width: checklistPct + '%' }"></div>
                            </div>
                            <div v-for="item in task.checklists" :key="item.id" class="checklist-item">
                                <Checkbox bare :model-value="item.is_done" :disabled="!canEdit"
                                    @update:model-value="canEdit && toggleChecklist(item)" />
                                <span :class="{ 'done': item.is_done }">{{ item.title }}</span>
                                <button v-if="canEdit" class="item-x" @click="deleteChecklist(item)" title="Xoá">&times;</button>
                            </div>
                            <div v-if="canEdit" class="input-group input-group-sm mt-2">
                                <input type="text" class="form-control" v-model="newChecklistItem"
                                    placeholder="Thêm mục mới..." maxlength="255" @keyup.enter="addChecklist">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn--icon-only" @click="addChecklist">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bình luận -->
                        <TaskComments :task-id="taskId" :board-id="boardId"
                            :comments="task.comments || []" @updated="() => fetchTask(false)" />
                    </div>
                </div>

                <!-- Cột phải -->
                <div class="col-lg-4">
                    <aside class="task-side">
                        <div class="panel mb-3">
                            <h6 class="side-title">Thuộc tính</h6>
                            <div class="form-group">
                                <label class="sect-label">Trạng thái</label>
                                <SelectInput v-model="statusId" :options="statusOptions" class="form-control-sm"
                                    :disabled="!canEdit" />
                            </div>
                            <div class="form-group">
                                <label class="sect-label">Độ ưu tiên</label>
                                <SelectInput v-model="priority" :options="PRIORITIES" class="form-control-sm"
                                    :disabled="!canEdit" />
                            </div>
                            <div class="form-group mb-3">
                                <label class="sect-label">Ngày hết hạn</label>
                                <TextInput type="date" v-model="dueDate" class="form-control-sm" group-class="mb-0"
                                    :disabled="!canEdit" />
                            </div>
                            <div class="form-group mb-0">
                                <label class="sect-label">Liên kết công việc</label>
                                <div class="task-link">
                                    <input class="task-link__input" type="text" :value="taskUrl" readonly
                                        @focus="$event.target.select()">
                                    <button type="button" class="task-link__btn"
                                        :title="linkCopied ? 'Đã sao chép' : 'Sao chép liên kết'"
                                        :aria-label="linkCopied ? 'Đã sao chép liên kết' : 'Sao chép liên kết'"
                                        @click="copyLink">
                                        <i :class="linkCopied ? 'fas fa-check' : 'fas fa-copy'"></i>
                                    </button>
                                    <Link :href="taskUrl" class="task-link__btn" title="Xem chi tiết công việc"
                                        aria-label="Xem chi tiết công việc">
                                        <i class="fas fa-external-link-alt"></i>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="canEdit" class="panel mb-3">
                            <Btn type="button" variant="black" icon="fas fa-save" class="btn-block"
                                :disabled="saving" @click="saveTask">
                                {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                            </Btn>
                            <Btn v-if="canManage" type="button" variant="red" outline icon="fas fa-trash-alt"
                                class="btn-block" @click="deleteTask">Xoá công việc</Btn>
                        </div>

                        <div class="panel">
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
                    </aside>
                </div>
            </div>
        </div>

        <!-- Bottom sheet chỉ hiển thị trên mobile; desktop vẫn dùng popover tại vị trí nút. -->
        <Teleport to="body">
            <Transition name="mobile-sheet">
                <div v-if="showAssigneePicker" class="mobile-sheet-layer"
                    @click.self="closeAssigneePicker">
                    <section class="mobile-bottom-sheet" role="dialog" aria-modal="true"
                        aria-labelledby="assignee-sheet-title" @click.stop>
                        <div class="mobile-sheet-handle" aria-hidden="true"></div>

                        <header class="mobile-sheet-header">
                            <div>
                                <h5 id="assignee-sheet-title" class="mobile-sheet-title">Người phụ trách</h5>
                                <p class="mobile-sheet-subtitle">Chọn hoặc gỡ người phụ trách công việc</p>
                            </div>
                            <button type="button" class="mobile-sheet-close" aria-label="Đóng"
                                @click="closeAssigneePicker">
                                <i class="fas fa-times"></i>
                            </button>
                        </header>

                        <div class="mobile-sheet-body">
                            <div v-if="task?.assignees?.length" class="mobile-sheet-section">
                                <div class="mobile-sheet-section__title">Đang phụ trách</div>
                                <div class="mobile-person-list">
                                    <button v-for="a in task.assignees" :key="`selected-${a.id}`" type="button"
                                        class="mobile-person-row is-selected"
                                        :disabled="!canManage"
                                        @click="canManage && removeAssignee(a)">
                                        <img :src="a.avatar_url || avatar(a.email, 40)" class="rounded-circle"
                                            width="40" height="40" :alt="a.name">
                                        <span class="mobile-person-row__content">
                                            <strong>{{ a.name }}</strong>
                                            <small v-if="a.email">{{ a.email }}</small>
                                        </span>
                                        <i v-if="canManage" class="fas fa-times mobile-person-row__action"></i>
                                        <i v-else class="fas fa-check mobile-person-row__action"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mobile-sheet-section mb-0">
                                <div class="mobile-sheet-section__title">Thêm người phụ trách</div>
                                <div v-if="assignableMembers.length" class="mobile-person-list">
                                    <button v-for="u in assignableMembers" :key="`available-${u.id}`" type="button"
                                        class="mobile-person-row" @click="addAssignee(u)">
                                        <img :src="u.avatar_url || avatar(u.email, 40)" class="rounded-circle"
                                            width="40" height="40" :alt="u.name">
                                        <span class="mobile-person-row__content">
                                            <strong>{{ u.name }}</strong>
                                            <small v-if="u.email">{{ u.email }}</small>
                                        </span>
                                        <i class="fas fa-plus mobile-person-row__action"></i>
                                    </button>
                                </div>
                                <div v-else class="mobile-sheet-empty">
                                    {{ !boardMembers.length
                                        ? 'Không có thành viên trong bảng.'
                                        : 'Mọi thành viên đã được giao.' }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </Transition>

            <Transition name="mobile-sheet">
                <div v-if="showLabelPanel" class="mobile-sheet-layer"
                    @click.self="closeLabelPanel">
                    <section class="mobile-bottom-sheet" role="dialog" aria-modal="true"
                        aria-labelledby="label-sheet-title" @click.stop>
                        <div class="mobile-sheet-handle" aria-hidden="true"></div>

                        <header class="mobile-sheet-header">
                            <div>
                                <h5 id="label-sheet-title" class="mobile-sheet-title">Nhãn</h5>
                                <p class="mobile-sheet-subtitle">Chọn, tạo hoặc xoá nhãn của công việc</p>
                            </div>
                            <button type="button" class="mobile-sheet-close" aria-label="Đóng"
                                @click="closeLabelPanel">
                                <i class="fas fa-times"></i>
                            </button>
                        </header>

                        <div class="mobile-sheet-body">
                            <div class="mobile-sheet-section">
                                <div class="mobile-sheet-section__title">Chọn nhãn</div>
                                <div v-if="labels.length" class="mobile-label-list">
                                    <div v-for="l in labels" :key="`mobile-label-${l.id}`"
                                        class="mobile-label-row">
                                        <button type="button" class="mobile-label-select"
                                            :class="{ active: hasLabel(l.id) }"
                                            @click="toggleLabel(l)">
                                            <span class="mobile-label-color" :style="{ backgroundColor: l.color }"></span>
                                            <span class="mobile-label-name">{{ l.name || 'Nhãn' }}</span>
                                            <i v-if="hasLabel(l.id)" class="fas fa-check mobile-label-check"></i>
                                        </button>
                                        <button type="button" class="mobile-label-delete"
                                            :aria-label="`Xoá nhãn ${l.name || 'Nhãn'} khỏi bảng`"
                                            @click="deleteLabel(l)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="mobile-sheet-empty">Chưa có nhãn nào.</div>
                            </div>

                            <div class="mobile-sheet-section mb-0">
                                <div class="mobile-sheet-section__title">Tạo nhãn mới</div>
                                <input type="text" class="form-control mb-3" v-model="newLabelName"
                                    placeholder="Tên nhãn (tuỳ chọn)" maxlength="255">

                                <div class="mobile-label-colors mb-3">
                                    <button v-for="c in LABEL_COLORS" :key="`mobile-${c}`" type="button"
                                        class="mobile-color-dot" :class="{ sel: newLabelColor === c }"
                                        :style="{ backgroundColor: c }" @click="newLabelColor = c"
                                        :aria-label="`Chọn màu ${c}`"></button>
                                </div>

                                <button type="button" class="btn btn-success btn-block mobile-create-label"
                                    @click="createLabel">
                                    <i class="fas fa-plus mr-1"></i>Tạo và gắn nhãn
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.task-edit {
    max-width: 1160px;
    margin: 0 auto;
}

/* ---------------- Header ---------------- */
.te-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--app-border);
}

.te-header__title {
    min-width: 0;
    flex: 1;
}

.task-code {
    flex-shrink: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: var(--app-accent, #663300);
    color: #fff;
    padding: 4px 12px;
    border-radius: 8px;
}

/* ---------------- Panel (thẻ nội dung) ---------------- */
.panel {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 2px 10px rgba(9, 30, 66, 0.05);
}

/* Sidebar dính khi cuộn */
.task-side {
    position: sticky;
    top: 16px;
}

.side-title,
.sect {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--app-accent, #663300);
}

.side-title {
    margin: 0 0 14px;
}

.sect {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
}

.sect-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--app-text-muted);
    margin-bottom: 6px;
}

/* ---------------- Permalink công việc ---------------- */
.task-link {
    display: flex;
    align-items: stretch;
    gap: 6px;
}

.task-link__input {
    flex: 1;
    min-width: 0;
    font-size: 0.78rem;
    padding: 4px 8px;
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 6px;
    background: rgba(127, 127, 127, 0.08);
    color: var(--app-text-muted);
}

.task-link__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 38px;
    aspect-ratio: 1 / 1;
    border: 1px solid var(--app-border, #e4e6ea);
    background: transparent;
    color: var(--app-accent, #663300);
    border-radius: 6px;
    padding: 0;
    cursor: pointer;
    text-decoration: none;
}

.task-link__btn:hover {
    background: var(--app-accent, #663300);
    color: #fff;
}

/* ---------------- Người phụ trách / Nhãn ---------------- */
.entity-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}

.entity-picker {
    position: relative;
    display: inline-block;
    flex: 0 0 auto;
}

.entity-empty {
    line-height: 30px;
}

.assignee-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    min-width: 0;
    background: rgba(127, 127, 127, 0.1);
    border-radius: 20px;
    padding: 3px 10px 3px 3px;
    font-size: 0.82rem;
    color: var(--app-text);
}

.assignee-pill__avatar {
    flex: 0 0 auto;
    object-fit: cover;
}

.assignee-pill__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assignee-option {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 8px;
}

.assignee-option__avatar {
    flex: 0 0 auto;
    object-fit: cover;
}

.assignee-option__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pill-x,
.chip-x,
.item-x {
    border: 0;
    background: transparent;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.7;
}

.pill-x { color: var(--app-text-muted); }
.chip-x { color: #fff; margin-left: 2px; }
.item-x { color: var(--app-text-muted); font-size: 1.05rem; }
.pill-x:hover, .chip-x:hover, .item-x:hover { opacity: 1; }

.btn-round {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px dashed var(--app-border);
    background: transparent;
    color: var(--app-text-muted);
    cursor: pointer;
    transition: border-color 0.15s ease, color 0.15s ease;
}

.btn-round:hover {
    border-color: var(--app-accent);
    color: var(--app-accent);
}

/* Popover chung (chọn người / nhãn) */
.popover-card {
    position: absolute;
    z-index: 30;
    top: calc(100% + 6px);
    left: 0;
    max-width: calc(100vw - 32px);
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

.assignee-popover {
    min-width: 210px;
}

.label-popover {
    min-width: 240px;
}

/* ---------------- Nhãn ---------------- */
.label-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    max-width: 100%;
    min-width: 0;
    height: 24px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
}

.label-chip__text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.label-option-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 180px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.label-color-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 0;
    border-radius: 6px;
    padding: 5px 10px;
    color: #fff;
    font-size: 0.78rem;
    font-weight: 600;
    opacity: 0.85;
    cursor: pointer;
}

.label-row.active {
    opacity: 1;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25) inset;
}

.label-row-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}

.label-row-wrap .label-row {
    flex: 1;
    min-width: 0;
}

.label-row__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: left;
}

.label-row__check {
    flex: 0 0 auto;
    margin-left: 8px;
}

.label-del {
    flex-shrink: 0;
    border: 0;
    background: transparent;
    color: var(--app-text-muted);
    cursor: pointer;
    padding: 4px 6px;
    font-size: 0.78rem;
    line-height: 1;
    border-radius: 6px;
}

.label-del:hover {
    color: #fff;
    background: #e5484d;
}

.color-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #ccc;
    cursor: pointer;
    padding: 0;
}

.color-dot.sel { box-shadow: 0 0 0 2px #333; }

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
    padding: 5px 0;
}

.checklist-item span {
    flex: 1;
    color: var(--app-text);
}

.checklist-item span.done {
    color: var(--app-text-muted);
    text-decoration: line-through;
}

/* ---------------- Bình luận ---------------- */
.mention-pop {
    position: absolute;
    z-index: 30;
    top: 46px;
    left: 10px;
    min-width: 220px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

.comment {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-top: 1px solid var(--app-border);
}

.comment:first-child { border-top: 0; }

.comment__avatar { flex-shrink: 0; }

.comment__body { flex: 1; min-width: 0; }

.comment__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 3px;
}

.comment__content {
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
    word-break: break-word;
}

/* Kiểu nội dung markdown đã render (dùng chung cho bình luận) */
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

/* ---------------- Lịch sử ---------------- */
.history-scroll {
    max-height: 340px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    gap: 8px;
    padding: 7px 0;
    font-size: 0.8rem;
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

[data-theme="dark"] .md-content :deep(a) { color: var(--app-accent-2); }
[data-theme="dark"] .md-content :deep(blockquote) {
    background: rgba(165, 118, 63, 0.12);
    border-left-color: var(--app-accent-2);
}

/* ---------------- Bottom sheet mobile ---------------- */
.mobile-sheet-layer {
    display: none;
}

.mobile-sheet-enter-active,
.mobile-sheet-leave-active {
    transition: opacity 0.2s ease;
}

.mobile-sheet-enter-active .mobile-bottom-sheet,
.mobile-sheet-leave-active .mobile-bottom-sheet {
    transition: transform 0.24s ease;
}

.mobile-sheet-enter-from,
.mobile-sheet-leave-to {
    opacity: 0;
}

.mobile-sheet-enter-from .mobile-bottom-sheet,
.mobile-sheet-leave-to .mobile-bottom-sheet {
    transform: translateY(100%);
}

@media (max-width: 575.98px) {
    .te-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }

    .te-header__title {
        grid-column: 2;
        grid-row: 1;
    }

    .te-header__title h4 {
        font-size: 1.05rem;
        line-height: 1.35;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }

    .te-header .task-code {
        grid-column: 2;
        grid-row: 2;
        justify-self: start;
        margin-left: 0;
    }

    .te-body {
        margin-right: -6px;
        margin-left: -6px;
    }

    .te-body > [class*="col-"] {
        padding-right: 6px;
        padding-left: 6px;
    }

    .panel {
        padding: 14px;
        border-radius: 12px;
    }

    .task-side {
        position: static;
        margin-top: 12px;
    }

    /* Hai vùng này giữ kiểu chip/pill như desktop nhưng tự xuống dòng trên mobile. */
    .entity-list {
        align-items: center;
        width: 100%;
        gap: 8px 6px;
    }

    .assignee-pill,
    .label-chip {
        max-width: 100%;
    }

    .label-chip {
        height: auto;
        min-height: 28px;
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .assignee-pill__name {
        max-width: calc(100vw - 120px);
    }

    .label-chip__text {
        max-width: calc(100vw - 96px);
    }

    /* Trên mobile chỉ giữ nút/chip tại trang; danh sách chọn được đưa vào bottom sheet. */
    .entity-picker {
        display: inline-block;
        flex: 0 0 auto;
        width: auto;
    }

    .label-add-btn {
        width: auto;
    }

    .desktop-picker-panel {
        display: none !important;
    }

    .assignee-option,
    .label-row-wrap {
        min-width: 0;
    }

    .pill-x,
    .chip-x {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 24px;
        height: 24px;
        margin-left: 0;
    }

    .color-dot {
        width: 26px;
        height: 26px;
    }

    .mobile-sheet-layer {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding: 0;
        background: rgba(0, 0, 0, 0.48);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }

    .mobile-bottom-sheet {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-height: min(86dvh, 760px);
        overflow: hidden;
        background: var(--app-surface, #fff);
        color: var(--app-text, #212529);
        border: 1px solid var(--app-border, #e4e6ea);
        border-bottom: 0;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.24);
        padding-bottom: env(safe-area-inset-bottom, 0);
    }

    .mobile-sheet-handle {
        flex: 0 0 auto;
        width: 42px;
        height: 5px;
        margin: 9px auto 2px;
        border-radius: 999px;
        background: var(--app-border, #d6d8dc);
    }

    .mobile-sheet-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 16px 14px;
        border-bottom: 1px solid var(--app-border, #e4e6ea);
    }

    .mobile-sheet-title {
        margin: 0;
        color: var(--app-text, #212529);
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .mobile-sheet-subtitle {
        margin: 3px 0 0;
        color: var(--app-text-muted, #6c757d);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .mobile-sheet-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 50%;
        background: rgba(127, 127, 127, 0.12);
        color: var(--app-text-muted, #6c757d);
        font-size: 1rem;
        cursor: pointer;
    }

    .mobile-sheet-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 16px;
    }

    .mobile-sheet-section {
        margin-bottom: 20px;
    }

    .mobile-sheet-section__title {
        margin-bottom: 8px;
        color: var(--app-text-muted, #6c757d);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.45px;
        text-transform: uppercase;
    }

    .mobile-person-list,
    .mobile-label-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .mobile-person-row {
        display: flex;
        align-items: center;
        width: 100%;
        min-width: 0;
        gap: 11px;
        padding: 10px 12px;
        border: 1px solid var(--app-border, #e4e6ea);
        border-radius: 12px;
        background: transparent;
        color: var(--app-text, #212529);
        text-align: left;
        cursor: pointer;
    }

    .mobile-person-row.is-selected {
        background: rgba(127, 127, 127, 0.08);
    }

    .mobile-person-row:disabled {
        cursor: default;
        opacity: 1;
    }

    .mobile-person-row img {
        flex: 0 0 auto;
        object-fit: cover;
    }

    .mobile-person-row__content {
        display: flex;
        flex: 1 1 auto;
        min-width: 0;
        flex-direction: column;
    }

    .mobile-person-row__content strong,
    .mobile-person-row__content small {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mobile-person-row__content strong {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .mobile-person-row__content small {
        margin-top: 1px;
        color: var(--app-text-muted, #6c757d);
        font-size: 0.74rem;
    }

    .mobile-person-row__action {
        flex: 0 0 auto;
        color: var(--app-accent, #663300);
        font-size: 0.82rem;
    }

    .mobile-sheet-empty {
        padding: 18px 14px;
        border: 1px dashed var(--app-border, #e4e6ea);
        border-radius: 12px;
        color: var(--app-text-muted, #6c757d);
        font-size: 0.82rem;
        text-align: center;
    }

    .mobile-label-row {
        display: flex;
        align-items: stretch;
        min-width: 0;
        gap: 8px;
    }

    .mobile-label-select {
        display: flex;
        align-items: center;
        flex: 1 1 auto;
        min-width: 0;
        gap: 10px;
        padding: 11px 12px;
        border: 1px solid var(--app-border, #e4e6ea);
        border-radius: 12px;
        background: transparent;
        color: var(--app-text, #212529);
        text-align: left;
        cursor: pointer;
    }

    .mobile-label-select.active {
        border-color: var(--app-accent, #663300);
        box-shadow: 0 0 0 1px var(--app-accent, #663300) inset;
    }

    .mobile-label-color {
        flex: 0 0 auto;
        width: 16px;
        height: 16px;
        border-radius: 5px;
    }

    .mobile-label-name {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.86rem;
        font-weight: 600;
    }

    .mobile-label-check {
        flex: 0 0 auto;
        color: var(--app-accent, #663300);
    }

    .mobile-label-delete {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 44px;
        width: 44px;
        border: 1px solid var(--app-border, #e4e6ea);
        border-radius: 12px;
        background: transparent;
        color: #e5484d;
        cursor: pointer;
    }

    .mobile-label-colors {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 9px;
    }

    .mobile-color-dot {
        width: 100%;
        aspect-ratio: 1 / 1;
        min-width: 0;
        border: 3px solid var(--app-surface, #fff);
        border-radius: 50%;
        box-shadow: 0 0 0 1px var(--app-border, #d6d8dc);
        cursor: pointer;
    }

    .mobile-color-dot.sel {
        box-shadow: 0 0 0 3px var(--app-text, #212529);
    }

    .mobile-create-label {
        min-height: 44px;
        border-radius: 10px;
        font-weight: 600;
    }

    .mention-pop {
        right: 0;
        min-width: 0;
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
