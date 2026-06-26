<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';

const props = defineProps({
    boards: { type: Array, default: () => [] },
});

const roleLabels = {
    board_member_manager: 'người quản lý',
    board_editor: 'người chỉnh sửa',
    board_viewer: 'người xem',
};
const roleLabel = (role) => roleLabels[role] || 'người sở hữu';

// --- Tạo bảng mới ---
const showCreate = ref(false);
const createForm = useForm({ name: '', description: '' });
const openCreate = () => { createForm.reset(); createForm.clearErrors(); showCreate.value = true; };
const submitCreate = () => {
    createForm.post(route('boards.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; },
    });
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
        <div v-if="showCreate" class="modal-backdrop-custom" @click.self="showCreate = false">
            <div class="card shadow modal-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Tạo bảng mới</h6>
                    <button type="button" class="close" @click="showCreate = false"><span>&times;</span></button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="submitCreate">
                        <div class="form-group">
                            <input type="text" class="form-control" v-model="createForm.name"
                                placeholder="Nhập tên bảng..." required maxlength="255" autofocus>
                            <div v-if="createForm.errors.name" class="text-danger small mt-1">{{ createForm.errors.name }}</div>
                        </div>
                        <div class="text-right">
                            <Btn type="button" variant="white" class="btn-sm" @click="showCreate = false">Huỷ</Btn>
                            <Btn variant="black" class="btn-sm px-3" :disabled="createForm.processing">Tạo</Btn>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal đổi tên -->
        <div v-if="showRename" class="modal-backdrop-custom" @click.self="showRename = false">
            <div class="card shadow modal-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Nhập tên bảng mới</h6>
                    <button type="button" class="close" @click="showRename = false"><span>&times;</span></button>
                </div>
                <div class="card-body">
                    <form @submit.prevent="submitRename">
                        <div class="form-group">
                            <input type="text" class="form-control" v-model="renameForm.name"
                                placeholder="Nhập tên..." required maxlength="255" autofocus>
                            <div v-if="renameForm.errors.name" class="text-danger small mt-1">{{ renameForm.errors.name }}</div>
                        </div>
                        <div class="text-right">
                            <Btn type="button" variant="white" class="btn-sm" @click="showRename = false">Huỷ</Btn>
                            <Btn variant="black" class="btn-sm px-3" :disabled="renameForm.processing">OK</Btn>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
.modal-card { width: 100%; max-width: 380px; }
</style>
