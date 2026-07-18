<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Btn from '@/Components/Btn.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import { renderMarkdown } from '@/composables/useMarkdown';
import { avatarSrc } from '@/composables/useSocialLinks';
import { showAppAlert, showAppConfirm } from '@/composables/useAppAlert';

const props = defineProps({
    taskId: { type: Number, required: true },
    boardId: { type: Number, required: true },
    comments: { type: Array, default: () => [] },
});

// Trang cha nghe sự kiện này để tải lại task sau khi thêm/xoá bình luận.
const emit = defineEmits(['updated']);

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
const commentEditor = ref(null);
const commentCompose = ref(null);
const isUploading = computed(() => commentEditor.value?.isUploading ?? false);
const mentionOpen = ref(false);
const mentionQuery = ref('');
const selectedMentions = ref([]);   // [{id, name}]
const mentionAt = ref(-1);          // vị trí ký tự '@' đang kích hoạt gợi ý
const mentionEnd = ref(-1);         // vị trí sau token mention đang kích hoạt
const mentionPosition = ref(null);
const activeMentionIndex = ref(0);
const keyboardNavigatingMentions = ref(false);
const ignoreNextMentionKeyup = ref(false);
const pickingMention = ref(false);
const deletingCommentId = ref(null);

const mentionMatches = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return boardMembers.value
        .filter((m) => m.name.toLowerCase().includes(q))
        .slice(0, 6);
});

watch(mentionMatches, (matches) => {
    activeMentionIndex.value = Math.min(activeMentionIndex.value, Math.max(0, matches.length - 1));
});

const onCommentInput = () => {
    if (pickingMention.value) return;

    const text = newComment.value;
    const caret = commentEditor.value?.getSelectionStart?.() ?? text.length;
    const textBeforeCaret = text.slice(0, caret);
    const at = textBeforeCaret.lastIndexOf('@');
    // Chỉ mở gợi ý khi '@' đứng đầu hoặc sau khoảng trắng (bỏ qua '@' trong email
    // như bob@corp.com) và phần sau '@' chưa có khoảng trắng (đang gõ token tên).
    const triggered = at >= 0
        && (at === 0 || /\s/.test(text[at - 1]))
        && !/\s/.test(textBeforeCaret.slice(at + 1));
    if (triggered) {
        const query = textBeforeCaret.slice(at + 1);
        const mentionChanged = !mentionOpen.value
            || mentionAt.value !== at
            || mentionQuery.value !== query;
        mentionAt.value = at;
        mentionEnd.value = (() => {
            const whitespace = text.slice(at + 1).search(/\s/);
            return whitespace === -1 ? text.length : at + 1 + whitespace;
        })();
        mentionQuery.value = query;
        mentionOpen.value = true;
        // keyup/cursor có thể chạy ngay sau ArrowUp/ArrowDown. Chỉ reset khi
        // token thật sự đổi, nếu không highlight vừa chọn sẽ nhảy về mục đầu.
        if (mentionChanged) {
            activeMentionIndex.value = 0;
            keyboardNavigatingMentions.value = false;
        }
        updateMentionPosition();
    } else {
        mentionOpen.value = false;
        mentionAt.value = -1;
        mentionEnd.value = -1;
        mentionPosition.value = null;
    }
};

const handleEditorKeydown = (event) => {
    if (!mentionOpen.value || !mentionMatches.value.length) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        keyboardNavigatingMentions.value = true;
        activeMentionIndex.value = (activeMentionIndex.value + 1) % mentionMatches.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        keyboardNavigatingMentions.value = true;
        activeMentionIndex.value = (activeMentionIndex.value - 1 + mentionMatches.value.length) % mentionMatches.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        pickMention(mentionMatches.value[activeMentionIndex.value], true);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        mentionOpen.value = false;
        mentionAt.value = -1;
        mentionPosition.value = null;
    }
};

