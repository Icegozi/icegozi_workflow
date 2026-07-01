<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    boardName: { type: String, default: '' },
    code: { type: String, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    statuses: { type: Array, default: () => [] },
    boardLabels: { type: Array, default: () => [] },
});

const loading = ref(true);
const task = ref(null);
const title = ref('');
const description = ref('');
const dueDate = ref('');
const priority = ref('normal');
const statusId = ref(null);
const newComment = ref('');
const newChecklistItem = ref('');
const boardMembers = ref([]);
const showAssigneePicker = ref(false);
const saving = ref(false);

// ---- Nhãn ----
const labels = ref([...props.boardLabels]);   // bảng nhãn của board (có thể thêm mới)
const showLabelPanel = ref(false);
const newLabelName = ref('');
const LABEL_COLORS = ['#e5484d', '#f76808', '#ffb224', '#18794e', '#006adc', '#8e4ec6', '#7a869a'];
const newLabelColor = ref(LABEL_COLORS[0]);

const PRIORITIES = [
    { value: 'low', label: 'Thấp' },
    { value: 'normal', label: 'Bình thường' },
    { value: 'high', label: 'Cao' },
    { value: 'urgent', label: 'Khẩn cấp' },
];

const loadTask = async () => {
    const { data } = await axios.get(route('tasks.show', props.taskId));
    task.value = data.task;
    title.value = data.task.title;
    description.value = data.task.description || '';
    dueDate.value = data.task.due_date || '';
    priority.value = data.task.priority || 'normal';
    statusId.value = data.task.status?.id ?? null;
    loading.value = false;
};

const loadMembers = async () => {
    try {
        const { data } = await axios.get(route('boards.assignedUsers', props.boardId));
        boardMembers.value = data.users || [];
    } catch (e) { /* ignore */ }
};

onMounted(async () => {
    await loadTask();
    loadMembers();   // cần cho cả gợi ý @mention, không chỉ khi canManage
});

// ---- @mention trong bình luận ----
const mentionOpen = ref(false);
const mentionQuery = ref('');
const selectedMentions = ref([]);   // [{id, name}]
const mentionMatches = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return boardMembers.value
        .filter((m) => m.name.toLowerCase().includes(q))
        .slice(0, 6);
});

const onCommentInput = () => {
    const text = newComment.value;
    const at = text.lastIndexOf('@');
    // Đang gõ token @... (sau @ không có khoảng trắng) thì mở gợi ý
    if (at >= 0 && !/\s/.test(text.slice(at + 1))) {
        mentionQuery.value = text.slice(at + 1);
        mentionOpen.value = true;
    } else {
        mentionOpen.value = false;
    }
};

const pickMention = (member) => {
    const text = newComment.value;
    const at = text.lastIndexOf('@');
    newComment.value = text.slice(0, at) + '@' + member.name + ' ';
    if (!selectedMentions.value.some((m) => m.id === member.id)) {
        selectedMentions.value.push({ id: member.id, name: member.name });
    }
    mentionOpen.value = false;
};

const backToBoard = () => router.visit(route('boards.show', props.boardId));

const saveTask = async () => {
    saving.value = true;
    try {
        await axios.put(route('tasks.update', props.taskId), {
            title: title.value,
            description: description.value,
            due_date: dueDate.value || null,
            priority: priority.value,
            status_id: statusId.value,
        });
        await loadTask();
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể lưu thay đổi.');
    } finally {
        saving.value = false;
    }
};

const deleteTask = async () => {
    if (!confirm('Xoá công việc này?')) return;
    try {
        await axios.delete(route('tasks.destroy', props.taskId));
        backToBoard();
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể xoá công việc.');
    }
};

// ---- Bình luận ----
const addComment = async () => {
    const content = newComment.value.trim();
    if (!content) return;
    // Chỉ gửi mention còn xuất hiện dạng "@Tên" trong nội dung
    const mentions = selectedMentions.value
        .filter((m) => content.includes('@' + m.name))
        .map((m) => m.id);
    try {
        await axios.post(route('comments.store', props.taskId), { content, mentions });
        newComment.value = '';
        selectedMentions.value = [];
        mentionOpen.value = false;
        await loadTask();
    } catch (e) { alert(e.response?.data?.message || 'Không thể thêm bình luận.'); }
};
const deleteComment = async (c) => {
    if (!confirm('Xoá bình luận?')) return;
    try {
        await axios.delete(`/tasks/${props.taskId}/comments/${c.id}`);
        await loadTask();
    } catch (e) { alert(e.response?.data?.message || 'Không thể xoá bình luận.'); }
};

