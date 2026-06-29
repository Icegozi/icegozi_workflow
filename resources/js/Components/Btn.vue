<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Nút dùng chung. variant ngữ nghĩa: white | black | red (hoặc tên màu Bootstrap bất kỳ).
const props = defineProps({
    type: { type: String, default: 'submit' },
    variant: { type: String, default: 'black' },
    outline: { type: Boolean, default: false },
    icon: { type: String, default: null },
    href: { type: String, default: null },
});

const palette = { white: 'light', black: 'dark', red: 'danger' };

const klass = computed(() => {
    const color = palette[props.variant] || props.variant;
    return 'btn ' + (props.outline ? 'btn-outline-' : 'btn-') + color;
});
</script>

<template>
    <Link v-if="href" :href="href" :class="klass">
        <i v-if="icon" :class="icon" class="mr-1"></i><slot />
    </Link>
    <button v-else :type="type" :class="klass">
        <i v-if="icon" :class="icon" class="mr-1"></i><slot />
    </button>
</template>
