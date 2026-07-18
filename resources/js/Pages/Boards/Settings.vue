<script setup>
import { computed } from 'vue';
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/vue3';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Btn from '@/Components/Btn.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import DataTable from '@/Components/DataTable.vue';
import { showAppConfirm } from '@/composables/useAppAlert';
import { avatarSrc } from '@/composables/useSocialLinks';

const props = defineProps({
    board: {
        type: Object,
        required: true,
    },

    owner: {
        type: Object,
        required: true,
    },

    members: {
        type: Array,
        default: () => [],
    },

    invitations: {
        type: Array,
        default: () => [],
    },

    potentialRoles: {
        type: Object,
        default: () => ({}),
    },

    canManage: {
        type: Boolean,
        default: false,
    },
});

const memberColumns = [
    {
        key: 'name',
        label: 'Thành viên',
    },
    {
        key: 'email',
        label: 'Email',
    },
    {
        key: 'role',
        label: 'Vai trò',
        width: '32%',
    },
];

const page = usePage();

const flash = computed(() => {
    return page.props.flash || {};
});

const errors = computed(() => {
    return page.props.errors || {};
});

const memberCount = computed(() => {
    return props.members.length + 1;
});

/* =========================================================
   MỜI THÀNH VIÊN
   ========================================================= */

const inviteForm = useForm({
    email: '',
    role_permission_name: '',
});

const submitInvite = () => {
    inviteForm.post(
        route(
            'boards.invite',
            props.board.id
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                inviteForm.reset();
            },
        }
    );
};

/* =========================================================
   ĐỔI VAI TRÒ
   ========================================================= */

const changeRole = (
    member,
    newRole
) => {
    router.post(
        route(
            'boards.members.updateRole',
            [
                props.board.id,
                member.id,
            ]
        ),
        {
            new_role_permission_name:
                newRole,
        },
        {
            preserveScroll: true,
        }
    );
};

/* =========================================================
   XÓA THÀNH VIÊN
   ========================================================= */

const removeMember = async (member) => {
    const confirmed = await showAppConfirm(
        `Xóa ${member.name} khỏi bảng?`,
        'danger'
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'boards.members.remove',
            [
                props.board.id,
                member.id,
            ]
        ),
        {
            preserveScroll: true,
        }
    );
};

/* =========================================================
   HỦY LỜI MỜI
   ========================================================= */

const cancelInvite = async (
    invitation
) => {
    const confirmed = await showAppConfirm(
        'Hủy lời mời này?',
        'danger'
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'boards.invitations.cancel',
            [
                props.board.id,
                invitation.id,
            ]
        ),
        {
            preserveScroll: true,
        }
    );
};
</script>

