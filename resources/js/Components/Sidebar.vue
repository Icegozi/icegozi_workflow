<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    // 'bg' = giao diện tối (layout người dùng), 'bg-light' = giao diện sáng (layout admin)
    bgClass: { type: String, default: 'bg' },
});

const isCollapsed = ref(false);
const storageKey = 'sidebar-collapsed';

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem(storageKey, String(isCollapsed.value));
};

const handleShortcut = (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'b') {
        return;
    }

    const target = event.target;
    const isEditing = target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

    if (isEditing) {
        return;
    }

    event.preventDefault();
    toggleSidebar();
};

onMounted(() => {
    isCollapsed.value = localStorage.getItem(storageKey) === 'true';
    window.addEventListener('keydown', handleShortcut);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleShortcut);
});
</script>

<template>
    <aside
        class="workflow-sidebar d-flex flex-column flex-shrink-0 p-3"
        :class="[bgClass, { 'is-collapsed': isCollapsed }]"
    >
        <button
            type="button"
            class="sidebar-toggle"
            :aria-label="isCollapsed ? 'Mở sidebar' : 'Thu gọn sidebar'"
            :aria-expanded="!isCollapsed"
            :title="`${isCollapsed ? 'Mở' : 'Thu gọn'} sidebar (Ctrl + B)`"
            @click="toggleSidebar"
        >
            <i
                class="fas"
                :class="isCollapsed ? 'fa-angle-double-right' : 'fa-angle-double-left'"
                aria-hidden="true"
            ></i>
        </button>

        <!-- Các mục điều hướng do layout truyền vào qua <slot> (admin & user khác nhau) -->
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <slot />
        </ul>
    </aside>
</template>

<style>
.workflow-sidebar {
    --sb-width: 250px;
    width: var(--sb-width);
    min-width: var(--sb-width);
    max-width: var(--sb-width);
    flex-basis: var(--sb-width);
    min-height: 100%;
    background: var(--app-surface);
    border-right: 1px solid var(--app-border);
    /* Suy ra biến nội bộ từ bảng màu chung -> tự đổi theo dark/light */
    --sb-accent: var(--app-accent);
    --sb-border: var(--app-border);
    --sb-text: var(--app-text);
    --sb-hover-bg: rgba(127, 127, 127, 0.12);
    --sb-brand: var(--app-text);
    overflow: hidden;
    transition:
        width 0.2s ease,
        min-width 0.2s ease,
        max-width 0.2s ease,
        flex-basis 0.2s ease,
        padding 0.2s ease;
}

.workflow-sidebar.is-collapsed {
    --sb-width: 72px;
    padding-right: 0.75rem !important;
    padding-left: 0.75rem !important;
}

.sidebar-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: flex-end;
    width: 2rem;
    height: 2rem;
    margin-bottom: 0.75rem;
    padding: 0;
    border: 1px solid var(--sb-border);
    border-radius: 0.5rem;
    background: transparent;
    color: var(--sb-text);
    transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.sidebar-toggle:hover,
.sidebar-toggle:focus-visible {
    border-color: var(--sb-accent);
    background-color: var(--sb-hover-bg);
    color: var(--sb-accent);
    outline: none;
}

.workflow-sidebar.is-collapsed .sidebar-toggle {
    align-self: center;
}

/* Cả hai biến thể đều bám theme; !important để ghi đè .bg-light của Bootstrap */
.workflow-sidebar.bg,
.workflow-sidebar.bg-light {
    background: var(--app-surface) !important;
}

/* Mục điều hướng (đến từ slot) */
.workflow-sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.85rem;
    border-radius: 0.55rem;
    color: var(--sb-text);
    font-weight: 500;
    font-size: 0.95rem;
    transition: background-color 0.18s ease, color 0.18s ease;
}

.workflow-sidebar .nav-link i {
    width: 1.1rem;
    text-align: center;
    flex-shrink: 0;
}

.workflow-sidebar .nav-label {
    white-space: nowrap;
}

.workflow-sidebar.is-collapsed .nav-link {
    justify-content: center;
    gap: 0;
    padding-right: 0.65rem;
    padding-left: 0.65rem;
}

.workflow-sidebar.is-collapsed .nav-label,
.workflow-sidebar.is-collapsed .dropdown-toggle::after,
.workflow-sidebar.is-collapsed .dropdown-menu {
    display: none;
}

.workflow-sidebar .nav-link:hover,
.workflow-sidebar .nav-link:focus {
    background-color: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.workflow-sidebar .nav-link.active,
.workflow-sidebar .nav-link.active:hover {
    background: linear-gradient(135deg, var(--sb-accent), var(--app-accent-2, #a5763f));
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(102, 51, 0, 0.35);
}

/* Dropdown: trải dọc ngay trong sidebar thay vì nổi đè lên */
.workflow-sidebar .dropdown-toggle::after {
    margin-left: auto;
}

.workflow-sidebar .dropdown-menu {
    position: static;
    float: none;
    width: 100%;
    margin-top: 0.25rem;
    background: var(--app-surface);
    border: 1px solid var(--sb-border);
    border-radius: 0.6rem;
    padding: 0.35rem;
    box-shadow: none;
}

.workflow-sidebar .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.4rem;
    padding: 0.5rem 0.7rem;
    font-size: 0.9rem;
    color: var(--sb-text);
    transition: background-color 0.18s ease, color 0.18s ease;
}

.workflow-sidebar .dropdown-item:hover {
    background-color: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.workflow-sidebar .dropdown-item.disabled {
    color: var(--app-text-muted);
}
</style>
