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

const openMenuId = ref(null);
const toggleMenu = (id) => { openMenuId.value = openMenuId.value === id ? null : id; };
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
                        class="col-md-4 col-lg-3 mt-2 mb-2 board-card"
                        style="position: relative; overflow: visible; height:120px">
                        <div class="card shadow-sm h-80 card-hover">
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                    <h6 class="mb-0 text-truncate font-weight-bold board-name">{{ board.name }}</h6>
                                    <div class="dropdown">
                                        <a href="#" class="text-muted" @click.prevent="toggleMenu(board.id)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right" :class="{ show: openMenuId === board.id }"
                                            style="position:absolute; right:0; z-index:9999;">
                                            <a class="dropdown-item" :href="board.show_url">
                                                <i class="fas fa-folder-open fa-fw mr-2 text-muted"></i>Mở
                                            </a>
                                            <a class="dropdown-item" href="#" @click.prevent="openRename(board); toggleMenu(board.id)">
                                                <i class="fas fa-pencil-alt fa-fw mr-2 text-muted"></i>Sửa tên
                                            </a>
                                            <a class="dropdown-item" href="#" @click.prevent="duplicate(board); toggleMenu(board.id)">
                                                <i class="fas fa-copy fa-fw mr-2 text-muted"></i>Nhân bản
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#" @click.prevent="destroy(board); toggleMenu(board.id)">
                                                <i class="fas fa-trash-alt fa-fw mr-2"></i> Xoá
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <div class="d-flex align-items-center">
                                        <i class="far fa-clock fa-fw mr-2"></i>
                                        <span>{{ board.updated_at }}</span>
                                    </div>
                                    <b class="ml-2">{{ roleLabel(board.currentUserRole) }}</b>
                                </div>
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
/* Thẻ bảng */
.card-hover {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
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

/* Menu thao tác trên thẻ bảng */
.dropdown-item .fa-fw {
    text-align: center;
}

.dropdown-item.text-danger:hover,
.dropdown-item.text-danger:focus {
    background-color: #f8d7da;
    color: #721c24;
}
</style>
