<script setup>
import { Comment, computed, Text, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';

// Nút dùng chung. variant ngữ nghĩa: white | black | red (hoặc tên màu Bootstrap bất kỳ).
const props = defineProps({
    type: { type: String, default: 'submit' },
    variant: { type: String, default: 'black' },
    outline: { type: Boolean, default: false },
    icon: { type: String, default: null },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const palette = { white: 'light', black: 'dark', red: 'danger' };
const slots = useSlots();

const defaultSlotNodes = computed(() => {
    return (slots.default?.() || []).filter((node) => {
        return node.type !== Comment
            && (node.type !== Text || node.children.trim());
    });
});

const isIconOnly = computed(() => {
    if (props.icon) {
        return defaultSlotNodes.value.length === 0;
    }

    return defaultSlotNodes.value.length === 1
        && defaultSlotNodes.value[0].type === 'i';
});

const klass = computed(() => {
    const color = palette[props.variant] || props.variant;
    return 'btn app-btn ' + (props.outline ? 'btn-outline-' : 'btn-') + color;
});

const buttonClass = computed(() => [
    klass.value,
    { 'btn--icon-only': isIconOnly.value },
]);
</script>

<template>
    <!-- Khi disabled thì luôn render <button disabled> (kể cả có href) để không điều hướng được. -->
    <Link v-if="href && !disabled" :href="href" :class="buttonClass">
        <i v-if="icon" :class="icon"></i><slot />
    </Link>
    <button v-else :type="type" :class="buttonClass" :disabled="disabled">
        <i v-if="icon" :class="icon"></i><slot />
    </button>
</template>

<style scoped>
.app-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}
</style>
