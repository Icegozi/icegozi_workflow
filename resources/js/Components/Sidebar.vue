<script setup>
defineProps({
    // 'bg' cho layout người dùng, 'bg-light' cho layout admin
    bgClass: { type: String, default: 'bg' },
});
</script>

<template>
    <div class="d-flex flex-column flex-shrink-0 p-3" :class="bgClass" style="width: 250px;">
        <a class="navbar-brand" href="#">My<span>App</span></a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <slot />
        </ul>
    </div>
</template>

<!-- Không scoped: mục điều hướng đến từ <slot>. Selector được giới hạn trong
     phạm vi sidebar bằng tiền tố .bg / .nav-pills. -->
<style>
/* Nền sidebar người dùng (bgClass = 'bg') */
.bg {
    background-image: none;
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    height: 100vh;
    margin: 0;
    border-right: 1px solid rgb(101, 79, 79);
}

.navbar-brand {
    display: inline-block;
    font-size: 20px;
    font-weight: 700;
    text-transform: capitalize;
    text-align: center;
}

/* Màu thương hiệu theo nền: tối trên nền sáng (admin), trắng trên nền tối (user) */
.bg .navbar-brand {
    color: #1b1e20;
}

.bg-light .navbar-brand {
    color: #1b1e20;
}

.navbar-brand span {
    display: inline-block;
    color: #ff545a;
    text-transform: capitalize;
}

/* Mục điều hướng (slot) nằm trong danh sách .nav-pills */
.nav-pills .nav-link {
    padding: 10px 15px;
    border-radius: 5px;
}

.nav-pills .nav-link.active {
    background-color: #0b0b0b !important;
    color: #ffffff;
}
</style>