<template>
    <Head
        :title="`Quản lý thành viên · ${board.name}`"
    />

    <AuthenticatedLayout>
        <div class="settings-page">
            <!-- =================================================
                 HEADER
                 ================================================= -->

            <header class="sp-header">
                <div class="sp-header__main">
                    <Link
                        :href="route('boards.show', board.id)"
                        class="sp-back"
                        title="Quay lại bảng"
                        aria-label="Quay lại bảng"
                    >
                        <i
                            class="fas fa-arrow-left"
                            aria-hidden="true"
                        ></i>
                    </Link>

                    <div class="sp-heading">
                        <span class="sp-eyebrow">
                            <i
                                class="fas fa-users-cog mr-1"
                            ></i>

                            Quản lý thành viên
                        </span>

                        <h1
                            class="sp-title"
                            :title="board.name"
                        >
                            {{ board.name }}
                        </h1>
                    </div>
                </div>

                <span class="sp-count">
                    <i
                        class="fas fa-users mr-1"
                    ></i>

                    {{ memberCount }} thành viên
                </span>
            </header>

            <!-- =================================================
                 FLASH MESSAGE
                 ================================================= -->

            <div
                v-if="flash.success"
                class="alert alert-success"
            >
                {{ flash.success }}
            </div>

            <div
                v-if="flash.error"
                class="alert alert-danger"
            >
                {{ flash.error }}
            </div>

            <div
                v-if="flash.warning"
                class="alert alert-warning"
            >
                {{ flash.warning }}
            </div>

            <!-- =================================================
                 MAIN GRID
                 ================================================= -->

            <div class="row settings-grid">
                <!-- =============================================
                     CỘT TRÁI
                     ============================================= -->

                <div
                    class="col-lg-5 mb-4 mb-lg-0"
                >
                    <!-- Mời thành viên -->

                    <div
                        v-if="canManage"
                        class="panel mb-4"
                    >
                        <div class="panel__head">
                            <h6 class="panel__title">
                                <i
                                    class="fas fa-user-plus"
                                ></i>

                                Mời thành viên mới
                            </h6>
                        </div>

                        <div class="panel__body">
                            <form
                                @submit.prevent="
                                    submitInvite
                                "
                            >
                                <div class="form-group">
                                    <label
                                        class="fld-label"
                                        for="invite-email"
                                    >
                                        Email thành viên
                                    </label>

                                    <TextInput
                                        id="invite-email"
                                        v-model="
                                            inviteForm.email
                                        "
                                        type="email"
                                        required
                                        placeholder="nhap@emailcuaban.com"
                                        icon="fas fa-envelope"
                                        group-class="mb-0"
                                    />

                                    <div
                                        v-if="
                                            errors.email
                                        "
                                        class="text-danger small mt-1"
                                    >
                                        {{ errors.email }}
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label
                                        class="fld-label"
                                        for="invite-role"
                                    >
                                        Vai trò
                                    </label>

                                    <SelectInput
                                        id="invite-role"
                                        v-model="
                                            inviteForm.role_permission_name
                                        "
                                        :options="
                                            potentialRoles
                                        "
                                        placeholder="-- Chọn vai trò --"
                                        placement="auto"
                                        aria-label="Chọn vai trò thành viên được mời"
                                        required
                                    />

                                    <div
                                        v-if="
                                            errors.role_permission_name
                                        "
                                        class="text-danger small mt-1"
                                    >
                                        {{
                                            errors.role_permission_name
                                        }}
                                    </div>
                                </div>

                                <p
                                    class="text-muted sp-hint"
                                >
                                    <i
                                        class="fas fa-info-circle mr-1"
                                    ></i>

                                    Email phải thuộc một
                                    tài khoản đã đăng ký.
                                    Thành viên sẽ nhận lời
                                    mời trong mục thông báo.
                                </p>

                                <Btn
                                    variant="primary"
                                    icon="fas fa-bell"
                                    class="btn-block"
                                    :disabled="
                                        inviteForm.processing
                                    "
                                >
                                    Gửi lời mời
                                </Btn>
                            </form>
                        </div>
                    </div>

                    <!-- Lời mời đang chờ -->

                    <div
                        v-if="canManage"
                        class="panel"
                    >
                        <div class="panel__head">
                            <h6 class="panel__title">
                                <i
                                    class="fas fa-hourglass-half"
                                ></i>

                                Lời mời đang chờ
                            </h6>

                            <span class="count-pill">
                                {{
                                    invitations.length
                                }}
                            </span>
                        </div>

                        <div
                            class="panel__body p-0"
                        >
                            <ul
                                v-if="
                                    invitations.length
                                "
                                class="invite-list"
                            >
                                <li
                                    v-for="
                                        invitation in invitations
                                    "
                                    :key="
                                        invitation.id
                                    "
                                    class="invite-item"
                                >
                                    <div
                                        class="invite-item__info"
                                    >
                                        <span
                                            class="invite-item__email"
                                        >
                                            {{
                                                invitation.email
                                            }}
                                        </span>

                                        <span
                                            class="role-badge"
                                        >
                                            {{
                                                potentialRoles[
                                                    invitation
                                                        .role_permission_name
                                                ] ||
                                                invitation
                                                    .role_permission_name
                                            }}
                                        </span>

                                        <span
                                            class="invite-item__meta"
                                        >
                                            Mời bởi
                                            {{
                                                invitation.inviter_name
                                            }}
                                            ·
                                            {{
                                                invitation.created_at_human
                                            }}
                                        </span>
                                    </div>

                                    <Btn
                                        type="button"
                                        variant="danger"
                                        outline
                                        icon="fas fa-times"
                                        class="btn-sm"
                                        title="Hủy lời mời"
                                        @click="
                                            cancelInvite(
                                                invitation
                                            )
                                        "
                                    />
                                </li>
                            </ul>

                            <div
                                v-else
                                class="empty-state"
                            >
                                <i
                                    class="fas fa-inbox d-block mb-2"
                                ></i>

                                Không có lời mời nào
                                đang chờ.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =============================================
                     CỘT PHẢI: THÀNH VIÊN
                     ============================================= -->

                <div class="col-lg-7">
                    <div class="panel">
                        <div class="panel__head">
                            <h6 class="panel__title">
                                <i
                                    class="fas fa-users"
                                ></i>

                                Thành viên hiện tại
                            </h6>

                            <span class="count-pill">
                                {{ memberCount }}
                            </span>
                        </div>

                        <div class="panel__body">
                            <DataTable
                                class="members-table"
                                :columns="
                                    memberColumns
                                "
                                :rows="members"
                                :show-actions="
                                    canManage
                                "
                                empty-text="Chưa có thành viên nào (ngoài chủ sở hữu)."
                            >
                                <!-- Chủ sở hữu luôn đứng đầu -->

                                <template #prepend>
                                    <tr
                                        class="member-table-row"
                                    >
                                        <td
                                            data-label="Thành viên"
                                        >
                                            <div
                                                class="member-cell"
                                            >
                                                <img
                                                    :src="
                                                        avatarSrc(owner.avatar_url)
                                                    "
                                                    class="member-avatar"
                                                    alt=""
                                                />

                                                <strong>
                                                    {{
                                                        owner.name
                                                    }}
                                                </strong>
                                            </div>
                                        </td>

                                        <td
                                            data-label="Email"
                                        >
                                            {{
                                                owner.email
                                            }}
                                        </td>

                                        <td
                                            data-label="Vai trò"
                                        >
                                            <span
                                                class="owner-badge"
                                            >
                                                <i
                                                    class="fas fa-crown mr-1"
                                                ></i>

                                                Chủ sở hữu
                                            </span>
                                        </td>

                                        <td
                                            v-if="
                                                canManage
                                            "
                                            data-label="Hành động"
                                        ></td>
                                    </tr>
                                </template>

                                <!-- Tên thành viên -->

                                <template
                                    #cell-name="{
                                        row,
                                    }"
                                >
                                    <div
                                        class="member-cell"
                                    >
                                        <img
                                            :src="
                                                avatarSrc(row.avatar_url)
                                            "
                                            class="member-avatar"
                                            alt=""
                                        />

                                        <span>
                                            {{ row.name }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Vai trò thành viên -->

                                <template
                                    #cell-role="{
                                        row,
                                    }"
                                >
                                    <div
                                        class="member-role-select"
                                    >
                                        <SelectInput
                                            :model-value="
                                                row.role
                                            "
                                            :options="
                                                potentialRoles
                                            "
                                            :disabled="
                                                !canManage
                                            "
                                            class="form-control-sm member-role-input"
                                            placement="auto"
                                            aria-label="Chọn vai trò thành viên"
                                            @update:model-value="
                                                (value) =>
                                                    changeRole(
                                                        row,
                                                        value
                                                    )
                                            "
                                        />
                                    </div>
                                </template>

                                <!-- Hành động -->

                                <template
                                    #actions="{
                                        row,
                                    }"
                                >
                                    <Btn
                                        type="button"
                                        variant="danger"
                                        outline
                                        icon="fas fa-user-times"
                                        class="btn-sm"
                                        title="Xóa thành viên"
                                        @click="
                                            removeMember(
                                                row
                                            )
                                        "
                                    />
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
    width: 100%;
    max-width: 1120px;
    min-width: 0;
    margin: 0 auto;
}