// ---- Checklist ----
const addChecklist = async () => {
    const t = newChecklistItem.value.trim();
    if (!t) return;
    try {
        await axios.post(route('checklists.store', props.taskId), { title: t });
        newChecklistItem.value = '';
        await loadTask();
    } catch (e) { alert(e.response?.data?.message || 'Không thể thêm mục.'); }
};
const toggleChecklist = async (item) => {
    try {
        await axios.put(route('checklists.update', item.id), { is_done: !item.is_done });
        await loadTask();
    } catch (e) { alert('Không thể cập nhật.'); }
};
const deleteChecklist = async (item) => {
    try {
        await axios.delete(route('checklists.destroy', item.id));
        await loadTask();
    } catch (e) { alert('Không thể xoá mục.'); }
};

// ---- Người phụ trách ----
const addAssignee = async (user) => {
    try {
        await axios.post(route('tasks.assignees.store', props.taskId), { user_id: user.id });
        showAssigneePicker.value = false;
        await loadTask();
    } catch (e) { alert(e.response?.data?.message || 'Không thể thêm người phụ trách.'); }
};
const removeAssignee = async (user) => {
    try {
        await axios.delete(route('tasks.assignees.destroy', [props.taskId, user.id]));
        await loadTask();
    } catch (e) { alert('Không thể gỡ người phụ trách.'); }
};

const hasLabel = (id) => (task.value?.labels || []).some((l) => l.id === id);

const toggleLabel = async (label) => {
    try {
        if (hasLabel(label.id)) {
            await axios.delete(route('tasks.labels.detach', [props.taskId, label.id]));
        } else {
            await axios.post(route('tasks.labels.attach', props.taskId), { label_id: label.id });
        }
        await loadTask();
    } catch (e) { alert(e.response?.data?.message || 'Không thể cập nhật nhãn.'); }
};

const createLabel = async () => {
    const name = newLabelName.value.trim();
    try {
        const { data } = await axios.post(route('labels.store', props.boardId), {
            name: name || null,
            color: newLabelColor.value,
        });
        labels.value.push(data.label);
        newLabelName.value = '';
        await toggleLabel(data.label);   // gắn luôn vào task
    } catch (e) { alert(e.response?.data?.message || 'Không thể tạo nhãn.'); }
};

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;
</script>

