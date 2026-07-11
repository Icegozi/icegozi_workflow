<script setup>
defineProps({
    // 'bg' = giao diện tối (layout người dùng), 'bg-light' = giao diện sáng (layout admin)
    bgClass: { type: String, default: 'bg' },
});
</script>

<template>
    <aside class="app-sidebar d-flex flex-column flex-shrink-0 p-3" :class="bgClass">
        <!-- Các mục điều hướng do layout truyền vào qua <slot> (admin & user khác nhau) -->
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <slot />
        </ul>
    </aside>
</template>

<style>
.app-sidebar {
    width: 250px;
    min-height: 100%;
    background: var(--app-surface);
    border-right: 1px solid var(--app-border);
    /* Suy ra biến nội bộ từ bảng màu chung -> tự đổi theo dark/light */
    --sb-accent: var(--app-accent);
    --sb-border: var(--app-border);
    --sb-text: var(--app-text);
    --sb-hover-bg: rgba(127, 127, 127, 0.12);
    --sb-brand: var(--app-text);
}

/* Cả hai biến thể đều bám theme; !important để ghi đè .bg-light của Bootstrap */
.app-sidebar.bg,
.app-sidebar.bg-light {
    background: var(--app-surface) !important;
}

/* Mục điều hướng (đến từ slot) */
.app-sidebar .nav-link {
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

.app-sidebar .nav-link i {
    width: 1.1rem;
    text-align: center;
}

.app-sidebar .nav-link:hover,
.app-sidebar .nav-link:focus {
    background-color: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.app-sidebar .nav-link.active,
.app-sidebar .nav-link.active:hover {
    background: linear-gradient(135deg, var(--sb-accent), var(--app-accent-2, #a5763f));
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(102, 51, 0, 0.35);
}

/* Dropdown: trải dọc ngay trong sidebar thay vì nổi đè lên */
.app-sidebar .dropdown-toggle::after {
    margin-left: auto;
}

.app-sidebar .dropdown-menu {
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

.app-sidebar .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.4rem;
    padding: 0.5rem 0.7rem;
    font-size: 0.9rem;
    color: var(--sb-text);
    transition: background-color 0.18s ease, color 0.18s ease;
}

.app-sidebar .dropdown-item:hover {
    background-color: var(--sb-hover-bg);
    color: var(--sb-accent);
}

.app-sidebar .dropdown-item.disabled {
    color: var(--app-text-muted);
}
</style>
