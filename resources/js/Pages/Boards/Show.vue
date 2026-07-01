<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KanbanColumn from '@/Components/KanbanColumn.vue';
import TaskModal from '@/Components/TaskModal.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    board: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

// State cục bộ (reactive) cho columns/tasks
const columns = reactive(props.board.columns.map((c) => ({ ...c, tasks: [...c.tasks] })));

// ---- Thêm cột ----
const addingColumn = ref(false);
const newColumnName = ref('');
const saveColumn = async () => {
    const name = newColumnName.value.trim();
    if (!name) return;
    try {
        const { data } = await axios.post(route('columns.store', props.board.id), { name });
        columns.push({ id: data.column.id, name: data.column.name, position: data.column.position, tasks: [] });
        newColumnName.value = '';
        addingColumn.value = false;
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
const onTaskChange = async (col, evt) => {
    const moved = evt.added || evt.moved;
    if (!moved) return;
    const taskId = moved.element.id;
    try {
        await axios.post(route('tasks.updatePosition'), {
            task_id: taskId,
            new_column_id: col.id,
            order: col.tasks.map((t) => t.id),
        });
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể cập nhật vị trí.');
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
</script>

<template>
    <Head :title="`${board.name} - Kanban`" />
    <AuthenticatedLayout>
        <div class="board-header p-3 mb-2 border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="mb-0">{{ board.name }}</h3>
            <Link :href="route('my-tasks.index')" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-user-check mr-1"></i>Task của tôi
            </Link>
        </div>

        <!-- Thanh tìm kiếm & lọc -->
        <div class="filter-bar d-flex flex-wrap align-items-center px-3 mb-2" style="gap:8px;">
            <div class="input-group input-group-sm" style="width:220px;">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                <input type="text" class="form-control" v-model="filters.q" placeholder="Tìm tiêu đề / mã...">
            </div>
            <select class="form-control form-control-sm" style="width:auto;" v-model="filters.priority">
                <option value="">Mọi ưu tiên</option>
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
                <option value="">Mọi nhãn</option>
                <option v-for="l in boardLabels" :key="l.id" :value="l.id">{{ l.name || 'Nhãn' }}</option>
            </select>
            <select class="form-control form-control-sm" style="width:auto;" v-model="filters.due">
                <option value="">Mọi hạn</option>
                <option value="overdue">Quá hạn</option>
                <option value="soon">Sắp tới (≤2 ngày)</option>
                <option value="future">Còn hạn</option>
                <option value="none">Không có hạn</option>
            </select>
            <button v-if="hasActiveFilter" class="btn btn-sm btn-link text-danger" @click="clearFilters">
                <i class="fas fa-times mr-1"></i>Xoá lọc
            </button>
        </div>

        <div class="kanban-board" id="kanbanBoard">
            <KanbanColumn v-for="col in columns" :key="col.id" :col="col"
                :can-edit="canEdit" :can-manage="canManage" :match="taskMatches"
                @rename="() => renameColumn(col)"
                @delete="() => deleteColumn(col)"
                @task-change="(e) => onTaskChange(col, e)"
                @add-task="(title) => saveTask(col, title)"
                @open-task="openTask" />

            <!-- Thêm cột -->
            <div v-if="canEdit" class="add-column">
                <template v-if="addingColumn">
                    <div class="p-2 bg-white rounded">
                        <TextInput v-model="newColumnName" placeholder="Nhập tên cột..." class="form-control-sm"
                            group-class="mb-2" @keyup.enter="saveColumn" autofocus />
                        <button class="btn btn-success btn-sm mr-1" @click="saveColumn">Lưu</button>
                        <button class="btn btn-secondary btn-sm" @click="addingColumn = false">Huỷ</button>
                    </div>
                </template>
                <div v-else class="add-column-placeholder text-dark p-2" @click="addingColumn = true">
                    <i class="fas fa-plus"></i> THÊM CỘT
                </div>
            </div>
        </div>

        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :can-edit="canEdit" :can-manage="canManage"
            :board-id="board.id" @close="closeTask" />
    </AuthenticatedLayout>
</template>

<style scoped>
.kanban-board {
    display: flex;
    /* Vượt quá bề ngang thì cột xuống dòng (không cuộn ngang) */
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 16px;
    max-width: 100%;
    padding-bottom: 10px;
}

/* Ô "Thêm cột" cùng bề rộng với một cột */
.add-column {
    flex: 0 0 auto;
    width: clamp(250px, 85vw, 290px);
}

.add-column-placeholder {
    background-color: #1d941d8d;
    border: 1px solid #d1d1d1;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    color: #201e1e;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
    height: 40px;
    font-weight: 500;
    font-size: small;
}

.add-column-placeholder:hover {
    background-color: #27ac278d;
    border-color: #b0b0b0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

@media (max-width: 575.98px) {
    .kanban-board {
        gap: 12px;
    }

    .add-column {
        width: 86vw;
    }
}
</style>