<template>
    <Head :title="`${code} - Chỉnh sửa`" />
    <AuthenticatedLayout>
        <div class="edit-header p-3 mb-3 border-bottom d-flex align-items-center">
            <button class="btn btn-sm btn-light mr-3" @click="backToBoard">
                <i class="fas fa-arrow-left mr-1"></i>Quay lại bảng
            </button>
            <span class="task-code mr-2">{{ code }}</span>
            <h4 class="mb-0 text-truncate">{{ boardName }}</h4>
        </div>

        <div v-if="loading" class="text-center p-5"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
        <div v-else class="row px-3">
            <!-- Cột trái -->
            <div class="col-lg-8">
                <p class="text-muted mb-1">Trong danh sách: <strong>{{ task.column_name }}</strong></p>
                <label class="small font-weight-bold">TIÊU ĐỀ</label>
                <TextInput v-model="title" placeholder="Tiêu đề..." group-class="mb-3" />

                <h6 class="font-weight-bold"><i class="fas fa-user-friends mr-2"></i>NGƯỜI PHỤ TRÁCH</h6>
                <div class="d-flex align-items-center flex-wrap mb-3">
                    <span v-for="a in task.assignees" :key="a.id" class="mr-2 mb-1 d-inline-flex align-items-center">
                        <img :src="avatar(a.email)" class="rounded-circle mr-1" width="28" height="28" :title="a.name">
                        <button v-if="canManage" class="btn btn-sm btn-link text-danger p-0" @click="removeAssignee(a)" title="Gỡ">&times;</button>
                    </span>
                    <span v-if="!task.assignees || !task.assignees.length" class="text-muted small mr-2">Chưa có ai.</span>
                    <div v-if="canManage" class="position-relative d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary rounded-circle" @click="showAssigneePicker = !showAssigneePicker"><i class="fas fa-plus"></i></button>
                        <div v-if="showAssigneePicker" class="card shadow position-absolute" style="z-index:10; min-width:200px;">
                            <div class="list-group list-group-flush">
                                <a v-for="u in boardMembers" :key="u.id" href="#" class="list-group-item list-group-item-action py-1"
                                    @click.prevent="addAssignee(u)">
                                    <img :src="avatar(u.email, 24)" class="rounded-circle mr-2" width="22" height="22">{{ u.name }}
                                </a>
                                <span v-if="!boardMembers.length" class="list-group-item small text-muted">Không có thành viên.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="font-weight-bold"><i class="fas fa-tags mr-2"></i>NHÃN</h6>
                <div class="d-flex align-items-center flex-wrap mb-3" style="gap:6px;">
                    <span v-for="l in (task.labels || [])" :key="l.id" class="label-chip"
                        :style="{ backgroundColor: l.color }">
                        {{ l.name || 'Nhãn' }}
                        <button class="chip-x" @click="toggleLabel(l)" title="Gỡ nhãn">&times;</button>
                    </span>
                    <span v-if="!task.labels || !task.labels.length" class="text-muted small">Chưa gắn nhãn.</span>

                    <div class="position-relative d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary" @click="showLabelPanel = !showLabelPanel">
                            <i class="fas fa-plus"></i> Nhãn
                        </button>
                        <div v-if="showLabelPanel" class="card shadow position-absolute p-2" style="z-index:10; min-width:230px;">
                            <div class="small text-muted mb-1">Chọn nhãn</div>
                            <div class="d-flex flex-column mb-2" style="gap:4px; max-height:180px; overflow-y:auto;">
                                <button v-for="l in labels" :key="l.id" type="button"
                                    class="label-row" :class="{ active: hasLabel(l.id) }"
                                    :style="{ backgroundColor: l.color }" @click="toggleLabel(l)">
                                    <span>{{ l.name || 'Nhãn' }}</span>
                                    <i v-if="hasLabel(l.id)" class="fas fa-check"></i>
                                </button>
                                <span v-if="!labels.length" class="text-muted small">Chưa có nhãn nào.</span>
                            </div>
                            <div class="small text-muted mb-1">Tạo nhãn mới</div>
                            <input type="text" class="form-control form-control-sm mb-1" v-model="newLabelName" placeholder="Tên (tuỳ chọn)">
                            <div class="d-flex align-items-center mb-2" style="gap:4px;">
                                <button v-for="c in LABEL_COLORS" :key="c" type="button" class="color-dot"
                                    :class="{ sel: newLabelColor === c }" :style="{ backgroundColor: c }"
                                    @click="newLabelColor = c"></button>
                            </div>
                            <button class="btn btn-sm btn-success btn-block" @click="createLabel">Tạo &amp; gắn</button>
                        </div>
                    </div>
                </div>

                <h6 class="font-weight-bold"><i class="fas fa-align-left mr-2"></i>MÔ TẢ</h6>
                <textarea class="form-control mb-3" rows="5" v-model="description" placeholder="Thêm mô tả chi tiết hơn..."></textarea>

                <h6 class="font-weight-bold"><i class="fas fa-tasks mr-2"></i>CHECKLIST</h6>
                <div class="mb-3">
                    <div v-for="item in task.checklists" :key="item.id" class="d-flex align-items-center mb-1">
                        <Checkbox bare :model-value="item.is_done" class="mr-2" @update:model-value="toggleChecklist(item)" />
                        <span :class="{ 'text-muted text-decoration-line-through': item.is_done }" style="flex:1;">{{ item.title }}</span>
                        <button class="btn btn-sm btn-link text-danger p-0" @click="deleteChecklist(item)">&times;</button>
                    </div>
                    <div class="input-group input-group-sm mt-2">
                        <input type="text" class="form-control" v-model="newChecklistItem" placeholder="Thêm mục mới..." @keyup.enter="addChecklist">
                        <div class="input-group-append"><button class="btn btn-success" @click="addChecklist">Thêm</button></div>
                    </div>
                </div>

                <hr>
                <h6 class="font-weight-bold"><i class="fas fa-comments mr-2"></i>BÌNH LUẬN</h6>
                <div class="position-relative mb-3">
                    <div class="input-group">
                        <textarea class="form-control" rows="1" v-model="newComment" @input="onCommentInput"
                            placeholder="Viết bình luận... gõ @ để nhắc thành viên"></textarea>
                        <div class="input-group-append"><button class="btn btn-outline-primary" @click="addComment"><i class="fas fa-paper-plane"></i></button></div>
                    </div>
                    <div v-if="mentionOpen && mentionMatches.length" class="card shadow position-absolute w-100" style="z-index:20;">
                        <a v-for="m in mentionMatches" :key="m.id" href="#"
                            class="list-group-item list-group-item-action py-1 d-flex align-items-center"
                            @click.prevent="pickMention(m)">
                            <img :src="avatar(m.email, 24)" class="rounded-circle mr-2" width="22" height="22">{{ m.name }}
                        </a>
                    </div>
                </div>
                <div v-for="c in task.comments" :key="c.id" class="d-flex mb-2">
                    <img :src="c.user_avatar || avatar(c.user_name, 40)" class="rounded-circle mr-2" width="32" height="32">
                    <div class="flex-grow-1">
                        <div><strong>{{ c.user_name }}</strong> <small class="text-muted">{{ c.time_ago }}</small></div>
                        <div style="white-space:pre-wrap;">{{ c.content }}</div>
                    </div>
                    <button class="btn btn-sm btn-link text-danger p-0" @click="deleteComment(c)">&times;</button>
                </div>
            </div>

            <!-- Cột phải -->
            <div class="col-lg-4">
                <h6 class="text-muted small font-weight-bold">THÔNG TIN</h6>
                <div class="form-group">
                    <label class="small">Trạng thái</label>
                    <select class="form-control form-control-sm" v-model="statusId">
                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="small">Độ ưu tiên</label>
                    <select class="form-control form-control-sm" v-model="priority">
                        <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="small">Ngày hết hạn</label>
                    <TextInput type="date" v-model="dueDate" class="form-control-sm" group-class="" />
                </div>

                <div class="mb-3">
                    <button class="btn btn-dark btn-block btn-sm" :disabled="saving" @click="saveTask">
                        <i class="fas fa-save mr-1"></i>{{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </button>
                    <button v-if="canManage" class="btn btn-danger btn-block btn-sm" @click="deleteTask">
                        <i class="fas fa-trash-alt mr-1"></i>Xoá công việc
                    </button>
                </div>

                <h6 class="text-muted small font-weight-bold">LỊCH SỬ</h6>
                <div style="max-height:300px; overflow-y:auto;">
                    <div v-for="h in task.task_histories" :key="h.id" class="d-flex mb-2 small">
                        <img :src="h.user_avatar" class="rounded-circle mr-2" width="24" height="24">
                        <div><strong>{{ h.user_name }}</strong> {{ h.action }} <span v-if="h.note">— {{ h.note }}</span>
                            <div class="text-muted" style="font-size:.7rem;">{{ h.updated_at }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.task-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: var(--app-accent, #663300);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
}

.label-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 22px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #fff;
    line-height: 1;
}

.chip-x {
    border: 0;
    background: transparent;
    color: #fff;
    font-size: 0.9rem;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    opacity: 0.85;
}

.label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 0;
    border-radius: 6px;
    padding: 4px 10px;
    color: #fff;
    font-size: 0.78rem;
    font-weight: 600;
    opacity: 0.85;
    cursor: pointer;
}

.label-row.active {
    opacity: 1;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25) inset;
}

.color-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #ccc;
    cursor: pointer;
    padding: 0;
}

.color-dot.sel {
    box-shadow: 0 0 0 2px #333;
}
</style>
