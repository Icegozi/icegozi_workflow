<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    maxWidth: { type: String, default: '500px' },
    align: { type: String, default: 'center' }, // 'center' | 'top'
    headerClass: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const close = () => emit('close');

// Đóng bằng phím Esc
const onKey = (e) => { if (e.key === 'Escape') close(); };
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="modal-backdrop-custom" :class="`is-${align}`" @click.self="close">
        <div class="modal-card" :style="{ maxWidth }">
            <!-- Header: dùng slot 'header' nếu cần tuỳ biến, ngược lại hiện title -->
            <div v-if="title || $slots.header" class="modal-card__header" :class="headerClass">
                <slot name="header">
                    <h6 class="modal-card__title">{{ title }}</h6>
                </slot>
                <button type="button" class="modal-card__close" @click="close" aria-label="Đóng">
                    <span>&times;</span>
                </button>
            </div>

            <!-- Nội dung (form…) truyền qua slot mặc định -->
            <div class="modal-card__body">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    z-index: 1060;
    padding: 30px 12px;
}

.modal-backdrop-custom.is-center { align-items: center; }
.modal-backdrop-custom.is-top { align-items: flex-start; }

.modal-card {
    width: 100%;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 60px);
    background: var(--app-surface);
    color: var(--app-text);
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.modal-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--app-border);
}

.modal-card__title {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
}

.modal-card__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    color: inherit;
    opacity: 0.8;
    cursor: pointer;
    padding: 0 0.25rem;
}

.modal-card__close:hover { opacity: 1; }

.modal-card__body {
    padding: 1rem;
    overflow-y: auto;
}
</style>
