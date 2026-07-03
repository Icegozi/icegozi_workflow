<script setup>
// Ô soạn thảo kiểu Redmine/Vgate: thanh công cụ định dạng Markdown + auto-grow
// + tab "Viết / Xem trước". An toàn XSS nhờ renderMarkdown (escape trước).
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import axios from 'axios';
import { renderMarkdown } from '@/composables/useMarkdown';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    minRows: { type: Number, default: 3 },
    autofocus: { type: Boolean, default: false },
    // Khi có taskId: bật upload tệp/ảnh (nút đính kèm + dán clipboard + kéo-thả).
    taskId: { type: Number, default: null },
});
const emit = defineEmits(['update:modelValue', 'submit']);

const ta = ref(null);
const tab = ref('write'); // 'write' | 'preview'
const rendered = computed(() => renderMarkdown(props.modelValue));

const setValue = (val, selStart, selEnd) => {
    emit('update:modelValue', val);
    nextTick(() => {
        const el = ta.value;
        if (!el) return;
        el.focus();
        if (selStart != null) el.setSelectionRange(selStart, selEnd ?? selStart);
        autoGrow();
    });
};

// Bọc phần đang chọn bằng cặp ký tự (đậm, nghiêng, code, link…)
const surround = (before, after = before, placeholder = '') => {
    const el = ta.value;
    const s = el.selectionStart;
    const e = el.selectionEnd;
    const val = props.modelValue || '';
    const sel = val.slice(s, e) || placeholder;
    const next = val.slice(0, s) + before + sel + after + val.slice(e);
    setValue(next, s + before.length, s + before.length + sel.length);
};

// Thêm tiền tố vào đầu mỗi dòng đang chọn (heading, danh sách, trích dẫn)
const linePrefix = (prefix) => {
    const el = ta.value;
    const s = el.selectionStart;
    const e = el.selectionEnd;
    const val = props.modelValue || '';
    const start = val.lastIndexOf('\n', s - 1) + 1;
    const block = val.slice(start, e) || '';
    const replaced = block.split('\n').map((l) => prefix + l).join('\n');
    const next = val.slice(0, start) + replaced + val.slice(e);
    setValue(next, start, start + replaced.length);
};

// ---- Upload tệp/ảnh (chèn Markdown tại con trỏ) ----
const fileInput = ref(null);
const uploading = ref(0);   // số tệp đang tải
let uploadSeq = 0;

// Chèn văn bản tại vị trí con trỏ (hoặc cuối nội dung nếu chưa focus).
const insertAtCursor = (text) => {
    const el = ta.value;
    const val = props.modelValue || '';
    const s = el ? el.selectionStart : val.length;
    const e = el ? el.selectionEnd : val.length;
    const next = val.slice(0, s) + text + val.slice(e);
    setValue(next, s + text.length);
};

// Thay chuỗi giữ chỗ bằng nội dung thật (khớp chuỗi thuần, thay lần đầu).
const replaceToken = (token, replacement) => {
    emit('update:modelValue', (props.modelValue || '').replace(token, replacement));
    nextTick(autoGrow);
};

const uploadFile = async (file) => {
    if (!props.taskId || !file) return;
    const seq = ++uploadSeq;
    const token = `⏳ đang tải ${file.name} #${seq}`;
    insertAtCursor(token + '\n');
    uploading.value++;
    try {
        const form = new FormData();
        form.append('file', file);
        const { data } = await axios.post(route('attachments.uploadInline', props.taskId), form);
        const md = data.is_image ? `![${data.name}](${data.url})` : `[${data.name}](${data.url})`;
        replaceToken(token, md);
    } catch (err) {
        replaceToken(token + '\n', '');
        alert(err.response?.data?.message || 'Không thể tải tệp lên.');
    } finally {
        uploading.value--;
    }
};

const uploadFiles = (files) => Array.from(files || []).forEach(uploadFile);

const onPickFiles = (e) => {
    uploadFiles(e.target.files);
    e.target.value = '';   // cho phép chọn lại cùng tệp
};

// Dán từ clipboard: nếu có tệp/ảnh -> upload, chặn dán nhị phân vào textarea.
const onPaste = (e) => {
    if (!props.taskId) return;
    const items = e.clipboardData?.items || [];
    const files = [];
    for (const it of items) {
        if (it.kind === 'file') { const f = it.getAsFile(); if (f) files.push(f); }
    }
    if (files.length) {
        e.preventDefault();
        uploadFiles(files);
    }
};