/* =========================================================
   HEADER
   ========================================================= */

.sp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 16px;
    flex-wrap: wrap;

    padding-bottom: 16px;
    margin-bottom: 20px;

    border-bottom: 1px solid
        var(--app-border);
}

.sp-header__main {
    display: flex;
    align-items: center;

    min-width: 0;

    gap: 16px;
}

.sp-back {
    flex: 0 0 auto;

    white-space: nowrap;
}

.sp-heading {
    min-width: 0;
}

.sp-eyebrow {
    color: var(--app-accent);

    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;

    text-transform: uppercase;
}

.sp-title {
    margin: 2px 0 0;

    overflow: hidden;

    color: var(--app-text);

    font-size: 1.4rem;
    font-weight: 700;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sp-count {
    flex: 0 0 auto;

    padding: 6px 14px;

    color: var(--app-text-muted);

    font-size: 0.82rem;
    font-weight: 600;

    border-radius: 20px;

    background: rgba(
        127,
        127,
        127,
        0.1
    );
}

/* =========================================================
   GRID
   ========================================================= */

.settings-grid {
    align-items: flex-start;
    margin-right: 0;
    margin-left: 0;
}

/* =========================================================
   PANEL
   ========================================================= */

.panel {
    min-width: 0;

    overflow: hidden;

    border: 1px solid
        var(--app-border);
    border-radius: 14px;

    background: var(--app-surface);

    box-shadow:
        0 2px 10px
        rgba(9, 30, 66, 0.05);
}

.panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 8px;

    padding: 14px 18px;

    border-bottom: 1px solid
        var(--app-border);

    background: rgba(
        127,
        127,
        127,
        0.04
    );
}

