<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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

const PRIORITIES = [
    { value: 'low', label: 'Thấp' },
    { value: 'normal', label: 'Bình thường' },
    { value: 'high', label: 'Cao' },
    { value: 'urgent', label: 'Khẩn cấp' },
];

const statusOptions = computed(() => props.statuses.map((s) => ({ value: s.id, label: s.name })));

// ---- Permalink công việc (https://{miền}/b-{board_code}/tasks/{task_code}) ----
// Ziggy trả URL tuyệt đối (kèm host) nên copy ra là link đầy đủ dùng được.
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
    try {
        await axios.post(route('tasks.assignees.store', props.taskId), { user_id: user.id });
        showAssigneePicker.value = false;
        await fetchTask(false);
    } catch (e) { alert(e.response?.data?.message || 'Không thể thêm người phụ trách.'); }
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
    if (showLabelPanel.value && labelWrap.value && !labelWrap.value.contains(e.target)) {
        showLabelPanel.value = false;
    }
    if (showAssigneePicker.value && assigneeWrap.value && !assigneeWrap.value.contains(e.target)) {
        showAssigneePicker.value = false;
    }
};
// mousedown (không phải click) để không xung đột với nút bật/tắt popover.
onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));
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
                    <div class="text-muted small">{{ boardName }}</div>
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
                        <div class="d-flex align-items-center flex-wrap mb-4" style="gap:8px;">
                            <span v-for="a in task.assignees" :key="a.id" class="assignee-pill">
                                <img :src="a.avatar_url || avatar(a.email)" class="rounded-circle" width="24" height="24" :title="a.name">
                                <span>{{ a.name }}</span>
                                <button v-if="canManage" class="pill-x" @click="removeAssignee(a)" title="Gỡ">&times;</button>
                            </span>
                            <span v-if="!task.assignees || !task.assignees.length" class="text-muted small">Chưa có ai.</span>
                            <div v-if="canManage" ref="assigneeWrap" class="position-relative d-inline-block">
                                <button class="btn-round" @click="showAssigneePicker = !showAssigneePicker" title="Thêm người">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <div v-if="showAssigneePicker" class="popover-card" style="min-width:210px;">
                                    <div class="list-group list-group-flush">
                                        <a v-for="u in boardMembers" :key="u.id" href="#"
                                            class="list-group-item list-group-item-action py-2"
                                            @click.prevent="addAssignee(u)">
                                            <img :src="u.avatar_url || avatar(u.email, 24)" class="rounded-circle mr-2" width="22" height="22">{{ u.name }}
                                        </a>
                                        <span v-if="!boardMembers.length" class="list-group-item small text-muted">Không có thành viên.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nhãn -->
                        <h6 class="sect"><i class="fas fa-tags"></i>Nhãn</h6>
                        <div class="d-flex align-items-center flex-wrap mb-4" style="gap:6px;">
                            <span v-for="l in (task.labels || [])" :key="l.id" class="label-chip"
                                :style="{ backgroundColor: l.color }">
                                {{ l.name || 'Nhãn' }}
                                <button v-if="canEdit" class="chip-x" @click="toggleLabel(l)" title="Gỡ nhãn">&times;</button>
                            </span>
                            <span v-if="!task.labels || !task.labels.length" class="text-muted small">Chưa gắn nhãn.</span>

                            <div v-if="canEdit" ref="labelWrap" class="position-relative d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary" @click="showLabelPanel = !showLabelPanel">
                                    <i class="fas fa-plus mr-1"></i>Nhãn
                                </button>
                                <div v-if="showLabelPanel" class="popover-card p-2" style="min-width:240px;">
                                    <div class="small text-muted mb-1">Chọn nhãn</div>
                                    <div class="d-flex flex-column mb-2" style="gap:4px; max-height:180px; overflow-y:auto;">
                                        <div v-for="l in labels" :key="l.id" class="label-row-wrap">
                                            <button type="button" class="label-row" :class="{ active: hasLabel(l.id) }"
                                                :style="{ backgroundColor: l.color }" @click="toggleLabel(l)">
                                                <span>{{ l.name || 'Nhãn' }}</span>
                                                <i v-if="hasLabel(l.id)" class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="label-del" title="Xoá nhãn khỏi bảng"
                                                @click="deleteLabel(l)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                        <span v-if="!labels.length" class="text-muted small">Chưa có nhãn nào.</span>
                                    </div>
                                    <div class="small text-muted mb-1">Tạo nhãn mới</div>
                                    <input type="text" class="form-control form-control-sm mb-2" v-model="newLabelName" placeholder="Tên (tuỳ chọn)">
                                    <div class="d-flex align-items-center mb-2" style="gap:5px;">
                                        <button v-for="c in LABEL_COLORS" :key="c" type="button" class="color-dot"
                                            :class="{ sel: newLabelColor === c }" :style="{ backgroundColor: c }"
                                            @click="newLabelColor = c"></button>
                                    </div>
                                    <button class="btn btn-sm btn-success btn-block" @click="createLabel">Tạo &amp; gắn</button>
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
                                    placeholder="Thêm mục mới..." @keyup.enter="addChecklist">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" @click="addChecklist">
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
                                    <button type="button" class="task-link__btn" :title="linkCopied ? 'Đã sao chép' : 'Sao chép liên kết'"
                                        @click="copyLink">
                                        <i :class="linkCopied ? 'fas fa-check' : 'fas fa-copy'"></i>
                                    </button>
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
    flex-shrink: 0;
    border: 1px solid var(--app-border, #e4e6ea);
    background: transparent;
    color: var(--app-accent, #663300);
    border-radius: 6px;
    padding: 0 10px;
    cursor: pointer;
}

.task-link__btn:hover {
    background: var(--app-accent, #663300);
    color: #fff;
}

/* ---------------- Người phụ trách ---------------- */
.assignee-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(127, 127, 127, 0.1);
    border-radius: 20px;
    padding: 3px 10px 3px 3px;
    font-size: 0.82rem;
    color: var(--app-text);
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
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

/* ---------------- Nhãn ---------------- */
.label-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 24px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
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
</style>
