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
        <div class="dropdown-menu show" v-if="open" :style="menuStyle">
            <slot />
        </div>
    </li>
</template>

<!-- Không scoped: các mục đến từ <slot>. Nút mở dropdown (.nav-link) đã được
     Topbar tạo kiểu qua '.main-header .nav-link'. Ở đây chỉ định kiểu cho item. -->
<style>
.main-header .dropdown-item {
    color: #212529;
    font-size: 14px;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
}

.main-header .dropdown-item:hover {
    background-color: #f1f1f1;
    color: #ff545a;
}
</style>
