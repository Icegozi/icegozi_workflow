<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import Btn from '@/Components/Btn.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import { renderMarkdown } from '@/composables/useMarkdown';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    comments: { type: Array, default: () => [] },
});

// Trang cha nghe sự kiện này để tải lại task sau khi thêm/xoá bình luận.
const emit = defineEmits(['updated']);

const avatar = (email, size = 30) => `https://i.pravatar.cc/${size}?u=${encodeURIComponent(email || 'x')}`;

// ---- Thành viên bảng (dùng cho gợi ý @mention) ----
const boardMembers = ref([]);
onMounted(async () => {
    try {
        const { data } = await axios.get(route('boards.assignedUsers', props.boardId));
        boardMembers.value = data.users || [];
    } catch (e) { /* ignore */ }
});

// ---- Soạn bình luận + @mention ----
const newComment = ref('');
const sending = ref(false);   // chống gửi bình luận trùng (Ctrl+Enter + click)
const mentionOpen = ref(false);
const mentionQuery = ref('');
const selectedMentions = ref([]);   // [{id, name}]
const mentionAt = ref(-1);          // vị trí ký tự '@' đang kích hoạt gợi ý

const mentionMatches = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return boardMembers.value
        .filter((m) => m.name.toLowerCase().includes(q))
        .slice(0, 6);
});

const onCommentInput = () => {
    const text = newComment.value;
    const at = text.lastIndexOf('@');
    // Chỉ mở gợi ý khi '@' đứng đầu hoặc sau khoảng trắng (bỏ qua '@' trong email
    // như bob@corp.com) và phần sau '@' chưa có khoảng trắng (đang gõ token tên).
    const triggered = at >= 0
        && (at === 0 || /\s/.test(text[at - 1]))
        && !/\s/.test(text.slice(at + 1));
    if (triggered) {
        mentionAt.value = at;
        mentionQuery.value = text.slice(at + 1);
        mentionOpen.value = true;
    } else {
        mentionOpen.value = false;
        mentionAt.value = -1;
    }
};
// MarkdownEditor phát v-model -> theo dõi để bật gợi ý @mention
watch(newComment, onCommentInput);

const pickMention = (member) => {
    const text = newComment.value;
    // Dùng đúng vị trí '@' đã kích hoạt gợi ý, không dò lại lastIndexOf('@').
    const at = mentionAt.value;
    if (at < 0) return;
    newComment.value = text.slice(0, at) + '@' + member.name + ' ';
    if (!selectedMentions.value.some((m) => m.id === member.id)) {
        selectedMentions.value.push({ id: member.id, name: member.name });
    }
    mentionOpen.value = false;
    mentionAt.value = -1;
};

const addComment = async () => {
    if (sending.value) return;   // đang gửi -> bỏ qua lần gọi trùng
    const content = newComment.value.trim();
    if (!content) return;
    // Chỉ gửi mention còn xuất hiện dạng "@Tên" trong nội dung
    const mentions = selectedMentions.value
        .filter((m) => content.includes('@' + m.name))
        .map((m) => m.id);
    sending.value = true;
    try {
        await axios.post(route('comments.store', props.taskId), { content, mentions });
        newComment.value = '';
        selectedMentions.value = [];
        mentionOpen.value = false;
        emit('updated');
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể thêm bình luận.');
    } finally {
        sending.value = false;
    }
};

const deleteComment = async (c) => {
    if (!confirm('Xoá bình luận?')) return;
    try {
        await axios.delete(`/tasks/${props.taskId}/comments/${c.id}`);
        emit('updated');
    } catch (e) {
        alert(e.response?.data?.message || 'Không thể xoá bình luận.');
    }
};
</script>

<template>
    <div>
        <h6 class="sect"><i class="fas fa-comments"></i>Bình luận</h6>
        <div class="comment-compose position-relative mb-4">
            <MarkdownEditor v-model="newComment" :min-rows="2" :task-id="taskId"
                placeholder="Viết bình luận... gõ @ để nhắc thành viên"
                @submit="addComment" />
            <div class="d-flex justify-content-end mt-2">
                <Btn type="button" variant="black" icon="fas fa-paper-plane"
                    class="btn-sm" :disabled="sending" @click="addComment">
                    {{ sending ? 'Đang gửi...' : 'Gửi bình luận' }}
                </Btn>
            </div>
            <div v-if="mentionOpen && mentionMatches.length" class="mention-pop">
                <a v-for="m in mentionMatches" :key="m.id" href="#"
                    class="list-group-item list-group-item-action py-2 d-flex align-items-center"
                    @click.prevent="pickMention(m)">
                    <img :src="m.avatar_url || avatar(m.email, 24)" class="rounded-circle mr-2" width="22" height="22">{{ m.name }}
                </a>
            </div>
        </div>

        <div class="comment-list">
            <div v-for="c in comments" :key="c.id" class="comment">
                <img :src="c.user_avatar || avatar(c.user_name, 40)" class="rounded-circle comment__avatar"
                    width="34" height="34">
                <div class="comment__body">
                    <div class="comment__head">
                        <strong>{{ c.user_name }}</strong>
                        <small class="text-muted">{{ c.time_ago }}</small>
                        <button class="item-x ml-auto" @click="deleteComment(c)" title="Xoá">&times;</button>
                    </div>
                    <div class="comment__content md-content" v-html="renderMarkdown(c.content)"></div>
                </div>
            </div>
            <div v-if="!comments || !comments.length" class="text-muted small text-center py-3">
                Chưa có bình luận nào.
            </div>
        </div>
    </div>
</template>

<style scoped>
.sect {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--app-accent, #663300);
}

.item-x {
    border: 0;
    background: transparent;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    opacity: 0.7;
    color: var(--app-text-muted);
    font-size: 1.05rem;
}
.item-x:hover { opacity: 1; }

.mention-pop {
    position: absolute;
    z-index: 30;
    top: 46px;
    left: 10px;
    min-width: 220px;
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

.comment {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-top: 1px solid var(--app-border);
}

.comment:first-child { border-top: 0; }

.comment__avatar { flex-shrink: 0; }

.comment__body { flex: 1; min-width: 0; }

.comment__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 3px;
}

.comment__content {
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
    word-break: break-word;
}

/* Kiểu nội dung markdown đã render (dùng chung cho bình luận) */
.md-content :deep(p) { margin: 0 0 0.5rem; }
.md-content :deep(p:last-child) { margin-bottom: 0; }
.md-content :deep(ul),
.md-content :deep(ol) { margin: 0 0 0.5rem; padding-left: 1.4rem; }
.md-content :deep(blockquote) {
    margin: 0 0 0.5rem;
    padding: 3px 12px;
    border-left: 3px solid var(--app-accent);
    background: rgba(102, 51, 0, 0.06);
    border-radius: 0 6px 6px 0;
}
.md-content :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82em;
    background: rgba(127, 127, 127, 0.15);
    padding: 1px 5px;
    border-radius: 4px;
}
.md-content :deep(pre) {
    padding: 10px 12px;
    background: rgba(127, 127, 127, 0.12);
    border-radius: 8px;
    overflow-x: auto;
}
.md-content :deep(pre code) { background: none; padding: 0; }
.md-content :deep(a) { color: var(--app-accent); }
[data-theme="dark"] .md-content :deep(a) { color: var(--app-accent-2); }
[data-theme="dark"] .md-content :deep(blockquote) {
    background: rgba(165, 118, 63, 0.12);
}
</style>