const updateMentionPosition = () => {
    nextTick(() => {
        const caret = commentEditor.value?.getSelectionStart?.() ?? mentionAt.value + 1;
        const anchor = commentEditor.value?.getCaretCoordinates?.(caret);
        const compose = commentCompose.value?.getBoundingClientRect();
        if (!anchor || !compose) return;
        mentionPosition.value = {
            left: Math.max(0, anchor.left - compose.left),
            top: anchor.top - compose.top + 4,
        };
    });
};

const onEditorCursor = ({ source } = {}) => {
    // Enter chọn mention cập nhật v-model trước khi textarea nhận caret mới.
    // Bỏ qua keyup tương ứng để không mở lại popup theo caret của text cũ.
    if (source === 'keyup' && ignoreNextMentionKeyup.value) {
        ignoreNextMentionKeyup.value = false;
        return;
    }

    onCommentInput();
};

const mentionStyle = computed(() => mentionPosition.value
    ? { left: `${mentionPosition.value.left}px`, top: `${mentionPosition.value.top}px` }
    : { left: '0', top: '0' });
// MarkdownEditor phát v-model -> theo dõi để bật gợi ý @mention
watch(newComment, onCommentInput);

const pickMention = (member, fromKeyboard = false) => {
    const text = newComment.value;
    // Dùng đúng vị trí '@' đã kích hoạt gợi ý, không dò lại lastIndexOf('@').
    const at = mentionAt.value;
    if (at < 0) return;
    const end = mentionEnd.value;
    // Đóng trước khi đổi nội dung để tránh một frame popup nhảy theo caret cũ.
    mentionOpen.value = false;
    mentionAt.value = -1;
    mentionEnd.value = -1;
    keyboardNavigatingMentions.value = false;
    pickingMention.value = true;
    if (fromKeyboard) {
        ignoreNextMentionKeyup.value = true;
    }
    newComment.value = text.slice(0, at) + '@' + member.name + ' ' + text.slice(end);
    nextTick(() => { pickingMention.value = false; });
    if (!selectedMentions.value.some((m) => m.id === member.id)) {
        selectedMentions.value.push({ id: member.id, name: member.name });
    }
    mentionPosition.value = null;
};

const addComment = async () => {
    if (sending.value || isUploading.value) return;
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
        showAppAlert(e.response?.data?.message || 'Không thể thêm bình luận.');
    } finally {
        sending.value = false;
    }
};

const handleAvatarError = (event) => {
    event.target.onerror = null;
    event.target.src = avatarSrc(null);
};

const deleteComment = async (c) => {
    if (deletingCommentId.value === c.id) return;
    if (!await showAppConfirm('Xoá bình luận?', 'danger')) return;
    if (deletingCommentId.value === c.id) return;
    deletingCommentId.value = c.id;
    try {
        await axios.delete(`/tasks/${props.taskId}/comments/${c.id}`);
        emit('updated');
    } catch (e) {
        showAppAlert(e.response?.data?.message || 'Không thể xoá bình luận.');
    } finally {
        deletingCommentId.value = null;
    }
};
</script>