.panel__title {
    display: flex;
    align-items: center;

    gap: 8px;

    margin: 0;

    color: var(--app-text);

    font-size: 0.9rem;
    font-weight: 700;
}

.panel__title i {
    color: var(--app-accent);
}

.panel__body {
    min-width: 0;

    padding: 18px;
}

.count-pill {
    min-width: 24px;

    padding: 2px 9px;

    color: #fff;

    font-size: 0.75rem;
    font-weight: 700;
    text-align: center;

    border-radius: 20px;

    background: var(--app-accent);
}

/* =========================================================
   FORM MỜI
   ========================================================= */

.fld-label {
    display: block;

    margin-bottom: 6px;

    color: var(--app-text-muted);

    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.4px;

    text-transform: uppercase;
}

.sp-hint {
    margin: 0 0 14px;

    font-size: 0.76rem;
    line-height: 1.5;
}

/* =========================================================
   LỜI MỜI ĐANG CHỜ
   ========================================================= */

.invite-list {
    margin: 0;
    padding: 0;

    list-style: none;
}

.invite-item {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    padding: 12px 18px;

    border-top: 1px solid
        var(--app-border);
}

.invite-item:first-child {
    border-top: 0;
}

.invite-item__info {
    display: flex;
    flex-direction: column;

    min-width: 0;

    gap: 3px;
}

.invite-item__email {
    color: var(--app-text);

    font-weight: 600;

    word-break: break-word;
}

.invite-item__meta {
    color: var(--app-text-muted);

    font-size: 0.72rem;
}

.role-badge {
    align-self: flex-start;

    padding: 2px 10px;

    color: var(--app-accent);

    font-size: 0.68rem;
    font-weight: 600;

    border-radius: 20px;

    background: rgba(
        102,
        51,
        0,
        0.08
    );
}

.empty-state {
    padding: 28px 18px;

    color: var(--app-text-muted);

    font-size: 0.85rem;
    text-align: center;
}

.empty-state i {
    font-size: 1.6rem;

    opacity: 0.5;
}

/* =========================================================
   THÀNH VIÊN
   ========================================================= */

.member-cell {
    display: flex;
    align-items: center;

    min-width: 0;

    gap: 10px;
}