// Kéo-thả tệp vào ô soạn.
const dragOver = ref(false);
const onDrop = (e) => {
    if (!props.taskId) return;
    const files = e.dataTransfer?.files;
    if (files && files.length) {
        e.preventDefault();
        dragOver.value = false;
        uploadFiles(files);
    }
};

const TOOLS = [
    { icon: 'fas fa-bold', title: 'Đậm', fn: () => surround('**', '**', 'đậm') },
    { icon: 'fas fa-italic', title: 'Nghiêng', fn: () => surround('*', '*', 'nghiêng') },
    { icon: 'fas fa-strikethrough', title: 'Gạch ngang', fn: () => surround('~~', '~~', 'gạch') },
    { sep: true },
    { icon: 'fas fa-heading', title: 'Tiêu đề', fn: () => linePrefix('## ') },
    { icon: 'fas fa-list-ul', title: 'Danh sách', fn: () => linePrefix('- ') },
    { icon: 'fas fa-list-ol', title: 'Danh sách số', fn: () => linePrefix('1. ') },
    { icon: 'fas fa-quote-right', title: 'Trích dẫn', fn: () => linePrefix('> ') },
    { sep: true },
    { icon: 'fas fa-code', title: 'Mã nguồn', fn: () => surround('`', '`', 'code') },
    { icon: 'fas fa-link', title: 'Đường dẫn', fn: () => surround('[', '](https://)', 'văn bản') },
];

const onInput = (e) => {
    emit('update:modelValue', e.target.value);
    autoGrow();
};

// Ctrl/Cmd + Enter -> gửi
const onKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        emit('submit');
    }
};

const autoGrow = () => {
    const el = ta.value;
    if (!el) return;
    el.style.height = 'auto';
    const min = props.minRows * 24 + 16;
    el.style.height = Math.max(el.scrollHeight, min) + 'px';
};

watch(() => props.modelValue, () => nextTick(autoGrow));
onMounted(() => {
    autoGrow();
    if (props.autofocus) ta.value?.focus();
});

defineExpose({ focus: () => ta.value?.focus() });
</script>

<template>
    <div class="md-editor">
        <div class="md-head">
            <div class="md-toolbar" :class="{ 'is-disabled': tab === 'preview' }">
                <template v-for="(t, i) in TOOLS" :key="i">
                    <span v-if="t.sep" class="md-sep"></span>
                    <button v-else type="button" class="md-btn" :title="t.title"
                        :disabled="tab === 'preview'" @click="t.fn">
                        <i :class="t.icon"></i>
                    </button>
                </template>
                <template v-if="taskId">
                    <span class="md-sep"></span>
                    <button type="button" class="md-btn" title="Đính kèm tệp / ảnh"
                        :disabled="tab === 'preview' || uploading > 0" @click="fileInput?.click()">
                        <i :class="uploading > 0 ? 'fas fa-spinner fa-spin' : 'fas fa-paperclip'"></i>
                    </button>
                    <input ref="fileInput" type="file" multiple class="d-none" @change="onPickFiles">
                </template>
            </div>
            <div class="md-tabs">
                <button type="button" class="md-tab" :class="{ active: tab === 'write' }"
                    @click="tab = 'write'">Viết</button>
                <button type="button" class="md-tab" :class="{ active: tab === 'preview' }"
                    @click="tab = 'preview'">Xem trước</button>
            </div>
        </div>

        <textarea v-show="tab === 'write'" ref="ta" class="md-textarea" :class="{ 'is-dragover': dragOver }"
            :value="modelValue" :placeholder="placeholder" @input="onInput" @keydown="onKeydown"
            @paste="onPaste" @drop="onDrop"
            @dragover.prevent="taskId && (dragOver = true)" @dragleave="dragOver = false"></textarea>

        <div v-show="tab === 'preview'" class="md-preview md-body">
            <div v-if="modelValue" v-html="rendered"></div>
            <p v-else class="text-muted mb-0"><em>Chưa có nội dung để xem trước.</em></p>
        </div>

        <div class="md-hint">
            <i class="fas fa-info-circle mr-1"></i>Hỗ trợ Markdown · <kbd>Ctrl</kbd>+<kbd>Enter</kbd> để gửi<template
                v-if="taskId"> · Dán, kéo-thả hoặc bấm <i class="fas fa-paperclip"></i> để đính kèm ảnh/tệp</template>
        </div>
    </div>