<template>
    <div>
        <h6 class="sect"><i class="fas fa-comments"></i>Bình luận</h6>
        <div ref="commentCompose" class="comment-compose position-relative mb-4">
            <MarkdownEditor ref="commentEditor" v-model="newComment" :min-rows="2" :task-id="taskId"
                placeholder="Viết bình luận... gõ @ để nhắc thành viên"
                @submit="addComment" @keydown="handleEditorKeydown" @cursor="onEditorCursor" />
            <div v-if="mentionOpen && mentionMatches.length" class="mention-pop"
                :class="{ 'is-keyboard-nav': keyboardNavigatingMentions }" :style="mentionStyle"
                @mousemove="keyboardNavigatingMentions = false">
                <a v-for="m in mentionMatches" :key="m.id" href="#"
                    class="mention-pop__option" :class="{ 'is-active': mentionMatches[activeMentionIndex]?.id === m.id }"
                    @click.prevent="pickMention(m)">
                    <img :src="avatarSrc(m.avatar_url)" class="rounded-circle mention-pop__avatar" width="32" height="32"
                        :alt="m.name" @error="handleAvatarError">
                    <span class="mention-pop__person">
                        <strong>{{ m.name }}</strong>
                        <small>{{ m.email }}</small>
                    </span>
                </a>
            </div>
            <div class="d-flex justify-content-end mt-2">
                <Btn type="button" variant="black" icon="fas fa-paper-plane"
                    class="btn-sm" :disabled="sending || isUploading || !newComment.trim()" @click="addComment">
                    {{ isUploading ? 'Đang tải tệp...' : (sending ? 'Đang gửi...' : 'Gửi bình luận') }}
                </Btn>
            </div>
        </div>

        <div class="comment-list">
            <div v-for="c in comments" :key="c.id" class="comment">
                <img :src="avatarSrc(c.user_avatar)" class="rounded-circle comment__avatar"
                    width="34" height="34" :alt="`Ảnh đại diện của ${c.user_name}`" @error="handleAvatarError">
                <div class="comment__body">
                    <div class="comment__head">
                        <strong>{{ c.user_name }}</strong>
                        <small class="text-muted">{{ c.time_ago }}</small>
                        <button v-if="c.can_delete" class="item-x ml-auto" :disabled="deletingCommentId === c.id"
                            @click="deleteComment(c)" title="Xoá bình luận"
                            aria-label="Xoá bình luận">&times;</button>
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
    width: max-content;
    max-width: min(320px, calc(100% - 12px));
    max-height: 260px;
    overflow-y: auto;
    margin-top: 8px;
    padding: 6px;
    background: var(--app-surface);
    background: color-mix(in srgb, var(--app-surface) 96%, transparent);
    border: 1px solid var(--app-border);
    border-color: color-mix(in srgb, var(--app-border) 85%, var(--app-accent));
    border-radius: 14px;
    box-shadow: 0 14px 34px rgba(32, 20, 10, 0.2);
    backdrop-filter: blur(10px);
}

.mention-pop__option {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px;
    color: var(--app-text);
    border-radius: 9px;
    text-decoration: none;
    transition: background 0.15s ease, transform 0.15s ease;
}

.mention-pop__option:focus,
.mention-pop__option.is-active {
    color: var(--app-text);
    background: color-mix(in srgb, var(--app-accent) 10%, transparent);
    outline: 0;
    transform: translateX(2px);
}

.mention-pop:not(.is-keyboard-nav) .mention-pop__option:hover {
    color: var(--app-text);
    background: color-mix(in srgb, var(--app-accent) 10%, transparent);
    outline: 0;
    transform: translateX(2px);
}

.mention-pop__avatar {
    flex: 0 0 32px;
    object-fit: cover;
}

.mention-pop__person {
    display: grid;
    min-width: 0;
    line-height: 1.25;
}

.mention-pop__person strong,
.mention-pop__person small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mention-pop__person strong { font-size: 0.85rem; }
.mention-pop__person small { color: var(--app-text-muted); font-size: 0.73rem; }

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

@media (max-width: 575.98px) {
    .mention-pop {
        max-height: 220px;
        max-width: calc(100% - 8px);
    }

    .comment-compose {
        margin-bottom: 18px !important;
    }

    .comment-compose > .d-flex {
        margin-top: 10px !important;
    }

    .comment-compose :deep(.btn) {
        width: 100%;
        min-height: 40px;
        justify-content: center;
    }

    .comment {
        gap: 8px;
        padding: 10px;
        margin-bottom: 8px;
        background: var(--app-surface);
        border: 1px solid var(--app-border);
        border-radius: 12px;
    }

    .comment:first-child {
        border-top: 1px solid var(--app-border);
    }

    .comment__avatar {
        width: 30px;
        height: 30px;
    }

    .comment__head {
        align-items: center;
        flex-wrap: wrap;
        gap: 3px 7px;
        margin-bottom: 5px;
    }

    .comment__head strong { font-size: 0.86rem; }
    .comment__head small { font-size: 0.72rem; }
    .comment__content { font-size: 0.86rem; line-height: 1.55; }
    .item-x { padding: 3px 2px; font-size: 1.2rem; }

    .mention-pop__option { padding: 7px; }
    .mention-pop__person strong { font-size: 0.82rem; }
}
</style>