.member-cell span,
.member-cell strong {
    min-width: 0;

    overflow: hidden;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.member-avatar {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    border-radius: 50%;

    object-fit: cover;
}

.owner-badge {
    display: inline-flex;
    align-items: center;

    padding: 3px 12px;

    color: #b45309;

    font-size: 0.72rem;
    font-weight: 600;

    border-radius: 20px;

    background: #fef3c7;
}

/* =========================================================
   SELECT VAI TRÒ TRONG BẢNG
   ========================================================= */

/*
 * Wrapper chỉ chiếm đúng 32px chiều cao.
 * Dropdown phải được Teleport ra body trong ResponsiveSelect.
 */
.member-role-select {
    position: relative;

    width: 100%;
    height: 32px;
    min-width: 0;
    min-height: 32px;
    max-height: 32px;

    overflow: visible;
}

/*
 * Root SelectInput và ResponsiveSelect luôn giữ cùng chiều cao.
 */
.member-role-select
    :deep(.responsive-select) {
    width: 100%;
    height: 32px;
    min-width: 0;
    min-height: 32px;
    max-height: 32px;
}

/*
 * Native select trên desktop và trigger trên mobile
 * đều giữ nguyên 32px.
 */
.member-role-select
    :deep(.responsive-select__native),
.member-role-select
    :deep(.responsive-select__trigger) {
    width: 100%;
    height: 32px !important;
    min-width: 0;
    min-height: 32px !important;
    max-height: 32px !important;

    padding-top: 0.25rem;
    padding-bottom: 0.25rem;

    line-height: 1.5;
}

/*
 * Ngăn style trạng thái mở làm thay đổi kích thước root.
 */
.member-role-select
    :deep(.responsive-select.is-open) {
    height: 32px;
    min-height: 32px;
    max-height: 32px;
}

/*
 * Menu đã Teleport không nằm bên trong wrapper.
 * Rule này là fallback nếu đang dùng bản cũ chưa Teleport.
 */
.member-role-select
    :deep(.responsive-select__menu) {
    position: absolute;
    z-index: 9999;
}

/* =========================================================
   DATATABLE DESKTOP
   ========================================================= */

@media (min-width: 768px) {
    .members-table
        :deep(tbody tr) {
        height: 56px;
        min-height: 56px;
        max-height: 56px;
    }

    .members-table
        :deep(tbody td) {
        height: 56px;
        min-height: 56px;
        max-height: 56px;

        vertical-align: middle;
    }

    /*
     * Ô vai trò không được thay đổi chiều cao
     * khi SelectInput mở.
     */
    .members-table
        :deep(
            tbody td[data-label='Vai trò']
        ) {
        position: relative;

        height: 56px;
        min-height: 56px;
        max-height: 56px;

        overflow: visible;
    }
}

/* =========================================================
   DARK MODE
   ========================================================= */

[data-theme='dark']
    .role-badge {
    color: var(--app-accent-2);

    background: rgba(
        165,
        118,
        63,
        0.15
    );
}

[data-theme='dark']
    .owner-badge {
    color: #fbbf24;

    background: rgba(
        251,
        191,
        36,
        0.15
    );
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 767.98px) {
    /*
     * Khi các cột xếp chồng, margin của row đã được triệt tiêu
     * nên cũng cần bỏ gutter của col để panel thẳng hàng với header.
     */
    .settings-grid > [class*='col-'] {
        padding-right: 0;
        padding-left: 0;
    }

    /*
     * Trên mobile DataTable thường đổi mỗi tr thành card.
     * Không cố định toàn bộ card ở 56px vì còn nhiều field.
     * Chỉ giữ chiều cao của SelectInput.
     */
    .member-role-select {
        height: 38px;
        min-height: 38px;
        max-height: 38px;
    }

    .member-role-select
        :deep(.responsive-select) {
        height: 38px;
        min-height: 38px;
        max-height: 38px;
    }

    .member-role-select
        :deep(
            .responsive-select__trigger
        ) {
        height: 38px !important;
        min-height: 38px !important;
        max-height: 38px !important;
    }
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
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        min-width: 42px;
        flex: 0 0 42px;

        border: 1px solid var(--app-border);
        border-radius: 50%;
        color: var(--app-text);
        text-decoration: none;
        background: var(--app-surface);
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
