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
    { key: 'role', label: 'Vai trò', width: '32%' },
];

const page = usePage();
const flash = computed(() => page.props.flash || {});
const errors = computed(() => page.props.errors || {});

const memberCount = computed(() => props.members.length + 1); // +1 chủ sở hữu

const avatar = (email) => `https://i.pravatar.cc/40?u=${encodeURIComponent(email || 'x')}`;

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
    <Head :title="`Quản lý thành viên · ${board.name}`" />
    <AuthenticatedLayout>
        <div class="settings-page">
            <!-- Header -->
            <header class="sp-header">
                <div class="sp-header__main">
                    <Btn :href="route('boards.show', board.id)" variant="white" icon="fas fa-arrow-left"
                        class="btn-sm sp-back">Quay lại bảng</Btn>
                    <div class="sp-heading">
                        <span class="sp-eyebrow"><i class="fas fa-users-cog mr-1"></i>Quản lý thành viên</span>
                        <h1 class="sp-title" :title="board.name">{{ board.name }}</h1>
                    </div>
                </div>
                <span class="sp-count"><i class="fas fa-users mr-1"></i>{{ memberCount }} thành viên</span>
            </header>

            <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
            <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>
            <div v-if="flash.warning" class="alert alert-warning">{{ flash.warning }}</div>

            <div class="row">
                <!-- Cột trái: Mời + Lời mời đang chờ -->
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div v-if="canManage" class="panel mb-4">
                        <div class="panel__head">
                            <h6 class="panel__title"><i class="fas fa-user-plus"></i>Mời thành viên mới</h6>
                        </div>
                        <div class="panel__body">
                            <form @submit.prevent="submitInvite">
                                <div class="form-group">
                                    <label class="fld-label">Email thành viên</label>
                                    <TextInput type="email" v-model="inviteForm.email" required
                                        placeholder="nhap@emailcuaban.com" icon="fas fa-envelope" group-class="mb-0" />
                                    <div v-if="errors.email" class="text-danger small mt-1">{{ errors.email }}</div>
                                </div>
                                <div class="form-group">
                                    <label class="fld-label">Vai trò</label>
                                    <SelectInput v-model="inviteForm.role_permission_name" :options="potentialRoles"
                                        placeholder="-- Chọn vai trò --" required />
                                    <div v-if="errors.role_permission_name" class="text-danger small mt-1">{{ errors.role_permission_name }}</div>
                                </div>
                                <p class="text-muted sp-hint">
                                    <i class="fas fa-info-circle mr-1"></i>Thành viên sẽ nhận lời mời qua email và cần chấp nhận để tham gia bảng.
                                </p>
                                <Btn variant="primary" icon="fas fa-paper-plane" class="btn-block"
                                    :disabled="inviteForm.processing">Gửi lời mời</Btn>
                            </form>
                        </div>
                    </div>

                    <div v-if="canManage" class="panel">
                        <div class="panel__head">
                            <h6 class="panel__title"><i class="fas fa-hourglass-half"></i>Lời mời đang chờ</h6>
                            <span class="count-pill">{{ invitations.length }}</span>
                        </div>
                        <div class="panel__body p-0">
                            <ul v-if="invitations.length" class="invite-list">
                                <li v-for="inv in invitations" :key="inv.id" class="invite-item">
                                    <div class="invite-item__info">
                                        <span class="invite-item__email">{{ inv.email }}</span>
                                        <span class="role-badge">{{ potentialRoles[inv.role_permission_name] || inv.role_permission_name }}</span>
                                        <span class="invite-item__meta">Mời bởi {{ inv.inviter_name }} · {{ inv.created_at_human }}</span>
                                    </div>
                                    <Btn type="button" variant="danger" outline icon="fas fa-times"
                                        class="btn-sm" title="Hủy lời mời" @click="cancelInvite(inv)" />
                                </li>
                            </ul>
                            <div v-else class="empty-state">
                                <i class="fas fa-inbox d-block mb-2"></i>
                                Không có lời mời nào đang chờ.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cột phải: Thành viên -->
                <div class="col-lg-7">
                    <div class="panel">
                        <div class="panel__head">
                            <h6 class="panel__title"><i class="fas fa-users"></i>Thành viên hiện tại</h6>
                            <span class="count-pill">{{ memberCount }}</span>
                        </div>
                        <div class="panel__body">
                            <DataTable :columns="memberColumns" :rows="members" :show-actions="canManage"
                                empty-text="Chưa có thành viên nào (ngoài chủ sở hữu).">
                                <!-- Chủ sở hữu luôn đứng đầu -->
                                <template #prepend>
                                    <tr>
                                        <td data-label="Thành viên">
                                            <div class="member-cell">
                                                <img :src="avatar(owner.email)" class="member-avatar" alt="">
                                                <strong>{{ owner.name }}</strong>
                                            </div>
                                        </td>
                                        <td data-label="Email">{{ owner.email }}</td>
                                        <td data-label="Vai trò"><span class="owner-badge"><i class="fas fa-crown mr-1"></i>Chủ sở hữu</span></td>
                                        <td v-if="canManage" data-label="Hành động"></td>
                                    </tr>
                                </template>
                                <template #cell-name="{ row }">
                                    <div class="member-cell">
                                        <img :src="avatar(row.email)" class="member-avatar" alt="">
                                        <span>{{ row.name }}</span>
                                    </div>
                                </template>
                                <template #cell-role="{ row }">
                                    <SelectInput :model-value="row.role" :options="potentialRoles"
                                        :disabled="!canManage" class="form-control-sm"
                                        @update:model-value="(val) => changeRole(row, val)" />
                                </template>
                                <template #actions="{ row }">
                                    <Btn type="button" variant="danger" outline icon="fas fa-user-times"
                                        class="btn-sm" title="Xóa thành viên" @click="removeMember(row)" />
                                </template>
                            </DataTable>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-page {
    max-width: 1120px;
    margin: 0 auto;
}

