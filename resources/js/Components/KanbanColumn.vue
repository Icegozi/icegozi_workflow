<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import draggable from 'vuedraggable';
import KanbanCard from '@/Components/KanbanCard.vue';
import Btn from '@/Components/Btn.vue';
import { showAppPrompt } from '@/composables/useAppAlert';

const props = defineProps({
    col: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    match: { type: Function, default: null },
});

const emit = defineEmits([
    'rename',
    'delete',
    'delete-task',
    'task-change',
    'add-task',
    'open-task',
]);

// Trên điện thoại, cử chỉ vuốt ngang được dành cho việc chuyển giữa các cột.
// Kéo thả vẫn khả dụng trên iPad/desktop, nơi không cần cuộn ngang bằng chính
// vùng chạm của card.
const isMobileViewport = ref(false);
let mobileViewportQuery;

const updateMobileViewport = () => {
    isMobileViewport.value = mobileViewportQuery?.matches ?? false;
};

onMounted(() => {
    mobileViewportQuery = window.matchMedia('(max-width: 767.98px)');
    updateMobileViewport();
    mobileViewportQuery.addEventListener('change', updateMobileViewport);
});

onBeforeUnmount(() => {
    mobileViewportQuery?.removeEventListener('change', updateMobileViewport);
});

const openAddTask = async () => {
    const title = await showAppPrompt(
        `Tên công việc mới trong cột "${props.col.name}":`,
        '',
        'warning'
    );

    if (title?.trim()) {
        emit('add-task', title.trim());
    }
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

        <draggable
            v-model="col.tasks"
            :group="'tasks'"
            item-key="id"
            :disabled="!canEdit || isMobileViewport"
            :animation="150"
            :force-fallback="true"
            :fallback-on-body="true"
            :fallback-tolerance="3"
            ghost-class="kanban-card--ghost"
            chosen-class="kanban-card--chosen"
            drag-class="kanban-card--dragging"
            class="column-content"
            @change="(e) => emit('task-change', e)"
        >
            <template #item="{ element: task }">
                <KanbanCard
                    v-show="!match || match(task)"
                    :task="task"
                    :can-manage="canManage"
                    @open="emit('open-task', task)"
                    @delete="emit('delete-task', task)"
                />
            </template>
        </draggable>

        <div v-if="canEdit" class="mt-2">
            <Btn type="button" variant="white" icon="fas fa-plus"
                class="btn-sm btn-block add-task-btn" @click="openAddTask">Thêm công việc</Btn>
        </div>
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
    /* Cho phép trang tiếp tục cuộn dọc khi chạm vào danh sách trên iPad. */
    touch-action: pan-y;
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

@media (max-width: 767.98px) {
    .column-content {
        /* Không chặn cử chỉ vuốt ngang của .kanban-board trên điện thoại. */
        touch-action: auto;
    }
}
</style>
