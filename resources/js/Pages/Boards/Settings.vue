<script setup>
import { computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    board: { type: Object, required: true },
    owner: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    potentialRoles: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const memberColumns = [
    { key: 'name', label: 'Thành viên' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Vai trò', width: '30%' },
];

const page = usePage();
const flash = computed(() => page.props.flash || {});
const errors = computed(() => page.props.errors || {});

const avatar = (email) => `https://i.pravatar.cc/30?u=${encodeURIComponent(email)}`;

// Mời thành viên
const inviteForm = useForm({ email: '', role_permission_name: '' });
const submitInvite = () => {
    inviteForm.post(route('boards.invite', props.board.id), {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
};

// Đổi vai trò
const changeRole = (member, newRole) => {
    router.post(route('boards.members.updateRole', [props.board.id, member.id]),
        { new_role_permission_name: newRole }, { preserveScroll: true });
};

// Xóa thành viên
const removeMember = (member) => {
    if (confirm(`Xóa ${member.name} khỏi bảng?`)) {
        router.delete(route('boards.members.remove', [props.board.id, member.id]), { preserveScroll: true });
    }
};

// Hủy lời mời
const cancelInvite = (inv) => {
    if (confirm('Hủy lời mời này?')) {
        router.delete(route('boards.invitations.cancel', [props.board.id, inv.id]), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Quản lý thành viên bảng ${board.name}`" />
    <AuthenticatedLayout>
        <div class="container-fluid mt-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0 text-dark"><span class="font-weight-bold">{{ board.name }}</span></h1>
            </div>
            <hr class="my-4">

            <div v-if="flash.success" class="alert alert-success small p-2">{{ flash.success }}</div>
            <div v-if="flash.error" class="alert alert-danger small p-2">{{ flash.error }}</div>
            <div v-if="flash.warning" class="alert alert-warning small p-2">{{ flash.warning }}</div>

            <div class="row">
                <!-- Cột trái: Mời + Lời mời đang chờ -->
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div v-if="canManage" class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-secondary">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-user-plus mr-2"></i>Mời thành viên mới</h6>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="submitInvite">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Email thành viên</label>
                                    <TextInput type="email" v-model="inviteForm.email" required
                                        placeholder="nhap@emailcuaban.com" class="form-control-sm" group-class="" />
                                    <div v-if="errors.email" class="text-danger small">{{ errors.email }}</div>
                                </div>
                                <div class="form-group">
                                    <label class="small font-weight-bold">Vai trò (Quyền)</label>
                                    <SelectInput v-model="inviteForm.role_permission_name" :options="potentialRoles"
                                        placeholder="-- Chọn vai trò --" required class="form-control-sm" />
                                    <div v-if="errors.role_permission_name" class="text-danger small">{{ errors.role_permission_name }}</div>
                                </div>
                                <Btn variant="black" outline icon="fas fa-paper-plane" class="btn-sm btn-block"
                                    :disabled="inviteForm.processing">Gửi lời mời</Btn>
                            </form>
                        </div>
                    </div>

                    <div v-if="canManage" class="card shadow">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-secondary">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-hourglass-half mr-2"></i>Lời mời đang chờ
                                (<span class="badge badge-warning badge-pill">{{ invitations.length }}</span>)
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <ul v-if="invitations.length" class="list-group list-group-flush">
                                <li v-for="inv in invitations" :key="inv.id" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="font-weight-bold">{{ inv.email }}</span>
                                            <em class="small text-muted d-block">Vai trò: {{ potentialRoles[inv.role_permission_name] || inv.role_permission_name }}</em>
                                            <small class="text-muted d-block" style="font-size:.75rem;">Mời bởi: {{ inv.inviter_name }} ({{ inv.created_at_human }})</small>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm ml-2" title="Hủy lời mời" @click="cancelInvite(inv)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </li>
                            </ul>
                            <div v-else class="p-3 text-center text-muted small">
                                <i class="fas fa-info-circle mr-1"></i> Không có lời mời nào đang chờ.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cột phải: Thành viên -->
                <div class="col-lg-7">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-secondary">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-users mr-2"></i>Thành viên hiện tại</h6>
                        </div>
                        <div class="card-body">
                            <DataTable :columns="memberColumns" :rows="members" :show-actions="canManage"
                                empty-text="Chưa có thành viên nào (ngoài chủ sở hữu).">
                                <!-- Chủ sở hữu luôn đứng đầu -->
                                <template #prepend>
                                    <tr>
                                        <td>
                                            <img :src="avatar(owner.email)" class="rounded-circle mr-2" width="24" height="24" alt="">
                                            <strong>{{ owner.name }}</strong>
                                        </td>
                                        <td>{{ owner.email }}</td>
                                        <td><span class="badge badge-success px-2 py-1">Chủ sở hữu</span></td>
                                        <td v-if="canManage"></td>
                                    </tr>
                                </template>
                                <template #cell-name="{ row }">
                                    <img :src="avatar(row.email)" class="rounded-circle mr-2" width="24" height="24" alt="">
                                    {{ row.name }}
                                </template>
                                <template #cell-role="{ row }">
                                    <SelectInput :model-value="row.role" :options="potentialRoles"
                                        :disabled="!canManage" class="form-control-sm"
                                        @update:model-value="(val) => changeRole(row, val)" />
                                </template>
                                <template #actions="{ row }">
                                    <button class="btn btn-outline-danger btn-sm" title="Xóa thành viên" @click="removeMember(row)">
                                        <i class="fas fa-user-times"></i>
                                    </button>
                                </template>
                            </DataTable>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