/* ---------------- Header ---------------- */
.sp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--app-border);
}

.sp-header__main {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

/* Nút "Quay lại bảng" cố định: không co, không ngắt chữ -> luôn 1 dòng dù tên bảng dài.
   Phần co lại để nhường chỗ là khối tiêu đề (.sp-heading), không phải nút. */
.sp-back {
    flex-shrink: 0;
    white-space: nowrap;
}

.sp-eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--app-accent);
}
.sp-heading {
    min-width: 0;
}

.sp-title {
    margin: 2px 0 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--app-text);
    /* Cắt 1 dòng kèm dấu … thay vì xuống dòng; tên đầy đủ xem qua tooltip (title). */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sp-count {
    flex-shrink: 0;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--app-text-muted);
    background: rgba(127, 127, 127, 0.1);
    padding: 6px 14px;
    border-radius: 20px;
}

/* ---------------- Panel ---------------- */
.panel {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(9, 30, 66, 0.05);
    overflow: hidden;
}

.panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--app-border);
    background: rgba(127, 127, 127, 0.04);
}

.panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--app-text);
}

.panel__title i {
    color: var(--app-accent);
}

.panel__body {
    padding: 18px;
}

.count-pill {
    min-width: 24px;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    background: var(--app-accent);
    border-radius: 20px;
    padding: 2px 9px;
}

/* ---------------- Form mời ---------------- */
.fld-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--app-text-muted);
    margin-bottom: 6px;
}

.sp-hint {
    font-size: 0.76rem;
    line-height: 1.5;
    margin: 0 0 14px;
}

/* ---------------- Lời mời đang chờ ---------------- */
.invite-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.invite-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 18px;
    border-top: 1px solid var(--app-border);
}

.invite-item:first-child { border-top: 0; }

.invite-item__info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.invite-item__email {
    font-weight: 600;
    color: var(--app-text);
    word-break: break-word;
}

.invite-item__meta {
    font-size: 0.72rem;
    color: var(--app-text-muted);
}

.role-badge {
    align-self: flex-start;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--app-accent);
    background: rgba(102, 51, 0, 0.08);
    border-radius: 20px;
    padding: 2px 10px;
}

.empty-state {
    text-align: center;
    color: var(--app-text-muted);
    font-size: 0.85rem;
    padding: 28px 18px;
}

.empty-state i {
    font-size: 1.6rem;
    opacity: 0.5;
}

/* ---------------- Thành viên ---------------- */
.member-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.member-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
}

.owner-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 600;
    color: #b45309;
    background: #fef3c7;
    border-radius: 20px;
    padding: 3px 12px;
}

[data-theme="dark"] .role-badge {
    color: var(--app-accent-2);
    background: rgba(165, 118, 63, 0.15);
}

[data-theme="dark"] .owner-badge {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.15);
}

@media (max-width: 575.98px) {
    .sp-header {
        align-items: stretch;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }

    .sp-header__main {
        align-items: flex-start;
        gap: 10px;
    }

    .sp-back {
        width: 42px;
        min-width: 42px;
        padding-right: 0;
        padding-left: 0;
        font-size: 0;
    }

    .sp-back i {
        margin-right: 0 !important;
        font-size: 0.85rem;
    }

    .sp-title {
        font-size: 1.1rem;
    }

    .sp-count {
        align-self: flex-start;
        margin-left: 52px;
        padding: 5px 11px;
    }

    .panel__head,
    .panel__body {
        padding: 12px;
    }

    .invite-item {
        align-items: flex-start;
        padding: 12px;
    }
}
</style>
