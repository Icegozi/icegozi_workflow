<script setup>
import { ref, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TaskModal from '@/Components/TaskModal.vue';

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
const addingTaskCol = ref(null);
const newTaskTitle = ref('');
const saveTask = async (col) => {
    const title = newTaskTitle.value.trim();
    if (!title) return;
    try {
        const { data } = await axios.post(route('tasks.store', col.id), { title });
        col.tasks.push({
            id: data.task.id, title: data.task.title, column_id: col.id,
            position: data.task.position, due_date: data.task.due_date,
            formatted_due_date: data.task.formatted_due_date, assignees: data.task.assignees || [],
        });
        newTaskTitle.value = '';
        addingTaskCol.value = null;
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
        <div class="board-header p-3 mb-2 border-bottom text-dark">
            <h3>{{ board.name }}</h3>
        </div>

        <div class="kanban-board" id="kanbanBoard" style="display:flex; align-items:flex-start; overflow-x:auto;">
            <div v-for="col in columns" :key="col.id" class="kanban-column"
                style="flex:0 0 300px; margin-right:12px; background:#ebecf0; border-radius:6px; padding:8px;">
                <div class="column-header d-flex justify-content-between align-items-center mb-2">
                    <h5 class="column-title flex-grow-1 mr-2 mb-0">{{ col.name }}</h5>
                    <div v-if="canEdit" class="column-actions">
                        <button class="btn btn-sm btn-light" title="Đổi tên cột" @click="renameColumn(col)"><i class="fas fa-pencil-alt"></i></button>
                        <button v-if="canManage" class="btn btn-sm btn-light" title="Xoá cột" @click="deleteColumn(col)"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>

                <draggable v-model="col.tasks" :group="'tasks'" item-key="id" :disabled="!canEdit"
                    class="column-content" style="min-height:20px;"
                    @change="(e) => onTaskChange(col, e)">
                    <template #item="{ element: task }">
                        <div class="kanban-card" style="background:#fff; border-radius:4px; padding:8px; margin-bottom:8px; cursor:pointer; box-shadow:0 1px 0 rgba(9,30,66,.25);"
                            @click="openTask(task)">
                            <h6 class="mb-1">{{ task.title }}</h6>
                            <small v-if="task.formatted_due_date" class="text-warning d-block">
                                <i class="far fa-clock"></i> {{ task.formatted_due_date }}
                            </small>
                            <div v-if="task.assignees && task.assignees.length" class="mt-1">
                                <img v-for="a in task.assignees" :key="a.id"
                                    :src="`https://i.pravatar.cc/24?u=${encodeURIComponent(a.email)}`"
                                    :title="a.name" class="rounded-circle" width="22" height="22" style="margin-right:-6px;">
                            </div>
                        </div>
                    </template>
                </draggable>

                <div v-if="canEdit" class="mt-2">
                    <template v-if="addingTaskCol === col.id">
                        <input type="text" class="form-control form-control-sm mb-1" v-model="newTaskTitle"
                            placeholder="Tiêu đề công việc..." @keyup.enter="saveTask(col)" autofocus>
                        <button class="btn btn-success btn-sm mr-1" @click="saveTask(col)">Thêm</button>
                        <button class="btn btn-secondary btn-sm" @click="addingTaskCol = null">Huỷ</button>
                    </template>
                    <a v-else href="#" class="text-muted small" @click.prevent="addingTaskCol = col.id; newTaskTitle = ''">
                        <i class="fas fa-plus"></i> Thêm công việc
                    </a>
                </div>
            </div>

            <!-- Thêm cột -->
            <div v-if="canEdit" class="kanban-column" style="flex:0 0 300px;">
                <template v-if="addingColumn">
                    <div class="p-2 bg-white rounded">
                        <input type="text" class="form-control form-control-sm mb-2" v-model="newColumnName"
                            placeholder="Nhập tên cột..." @keyup.enter="saveColumn" autofocus>
                        <button class="btn btn-success btn-sm mr-1" @click="saveColumn">Lưu</button>
                        <button class="btn btn-secondary btn-sm" @click="addingColumn = false">Huỷ</button>
                    </div>
                </template>
                <div v-else class="add-column-placeholder text-dark p-2" style="cursor:pointer;"
                    @click="addingColumn = true">
                    <i class="fas fa-plus"></i> THÊM CỘT
                </div>
            </div>
        </div>

        <TaskModal v-if="modalTaskId" :task-id="modalTaskId" :can-edit="canEdit" :can-manage="canManage"
            :board-id="board.id" @close="closeTask" @updated="onTaskUpdated" @deleted="onTaskDeleted" />
    </AuthenticatedLayout>
</template>
