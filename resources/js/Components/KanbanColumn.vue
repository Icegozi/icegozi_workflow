<script setup>
import { ref } from 'vue';
import draggable from 'vuedraggable';
import KanbanCard from '@/Components/KanbanCard.vue';
import TextInput from '@/Components/TextInput.vue';
import Btn from '@/Components/Btn.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    col: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    match: { type: Function, default: null },
});

const emit = defineEmits(['rename', 'delete', 'task-change', 'add-task', 'open-task']);

// State cục bộ cho form thêm công việc (mỗi cột tự quản)
const adding = ref(false);
const newTitle = ref('');

const submitTask = () => {
    const title = newTitle.value.trim();
    if (!title) return;
    emit('add-task', title);
    newTitle.value = '';
    adding.value = false;
};
</script>

<template>
    <div class="kanban-column">
        <div class="column-header d-flex justify-content-between align-items-center mb-2">
            <h5 class="column-title flex-grow-1 mr-2 mb-0">{{ col.name }}</h5>
            <div v-if="canEdit" class="column-actions">
                <button class="btn btn-sm btn-light btn--icon-only" title="Đổi tên cột" @click="emit('rename')">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button v-if="canManage" class="btn btn-sm btn-light btn--icon-only" title="Xoá cột" @click="emit('delete')">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>

        <draggable v-model="col.tasks" :group="'tasks'" item-key="id" :disabled="!canEdit"
            class="column-content" @change="(e) => emit('task-change', e)">
            <template #item="{ element: task }">
                <KanbanCard v-show="!match || match(task)" :task="task" @open="emit('open-task', task)" />
            </template>
        </draggable>

        <div v-if="canEdit" class="mt-2">
            <Btn type="button" variant="white" icon="fas fa-plus"
                class="btn-sm btn-block add-task-btn" @click="adding = true; newTitle = ''">Thêm công việc</Btn>
        </div>

        <!-- Form thêm công việc trong modal -->
        <Modal v-if="adding" :title="`Thêm công việc · ${col.name}`" max-width="440px"
            align="center" @close="adding = false">
            <form class="modal-form" @submit.prevent="submitTask">
                <div class="form-group">
                    <label class="small font-weight-bold mb-1">Tiêu đề công việc</label>
                    <TextInput v-model="newTitle" placeholder="Nhập tiêu đề công việc..." autofocus group-class="mb-0" />
                </div>
                <div class="modal-form__actions">
                    <Btn type="button" variant="white" class="btn-sm mr-2" @click="adding = false">Huỷ</Btn>
                    <Btn variant="success" icon="fas fa-plus" class="btn-sm px-3">Thêm công việc</Btn>
                </div>
            </form>
        </Modal>
    </div>
</template>

<style scoped>
.kanban-column {
    /* Bề rộng co giãn theo màn: tối thiểu 250px, ~85% màn nhỏ, tối đa 290px */
    flex: 0 0 auto;
    width: clamp(250px, 85vw, 290px);
    /* Cao theo nội dung (không giới hạn, không cuộn trong cột) */
    min-height: 120px;
    background: var(--app-bg);
    border-radius: 10px;
    padding: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.column-header {
    cursor: grab;
}

.column-title {
    cursor: pointer;
    transition: background-color 0.2s ease;
    flex-grow: 1;
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
    margin-bottom: 0;
    padding: 5px 8px;
    font-size: small;
    background: linear-gradient(135deg, var(--app-accent), var(--app-accent-2));
    color: #ffffff;
    border-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 4px 12px rgba(102, 51, 0, 0.35);
    text-align: center;
}

.column-title:hover {
    background: linear-gradient(135deg, var(--app-accent-dark), var(--app-accent));
    color: #ffffff;
}

.column-actions .btn {
    padding: 0.1rem 0.4rem;
    font-size: 0.8em;
    line-height: 1.2;
    margin-left: 4px;
}

.column-content {
    /* Cao theo số lượng task, không cuộn dọc bên trong cột */
    flex: 1 1 auto;
    min-height: 40px;
    padding-top: 5px;
    padding-bottom: 5px;
    background-image: none;
    width: 100%;
    margin: 0;
}

/* Nút "Thêm công việc": dạng ghost gạch đứt, dịu; hover mới nổi màu accent.
   Dùng biến theme nên nhất quán ở cả chế độ sáng lẫn tối. */
.add-task-btn {
    justify-content: flex-start;
    border: 1px dashed var(--app-border) !important;
    background: transparent !important;
    color: var(--app-text-muted) !important;
    font-weight: 500;
    box-shadow: none !important;
}

.add-task-btn:hover {
    background: rgba(102, 51, 0, 0.08) !important;
    color: var(--app-accent) !important;
    border-color: var(--app-accent) !important;
}

[data-theme="dark"] .add-task-btn:hover {
    background: rgba(165, 118, 63, 0.18) !important;
    color: var(--app-accent-2) !important;
    border-color: var(--app-accent-2) !important;
}

@media (max-width: 575.98px) {
    .kanban-column {
        width: 86vw;
        padding: 10px;
    }
}
</style>
