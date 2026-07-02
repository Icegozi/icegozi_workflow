<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    boards: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
});

const roleLabels = {
    board_member_manager: 'người quản lý',
    board_editor: 'người chỉnh sửa',
    board_viewer: 'người xem',
};
const roleLabel = (role) => roleLabels[role] || 'người sở hữu';

// --- Tạo bảng mới ---
const showCreate = ref(false);
const createForm = useForm({ name: '', description: '', template_id: null });
const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.template_id = props.templates[0]?.key ?? null;
    showCreate.value = true;
};
const submitCreate = () => {
    createForm.post(route('boards.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; },
    });
};

// --- Nhân bản bảng ---
const duplicate = (board) => {
    if (!confirm(`Nhân bản bảng "${board.name}" (kèm toàn bộ công việc)?`)) return;
    router.post(route('boards.duplicate', board.id), { with_tasks: true }, { preserveScroll: true });
};

// --- Đổi tên bảng ---
const showRename = ref(false);
const renameId = ref(null);
const renameForm = useForm({ name: '' });
const openRename = (board) => {
    renameId.value = board.id;
    renameForm.name = board.name;
    renameForm.clearErrors();
    showRename.value = true;
};
const submitRename = () => {
    renameForm.put(route('boards.update', renameId.value), {
        preserveScroll: true,
        onSuccess: () => { showRename.value = false; },
    });
};

