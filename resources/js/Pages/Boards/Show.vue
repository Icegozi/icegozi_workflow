<script setup>
import { ref, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
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
            priority: data.task.priority || 'normal', has_description: false,
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

// ---- Modal chi tiết ----
const modalTaskId = ref(null);
const openTask = (task) => { modalTaskId.value = task.id; };
const closeTask = () => { modalTaskId.value = null; };
const onTaskUpdated = (payload) => {
    for (const col of columns) {
        const t = col.tasks.find((x) => x.id === payload.id);
        if (t) {
            if (payload.title !== undefined) t.title = payload.title;
            if (payload.due_date !== undefined) {
                t.due_date = payload.due_date;
                t.formatted_due_date = payload.formatted_due_date;
            }
        }
    }
};
const onTaskDeleted = (id) => {
    for (const col of columns) {
        const idx = col.tasks.findIndex((t) => t.id === id);
        if (idx !== -1) col.tasks.splice(idx, 1);
    }
    closeTask();
};
</script>

<template>
    <Head :title="`${board.name} - Kanban`" />
    <AuthenticatedLayout>
        <div class="board-header p-3 mb-2 border-bottom">
            <h3 class="mb-0">{{ board.name }}</h3>
        </div>

        <div class="kanban-board" id="kanbanBoard">
            <KanbanColumn v-for="col in columns" :key="col.id" :col="col"
                :can-edit="canEdit" :can-manage="canManage"
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
            :board-id="board.id" @close="closeTask" @updated="onTaskUpdated" @deleted="onTaskDeleted" />
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
