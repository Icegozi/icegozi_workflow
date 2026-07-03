<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

defineProps({
    label: { type: String, required: true },
    menuStyle: { type: String, default: '' },
});

const open = ref(false);
const root = ref(null);

const onDocClick = (e) => {
    if (root.value && !root.value.contains(e.target)) open.value = false;
};
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <li class="nav-item dropdown" ref="root">
        <a class="nav-link dropdown-toggle" href="#" role="button" @click.prevent="open = !open">{{ label }}</a>
        <div class="dropdown-menu nav-dropdown-menu show" v-if="open" :style="menuStyle">
            <slot />
        </div>
    </li>
</template>

<!-- Không scoped: các mục đến từ <slot>. Nút mở dropdown (.nav-link) đã được
     Topbar tạo kiểu qua '.main-header .nav-link'. Ở đây chỉ định kiểu cho item. -->
<style>
.main-header .dropdown-item {
    color: var(--app-text, #212529);
    font-size: 14px;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
}

.main-header .dropdown-item:hover {
    background-color: rgba(127, 127, 127, 0.1);
    color: var(--app-accent, #663300);
}

/* Giới hạn bề rộng menu + cắt text (…) khi tên (vd tên board) quá dài -> không vỡ layout */
.nav-dropdown-menu {
    max-width: 215px;
}

.nav-dropdown-menu .dropdown-item {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