// --- Xóa bảng ---
const destroy = (board) => {
    if (confirm(`Xoá bảng "${board.name}"? Hành động này không thể hoàn tác.`)) {
        router.delete(route('boards.destroy', board.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Bảng của tôi" />
    <AuthenticatedLayout>
        <div class="container">
            <div class="row mb-3 align-items-center">
                <div class="col-md-12">
                    <Btn type="button" variant="success" @click="openCreate">
                        <i class="fas fa-plus mr-1"></i> Tạo bảng mới
                    </Btn>
                </div>
            </div>

            <div class="row" id="board-list-container">
                <template v-if="props.boards.length">
                    <div v-for="board in props.boards" :key="board.id"
                        class="col-sm-6 col-md-4 col-lg-3 mb-3 board-card">
                        <div class="board-tile">
                            <!-- Vùng thông tin: bấm để mở bảng -->
                            <a :href="board.show_url" class="board-tile__body">
                                <h6 class="board-tile__name text-truncate">{{ board.name }}</h6>
                                <div class="board-tile__meta">
                                    <span class="board-tile__time">
                                        <i class="far fa-clock mr-1"></i>{{ board.updated_at }}
                                    </span>
                                    <span class="board-tile__role">{{ roleLabel(board.currentUserRole) }}</span>
                                </div>
                            </a>

                            <!-- Thanh hành động ngay trên card -->
                            <div class="board-tile__actions">
                                <Btn :href="board.show_url" variant="secondary" outline icon="fas fa-folder-open"
                                    class="btn-sm flex-fill" title="Mở bảng" aria-label="Mở bảng" />
                                <Btn type="button" variant="secondary" outline icon="fas fa-pen"
                                    class="btn-sm flex-fill" title="Sửa tên" aria-label="Sửa tên"
                                    @click="openRename(board)" />
                                <Btn type="button" variant="secondary" outline icon="fas fa-clone"
                                    class="btn-sm flex-fill" title="Nhân bản" aria-label="Nhân bản"
                                    @click="duplicate(board)" />
                                <Btn type="button" variant="red" outline icon="fas fa-trash-alt"
                                    class="btn-sm flex-fill" title="Xoá bảng" aria-label="Xoá bảng"
                                    @click="destroy(board)" />
                            </div>
                        </div>
                    </div>
                </template>
                <div v-else class="col-12">
                    <p class="text-muted text-center mt-5">Bạn chưa có bảng làm việc nào. Hãy tạo một bảng mới!</p>
                </div>
            </div>
        </div>

        <!-- Modal tạo bảng -->
        <Modal v-if="showCreate" title="Tạo bảng mới" max-width="620px" @close="showCreate = false">
            <form @submit.prevent="submitCreate">
                <div class="form-group">
                    <label class="small font-weight-bold">Tên bảng</label>
                    <TextInput v-model="createForm.name" placeholder="Nhập tên bảng..."
                        required maxlength="255" autofocus group-class="" />
                    <div v-if="createForm.errors.name" class="text-danger small mt-1">{{ createForm.errors.name }}</div>
                </div>

                <label class="small font-weight-bold">Chọn mẫu</label>
                <div class="template-grid mb-3">
                    <button v-for="tpl in templates" :key="tpl.key" type="button"
                        class="template-card" :class="{ active: createForm.template_id === tpl.key }"
                        @click="createForm.template_id = tpl.key">
                        <div class="template-head">
                            <i class="fas" :class="tpl.icon"></i>
                            <span class="template-name">{{ tpl.name }}</span>
                        </div>
                        <div class="template-desc">{{ tpl.description }}</div>
                        <div class="template-cols">
                            <span v-for="c in tpl.columns" :key="c" class="template-col">{{ c }}</span>
                        </div>
                    </button>
                </div>

                <div class="text-right">
                    <Btn type="button" variant="white" class="btn-sm" @click="showCreate = false">Huỷ</Btn>
                    <Btn variant="black" class="btn-sm px-3" :disabled="createForm.processing">Tạo</Btn>
                </div>
            </form>
        </Modal>

        <!-- Modal đổi tên -->
        <Modal v-if="showRename" title="Nhập tên bảng mới" max-width="380px" @close="showRename = false">
            <form @submit.prevent="submitRename">
                <div class="form-group">
                    <TextInput v-model="renameForm.name" placeholder="Nhập tên..."
                        required maxlength="255" autofocus group-class="" />
                    <div v-if="renameForm.errors.name" class="text-danger small mt-1">{{ renameForm.errors.name }}</div>
                </div>
                <div class="text-right">
                    <Btn type="button" variant="white" class="btn-sm" @click="showRename = false">Huỷ</Btn>
                    <Btn variant="black" class="btn-sm px-3" :disabled="renameForm.processing">OK</Btn>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* ---------------- Thẻ bảng (board tile) ---------------- */
.board-tile {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(9, 30, 66, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    overflow: hidden;
}

.board-tile:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 22px rgba(9, 30, 66, 0.12);
    border-color: var(--app-accent);
}

/* Vùng thông tin có thể bấm để mở bảng */
.board-tile__body {
    display: block;
    flex: 1 1 auto;
    padding: 16px 16px 12px;
    text-decoration: none;
    color: inherit;
}

.board-tile__body:hover {
    text-decoration: none;
    color: inherit;
}

.board-tile__name {
    margin: 0 0 14px;
    font-weight: 700;
    font-size: 1rem;
    color: var(--app-text);
}

.board-tile__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.75rem;
    color: var(--app-text-muted);
}

.board-tile__time {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.board-tile__role {
    flex-shrink: 0;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.7rem;
    background: rgba(127, 127, 127, 0.12);
    color: var(--app-text-muted);
    white-space: nowrap;
}

/* Thanh hành động: các nút icon dùng chung <Btn>, chia đều bằng flex-fill */
.board-tile__actions {
    display: flex;
    gap: 6px;
    padding: 8px 10px;
    border-top: 1px solid var(--app-border);
    background: rgba(127, 127, 127, 0.04);
}

/* Lưới chọn mẫu bảng */
.template-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.template-card {
    text-align: left;
    background: var(--app-surface);
    border: 1px solid #e4e6ea;
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.template-card:hover {
    border-color: #c1c7d0;
}

.template-card.active {
    border-color: var(--app-accent, #663300);
    box-shadow: 0 0 0 2px rgba(102, 51, 0, 0.25);
}

.template-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: #172b4d;
}

.template-desc {
    font-size: 0.75rem;
    color: #7a869a;
    margin: 4px 0 6px;
}

.template-cols {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.template-col {
    font-size: 0.66rem;
    background: #f1f2f4;
    color: #44546f;
    border-radius: 4px;
    padding: 1px 6px;
}

@media (max-width: 575.98px) {
    .template-grid {
        grid-template-columns: 1fr;
    }
}
</style>