</template>

<style scoped>
.md-editor {
    border: 1px solid var(--app-border);
    border-radius: 10px;
    background: var(--app-surface);
    overflow: hidden;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.md-editor:focus-within {
    border-color: var(--app-accent);
    box-shadow: 0 0 0 0.15rem rgba(102, 51, 0, 0.18);
}

.md-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-border);
    background: rgba(127, 127, 127, 0.05);
    flex-wrap: wrap;
}

.md-toolbar {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-wrap: wrap;
}

.md-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--app-text-muted);
    font-size: 0.82rem;
    cursor: pointer;
    transition: background-color 0.12s ease, color 0.12s ease;
}

.md-btn:hover {
    background: rgba(102, 51, 0, 0.1);
    color: var(--app-accent);
}

.md-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.md-sep {
    width: 1px;
    height: 18px;
    margin: 0 4px;
    background: var(--app-border);
}

.md-tabs {
    display: flex;
    gap: 2px;
}

.md-tab {
    border: none;
    background: transparent;
    color: var(--app-text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.12s ease, color 0.12s ease;
}

.md-tab:hover {
    color: var(--app-text);
}

.md-tab.active {
    background: var(--app-surface);
    color: var(--app-accent);
    box-shadow: inset 0 0 0 1px var(--app-border);
}

.md-textarea {
    display: block;
    width: 100%;
    border: none;
    outline: none;
    resize: none;
    padding: 12px 14px;
    background: transparent;
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
    min-height: 90px;
    overflow: hidden;
}

.md-textarea::placeholder {
    color: var(--app-text-muted);
}

.md-textarea.is-dragover {
    outline: 2px dashed var(--app-accent);
    outline-offset: -6px;
    background: rgba(102, 51, 0, 0.04);
}

.md-body {
    padding: 12px 14px;
    color: var(--app-text);
    font-size: 0.9rem;
    line-height: 1.6;
    min-height: 90px;
}

.md-hint {
    padding: 5px 12px;
    font-size: 0.7rem;
    color: var(--app-text-muted);
    border-top: 1px solid var(--app-border);
    background: rgba(127, 127, 127, 0.03);
}

.md-hint kbd {
    background: rgba(127, 127, 127, 0.18);
    color: var(--app-text);
    border-radius: 4px;
    padding: 0 5px;
    font-size: 0.68rem;
}

/* Kiểu hiển thị nội dung markdown đã render */
.md-preview :deep(p) { margin: 0 0 0.6rem; }
.md-preview :deep(p:last-child) { margin-bottom: 0; }
.md-preview :deep(h1),
.md-preview :deep(h2),
.md-preview :deep(h3),
.md-preview :deep(h4) { margin: 0.6rem 0 0.4rem; font-weight: 700; line-height: 1.3; }
.md-preview :deep(h1) { font-size: 1.3rem; }
.md-preview :deep(h2) { font-size: 1.15rem; }
.md-preview :deep(h3) { font-size: 1.02rem; }
.md-preview :deep(ul),
.md-preview :deep(ol) { margin: 0 0 0.6rem; padding-left: 1.4rem; }
.md-preview :deep(blockquote) {
    margin: 0 0 0.6rem;
    padding: 4px 12px;
    border-left: 3px solid var(--app-accent);
    background: rgba(102, 51, 0, 0.06);
    color: var(--app-text-muted);
    border-radius: 0 6px 6px 0;
}
.md-preview :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82em;
    background: rgba(127, 127, 127, 0.15);
    padding: 1px 5px;
    border-radius: 4px;
}
.md-preview :deep(pre) {
    margin: 0 0 0.6rem;
    padding: 10px 12px;
    background: rgba(127, 127, 127, 0.12);
    border-radius: 8px;
    overflow-x: auto;
}
.md-preview :deep(pre code) { background: none; padding: 0; }
.md-preview :deep(a) { color: var(--app-accent); }

[data-theme="dark"] .md-btn:hover { color: var(--app-accent-2); background: rgba(165, 118, 63, 0.2); }
[data-theme="dark"] .md-tab.active { color: var(--app-accent-2); }
[data-theme="dark"] .md-preview :deep(a) { color: var(--app-accent-2); }
[data-theme="dark"] .md-preview :deep(blockquote) { background: rgba(165, 118, 63, 0.12); border-left-color: var(--app-accent-2); }
</style>
