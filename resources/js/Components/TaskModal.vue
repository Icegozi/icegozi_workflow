<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'updated', 'deleted']);

const loading = ref(true);
const task = ref(null);
const title = ref('');
const description = ref('');
const dueDate = ref('');
const newComment = ref('');
const newChecklistItem = ref('');
const boardMembers = ref([]);
const showAssigneePicker = ref(false);

const loadTask = async () => {
    const { data } = await axios.get(route('tasks.show', props.taskId));
    task.value = data.task;
    title.value = data.task.title;
    description.value = data.task.description || '';
    dueDate.value = data.task.due_date || '';
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
    if (props.canManage) loadMembers();
});

// Lưu thay đổi cơ bản (title/description/due_date) — title bắt buộc theo TaskRequest
const saveTask = async () => {
    try {
        const { data } = await axios.put(route('tasks.update', props.taskId), {
            title: title.value,
            description: description.value,
            due_date: dueDate.value || null,
        });
        emit('updated', {
            id: props.taskId,
            title: data.task.title,
            due_date: data.task.due_date,
            formatted_due_date: data.task.formatted_due_date,
        });
        await loadTask();
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể lưu thay đổi.');
    }
};

const deleteTask = async () => {
    if (!confirm('Xoá công việc này?')) return;
    try {
        await axios.delete(route('tasks.destroy', props.taskId));
        emit('deleted', props.taskId);
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể xoá công việc.');
    }
};

// ---- Bình luận ----
const addComment = async () => {
    const content = newComment.value.trim();
    if (!content) return;
    try {
        await axios.post(route('comments.store', props.taskId), { content });
        newComment.value = '';
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

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;
</script>

<template>
    <div class="modal-backdrop-custom" @click.self="emit('close')">
        <div class="task-modal card shadow">
            <div class="modal-header bg-dark text-light">
                <h5 class="modal-title mb-0">Chi tiết công việc</h5>
                <button type="button" class="close text-danger" @click="emit('close')"><span>&times;</span></button>
            </div>

            <div class="modal-body" style="max-height:80vh; overflow-y:auto;">
                <div v-if="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                <div v-else class="row">
                    <!-- Cột trái -->
                    <div class="col-lg-8">
                        <p class="text-muted mb-1">Trong danh sách: <strong>{{ task.column_name }}</strong></p>
                        <input type="text" class="form-control form-control-lg font-weight-bold border-0 pl-0 mb-3"
                            v-model="title" :readonly="!canEdit" placeholder="Tiêu đề...">

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

                        <h6 class="font-weight-bold"><i class="fas fa-align-left mr-2"></i>MÔ TẢ</h6>
                        <textarea class="form-control mb-3" rows="4" v-model="description" :readonly="!canEdit"
                            placeholder="Thêm mô tả chi tiết hơn..."></textarea>

                        <h6 class="font-weight-bold"><i class="fas fa-tasks mr-2"></i>CHECKLIST</h6>
                        <div class="mb-3">
                            <div v-for="item in task.checklists" :key="item.id" class="d-flex align-items-center mb-1">
                                <input type="checkbox" :checked="item.is_done" :disabled="!canEdit" @change="toggleChecklist(item)" class="mr-2">
                                <span :class="{ 'text-muted text-decoration-line-through': item.is_done }" style="flex:1;">{{ item.title }}</span>
                                <button v-if="canEdit" class="btn btn-sm btn-link text-danger p-0" @click="deleteChecklist(item)">&times;</button>
                            </div>
                            <div v-if="canEdit" class="input-group input-group-sm mt-2">
                                <input type="text" class="form-control" v-model="newChecklistItem" placeholder="Thêm mục mới..." @keyup.enter="addChecklist">
                                <div class="input-group-append"><button class="btn btn-success" @click="addChecklist">Thêm</button></div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-comments mr-2"></i>BÌNH LUẬN</h6>
                        <div class="input-group mb-3">
                            <textarea class="form-control" rows="1" v-model="newComment" placeholder="Viết bình luận..."></textarea>
                            <div class="input-group-append"><button class="btn btn-outline-primary" @click="addComment"><i class="fas fa-paper-plane"></i></button></div>
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
                            <label class="small">Ngày hết hạn</label>
                            <input type="date" class="form-control form-control-sm" v-model="dueDate" :disabled="!canEdit">
                        </div>

                        <div v-if="canEdit" class="mb-3">
                            <button class="btn btn-dark btn-block btn-sm" @click="saveTask"><i class="fas fa-save mr-1"></i>Lưu thay đổi</button>
                            <button v-if="canManage" class="btn btn-danger btn-block btn-sm" @click="deleteTask"><i class="fas fa-trash-alt mr-1"></i>Xoá công việc</button>
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
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    display: flex; align-items: flex-start; justify-content: center; z-index: 1060; padding: 30px 10px;
}
.task-modal { width: 100%; max-width: 1000px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: .75rem 1rem; }
.modal-header .close { background: none; border: none; font-size: 1.5rem; line-height: 1; }
</style>
