<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KanbanColumn from '@/Components/KanbanColumn.vue';
import BoardCalendar from '@/Components/BoardCalendar.vue';
import BoardAnalytics from '@/Components/BoardAnalytics.vue';
import TaskModal from '@/Components/TaskModal.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import Btn from '@/Components/Btn.vue';

const props = defineProps({
    board: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

// State cục bộ (reactive) cho columns/tasks
const columns = reactive(props.board.columns.map((c) => ({ ...c, tasks: [...c.tasks] })));

// ---- Thêm cột (hiển thị form trong modal) ----
const showAddColumn = ref(false);
const newColumnName = ref('');
const openAddColumn = () => { newColumnName.value = ''; showAddColumn.value = true; };
const saveColumn = async () => {
    const name = newColumnName.value.trim();
    if (!name) return;
    try {
        const { data } = await axios.post(route('columns.store', props.board.id), { name });
        columns.push({ id: data.column.id, name: data.column.name, position: data.column.position, tasks: [] });
        newColumnName.value = '';
        showAddColumn.value = false;
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể tạo cột.');
    }
};

// ---- Sửa / xoá cột ----
const renameColumn = async (col) => {
    const name = prompt('Tên cột mới:', col.name);
    if (!name || name.trim() === col.name) return;
    try {
        await axios.put(route('columns.update', [props.board.id, col.id]), { name: name.trim() });
        col.name = name.trim();
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể đổi tên cột.');
    }
};
const deleteColumn = async (col) => {
    if (!confirm(`Xoá cột "${col.name}"?`)) return;
    try {
        await axios.delete(route('columns.destroy', [props.board.id, col.id]));
        const idx = columns.findIndex((c) => c.id === col.id);
        if (idx !== -1) columns.splice(idx, 1);
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể xoá cột.');
    }
};

// ---- Thêm công việc ----
const saveTask = async (col, title) => {
    const t = (title || '').trim();
    if (!t) return;
    try {
        const { data } = await axios.post(route('tasks.store', col.id), { title: t });
        col.tasks.push({
            id: data.task.id, title: data.task.title, column_id: col.id,
            position: data.task.position, due_date: data.task.due_date,
            formatted_due_date: data.task.formatted_due_date, assignees: data.task.assignees || [],
            priority: data.task.priority || 'normal', status: data.task.status || null,
            has_description: false,
            comments_count: 0, attachments_count: 0, checklist_total: 0, checklist_done: 0,
        });
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể thêm công việc.');
    }
};

// ---- Kéo thả công việc ----
let positionSeq = 0;

// Khôi phục thứ tự task của mọi cột về trạng thái đã chụp (khi lưu vị trí thất bại).
const restoreOrder = (snapshot) => {
    const byId = new Map();
    for (const c of columns) for (const t of c.tasks) byId.set(t.id, t);
    for (const { col, ids } of snapshot) {
        col.tasks = ids.map((id) => byId.get(id)).filter(Boolean);
        col.tasks.forEach((t) => { t.column_id = col.id; });
    }
};

const onTaskChange = async (col, evt) => {
    const moved = evt.added || evt.moved;
    if (!moved) return;
    const taskId = moved.element.id;
    // Chụp thứ tự hiện tại của MỌI cột trước khi gọi API (kéo có thể vắt qua 2 cột).
    const snapshot = columns.map((c) => ({ col: c, ids: c.tasks.map((t) => t.id) }));
    const seq = ++positionSeq;
    try {
        await axios.post(route('tasks.updatePosition'), {
            task_id: taskId,
            new_column_id: col.id,
            order: col.tasks.map((t) => t.id),
        });
    } catch (e) {
        // Chỉ hoàn tác nếu chưa có thao tác kéo-thả mới hơn (tránh giật ngược trạng thái).
        if (seq === positionSeq) {
            restoreOrder(snapshot);
        }
        alert(e.response?.data?.message || 'Không thể cập nhật vị trí.');
    }
};

// ---- Chế độ xem: bảng Kanban | Lịch ----
const viewMode = ref('board');
const allTasks = computed(() => columns.flatMap((c) => c.tasks));

const fmtDMY = (isoDate) => {
    if (!isoDate) return null;
    const [y, m, d] = isoDate.split('-');
    return `${d}/${m}/${y}`;
};

// Đổi hạn từ lịch (kéo-thả). Cập nhật lạc quan rồi gọi API.
const rescheduleTask = async ({ task, dueDate }) => {
    const prevDate = task.due_date;
    const prevFmt = task.formatted_due_date;
    task.due_date = dueDate;
    task.formatted_due_date = fmtDMY(dueDate);
    try {
        await axios.put(route('tasks.update', task.id), { title: task.title, due_date: dueDate });
    } catch (e) {
        task.due_date = prevDate;
        task.formatted_due_date = prevFmt;
        alert(e.response?.data?.message || 'Không thể đổi hạn công việc.');
    }
};

// ---- Tìm kiếm & lọc (client-side) ----
const filters = reactive({ q: '', priority: '', assignee: '', label: '', due: '' });

const allAssignees = computed(() => {
    const map = new Map();
    for (const col of columns) {
        for (const t of col.tasks) {
            for (const a of t.assignees || []) if (!map.has(a.id)) map.set(a.id, a);
        }
    }
    return [...map.values()];
});
const boardLabels = computed(() => props.board.labels || []);

const dueStateOf = (task) => {
    if (!task.due_date) return 'none';
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const due = new Date(task.due_date); due.setHours(0, 0, 0, 0);
    const diff = Math.round((due - today) / 86400000);
    if (diff < 0) return 'overdue';
    if (diff <= 2) return 'soon';
    return 'future';
};

const hasActiveFilter = computed(() =>
    !!(filters.q || filters.priority || filters.assignee || filters.label || filters.due)
);

const taskMatches = (task) => {
    if (filters.q) {
        const q = filters.q.toLowerCase();
        const hay = `${task.title || ''} ${task.code || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
    }
    if (filters.priority && task.priority !== filters.priority) return false;
    if (filters.assignee && !(task.assignees || []).some((a) => a.id === Number(filters.assignee))) return false;
    if (filters.label && !(task.labels || []).some((l) => l.id === Number(filters.label))) return false;
    if (filters.due && dueStateOf(task) !== filters.due) return false;
    return true;
};

const clearFilters = () => { filters.q = ''; filters.priority = ''; filters.assignee = ''; filters.label = ''; filters.due = ''; };

// ---- Modal chi tiết (chỉ xem) ----
const modalTaskId = ref(null);
const openTask = (task) => { modalTaskId.value = task.id; };
const closeTask = () => { modalTaskId.value = null; };

// ---- Modal "Task của tôi" (chỉ trong bảng này) ----
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const showMyTasks = ref(false);
const MY_PRIORITY = {
    urgent: { label: 'Khẩn cấp', color: '#e5484d' },
    high: { label: 'Cao', color: '#f76808' },
    normal: { label: 'Bình thường', color: '#006adc' },
    low: { label: 'Thấp', color: '#18794e' },
};
const myTasks = computed(() => {
    const uid = currentUserId.value;
    if (!uid) return [];
    const out = [];
    for (const col of columns) {
        for (const t of col.tasks) {
            if ((t.assignees || []).some((a) => a.id === uid)) {
                out.push({ ...t, column_name: col.name });
            }
        }
    }
    return out;
});
const openMyTask = (task) => { showMyTasks.value = false; openTask(task); };

// ---- Nhật ký hoạt động ----
const showActivity = ref(false);
const activities = ref([]);
const loadingActivity = ref(false);
const openActivity = async () => {
    showActivity.value = true;
    loadingActivity.value = true;
    try {
        const { data } = await axios.get(route('boards.activity', props.board.id));
        activities.value = data.activities || [];
    } catch (e) {
        activities.value = [];
    } finally {
        loadingActivity.value = false;
    }
};
</script>

<template>
    <Head :title="`${board.name} - Kanban`" />
    <AuthenticatedLayout>
        <div class="board-header p-3 mb-2 border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="mb-0">{{ board.name }}</h3>
            <div class="d-flex align-items-center" style="gap:8px;">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn" :class="viewMode === 'board' ? 'btn-dark' : 'btn-outline-secondary'"
                        @click="viewMode = 'board'"><i class="fas fa-columns mr-1"></i> Bảng</button>
                    <button class="btn" :class="viewMode === 'calendar' ? 'btn-dark' : 'btn-outline-secondary'"
                        @click="viewMode = 'calendar'"><i class="far fa-calendar-alt mr-1"></i> Lịch</button>
                    <button class="btn" :class="viewMode === 'analytics' ? 'btn-dark' : 'btn-outline-secondary'"
                        @click="viewMode = 'analytics'"><i class="fas fa-chart-pie mr-1"></i> Phân tích</button>
                </div>
                <button class="btn btn-sm btn-outline-secondary" @click="openActivity">
                    <i class="fas fa-clock-rotate-left mr-1"></i> Hoạt động
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="showMyTasks = true">
                    <i class="fas fa-user-check mr-1"></i> Task của tôi
                    <span v-if="myTasks.length" class="badge badge-secondary ml-1">{{ myTasks.length }}</span>
                </button>
                <Btn :href="route('boards.settings', board.id)" :disabled="!canManage"
                    variant="secondary" outline icon="fas fa-users" class="btn-sm"
                    :title="canManage ? 'Quản lý thành viên' : 'Chỉ người quản lý mới được mời thành viên'">
                    Thành viên</Btn>
                <Btn v-if="canEdit && viewMode === 'board'" type="button" variant="success"
                    icon="fas fa-plus" class="btn-sm" @click="openAddColumn">Thêm cột</Btn>
            </div>
        </div>

        <!-- Thanh tìm kiếm & lọc (chỉ ở chế độ Bảng) -->
        <div v-if="viewMode === 'board'" class="filter-bar d-flex flex-wrap align-items-center px-3 mb-2" style="gap:8px;">
            <div class="input-group input-group-sm" style="width:220px;">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                <input type="text" class="form-control" v-model="filters.q" placeholder="Tìm tiêu đề / mã...">
            </div>
            <select class="form-control form-control-sm" style="width:auto;" v-model="filters.priority">
                <option value="">Tất cả</option>
                <option value="urgent">Khẩn cấp</option>
                <option value="high">Cao</option>
                <option value="normal">Bình thường</option>
                <option value="low">Thấp</option>
            </select>
            <select class="form-control form-control-sm" style="width:auto;" v-model="filters.assignee">
                <option value="">Mọi người</option>
                <option v-for="a in allAssignees" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <select v-if="boardLabels.length" class="form-control form-control-sm" style="width:auto;" v-model="filters.label">
                <option value="">Tất cả</option>
                <option v-for="l in boardLabels" :key="l.id" :value="l.id">{{ l.name || 'Nhãn' }}</option>
            </select>
            <select class="form-control form-control-sm" style="width:auto;" v-model="filters.due">
                <option value="">Tất cả</option>
                <option value="overdue">Quá hạn</option>
                <option value="soon">Sắp tới (≤2 ngày)</option>
                <option value="future">Còn hạn</option>
                <option value="none">Không có hạn</option>
            </select>
            <button v-if="hasActiveFilter" class="btn btn-sm btn-link text-danger" @click="clearFilters">
                <i class="fas fa-times mr-1"></i>Xoá lọc
            </button>
        </div>

        <div v-if="viewMode === 'board'" class="kanban-board" id="kanbanBoard">
            <KanbanColumn v-for="col in columns" :key="col.id" :col="col"
                :can-edit="canEdit" :can-manage="canManage" :match="taskMatches"
                @rename="() => renameColumn(col)"
                @delete="() => deleteColumn(col)"
                @task-change="(e) => onTaskChange(col, e)"
                @add-task="(title) => saveTask(col, title)"
                @open-task="openTask" />
        </div>

        <!-- Chế độ Lịch -->
        <div v-else-if="viewMode === 'calendar'" class="px-2">
            <BoardCalendar :tasks="allTasks" :can-edit="canEdit"
                @open="openTask" @reschedule="rescheduleTask" />
        </div>

        <!-- Chế độ Phân tích -->
        <BoardAnalytics v-else :key="`analytics-${board.id}`" :board-id="board.id" />

        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :can-edit="canEdit" :can-manage="canManage"
            :board-id="board.id" @close="closeTask" />

        <!-- Thêm cột mới -->
        <Modal v-if="showAddColumn" title="Thêm cột mới" max-width="420px" align="center" @close="showAddColumn = false">
            <form @submit.prevent="saveColumn">
                <div class="form-group">
                    <label class="small font-weight-bold">Tên cột</label>
                    <TextInput v-model="newColumnName" placeholder="Nhập tên cột..." autofocus group-class="mb-0" />
                </div>
                <div class="text-right">
                    <Btn type="button" variant="white" class="btn-sm mr-2" @click="showAddColumn = false">Huỷ</Btn>
                    <Btn variant="black" class="btn-sm px-3">Tạo cột</Btn>
                </div>
            </form>
        </Modal>

        <!-- Task của tôi trong bảng này -->
        <Modal v-if="showMyTasks" max-width="620px" align="center" @close="showMyTasks = false">
            <template #header>
                <h5 class="mb-0"><i class="fas fa-user-check mr-2"></i>Task của tôi trong bảng</h5>
            </template>
            <div v-if="!myTasks.length" class="text-center text-muted py-4">
                <i class="fas fa-mug-hot fa-2x mb-2 d-block"></i>
                Bạn chưa được giao công việc nào trong bảng này.
            </div>
            <div v-else class="my-task-list">
                <button v-for="t in myTasks" :key="t.id" type="button" class="my-task" @click="openMyTask(t)">
                    <div class="my-task__main">
                        <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                            <span class="my-code">{{ t.code }}</span>
                            <span v-for="l in t.labels" :key="l.id" class="my-label"
                                :style="{ backgroundColor: l.color }">{{ l.name }}</span>
                        </div>
                        <div class="my-title" :class="{ done: t.status?.is_completed }">{{ t.title }}</div>
                        <div class="text-muted small"><i class="fas fa-columns mr-1"></i>{{ t.column_name }}</div>
                    </div>
                    <div class="my-task__meta">
                        <span v-if="t.status" class="status-mini"
                            :style="{ color: t.status.color, borderColor: t.status.color }">{{ t.status.name }}</span>
                        <span v-if="MY_PRIORITY[t.priority]" class="prio-dot"
                            :style="{ backgroundColor: MY_PRIORITY[t.priority].color }"
                            :title="MY_PRIORITY[t.priority].label"></span>
                        <span v-if="t.formatted_due_date" class="my-due">
                            <i class="far fa-clock mr-1"></i>{{ t.formatted_due_date }}</span>
                    </div>
                </button>
            </div>
        </Modal>

        <!-- Nhật ký hoạt động -->
        <Modal v-if="showActivity" max-width="560px" align="center" header-class="bg-dark text-light" @close="showActivity = false">
            <template #header><h5 class="mb-0"><i class="fas fa-clock-rotate-left mr-2"></i>Hoạt động của bảng</h5></template>
            <div v-if="loadingActivity" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
            <div v-else>
                <div v-for="a in activities" :key="a.id" class="d-flex mb-3">
                    <img :src="a.user_avatar" class="rounded-circle mr-2" width="32" height="32" style="height:32px;">
                    <div class="flex-grow-1">
                        <div v-html="a.note" style="font-size:.85rem;"></div>
                        <div class="text-muted" style="font-size:.72rem;">{{ a.time_ago }}</div>
                    </div>
                </div>
                <div v-if="!activities.length" class="text-muted text-center py-4">Chưa có hoạt động nào.</div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.my-task-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.my-task {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    text-align: left;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
    transition: box-shadow 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.my-task:hover {
    box-shadow: 0 4px 12px rgba(9, 30, 66, 0.08);
    border-color: var(--app-accent);
    transform: translateY(-1px);
}

.my-task__main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.my-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.7rem;
    font-weight: 700;
    color: #7a869a;
}

.my-label {
    display: inline-flex;
    align-items: center;
    height: 16px;
    padding: 0 6px;
    border-radius: 5px;
    font-size: 0.64rem;
    font-weight: 600;
    color: #fff;
}

.my-title {
    font-weight: 600;
    color: var(--app-text);
    word-break: break-word;
}

.my-title.done {
    text-decoration: line-through;
    color: var(--app-text-muted);
}

.my-task__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.status-mini {
    font-size: 0.7rem;
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

.my-due {
    font-size: 0.75rem;
    color: var(--app-text-muted);
    white-space: nowrap;
}

.kanban-board {
    display: flex;
    /* Vượt quá bề ngang thì cột xuống dòng (không cuộn ngang) */
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 16px;
    max-width: 100%;
    padding-bottom: 10px;
}

@media (max-width: 575.98px) {
    .kanban-board {
        gap: 12px;
    }
}
</style>
