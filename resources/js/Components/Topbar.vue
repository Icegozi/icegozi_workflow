<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useTheme } from '@/composables/useTheme';
import { avatarSrc } from '@/composables/useSocialLinks';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const userAvatar = computed(() => avatarSrc(user.value?.avatar_url, user.value?.email, 40));

const { theme, toggle: toggleTheme } = useTheme();

// ---- Thông báo ----
const notifications = ref([]);
const unread = ref(0);
const showNotif = ref(false);
let poll = null;

const loadNotifications = async () => {
    try {
        const { data } = await axios.get(route('notifications.index'));
        notifications.value = data.notifications || [];
        unread.value = data.unread || 0;
    } catch (e) { /* im lặng */ }
};

const openNotif = async () => {
    showNotif.value = !showNotif.value;
    if (showNotif.value) await loadNotifications();
};

const onNotifClick = async (n) => {
    try {
        if (!n.is_read) {
            await axios.post(route('notifications.read', n.id));
            n.is_read = true;
            unread.value = Math.max(0, unread.value - 1);
        }
    } catch (e) { /* ignore */ }
    showNotif.value = false;
    if (n.url) router.visit(n.url);
};

const markAllRead = async () => {
    try {
        await axios.post(route('notifications.readAll'));
        notifications.value.forEach((n) => (n.is_read = true));
        unread.value = 0;
    } catch (e) { /* ignore */ }
};

onMounted(() => {
    loadNotifications();
    poll = setInterval(loadNotifications, 60000);   // làm mới mỗi 60s
});
onUnmounted(() => poll && clearInterval(poll));

const logout = () => {
    if (confirm('Bạn có chắc chắn muốn đăng xuất không?')) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <nav class="app-topbar navbar navbar-expand border-bottom">
        <a class="topbar-brand ml-5" href="/">My<span>App</span></a>
        <!-- Điều hướng bên trái (tuỳ layout) -->
        <ul class="navbar-nav">
            <slot />
        </ul>

        <!-- Người dùng + đăng xuất (dùng chung) -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="#" @click.prevent="toggleTheme"
                    :title="theme === 'dark' ? 'Chuyển sáng' : 'Chuyển tối'">
                    <i :class="theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'"></i>
                </a>
            </li>
            <li class="nav-item position-relative">
                <a class="nav-link d-flex align-items-center" href="#" @click.prevent="openNotif" title="Thông báo">
                    <i class="far fa-bell"></i>
                    <span v-if="unread" class="notif-badge">{{ unread > 9 ? '9+' : unread }}</span>
                </a>
                <div v-if="showNotif" class="notif-panel card shadow">
                    <div class="notif-head d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>Thông báo</strong>
                        <a v-if="unread" href="#" class="small" @click.prevent="markAllRead">Đánh dấu đã đọc</a>
                    </div>
                    <div class="notif-list">
                        <a v-for="n in notifications" :key="n.id" href="#"
                            class="notif-item px-3 py-2 d-block" :class="{ unread: !n.is_read }"
                            @click.prevent="onNotifClick(n)">
                            <div v-html="n.message" class="notif-msg"></div>
                            <div class="text-muted" style="font-size:.72rem;">{{ n.time_ago }}</div>
                        </a>
                        <div v-if="!notifications.length" class="text-muted text-center py-3 small">
                            Chưa có thông báo.
                        </div>
                    </div>
                </div>
            </li>
            <li class="nav-item d-flex align-items-center">
                <Link :href="route('profile.edit')" class="nav-link d-flex align-items-center" title="Hồ sơ cá nhân">
                    <img :src="userAvatar" class="rounded-circle mr-2 topbar-avatar" width="28" height="28" alt="avatar">
                    <span>{{ user?.name }}</span>
                </Link>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="#" @click.prevent="logout" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
</template>

<!-- Không dùng scoped: các link điều hướng đến từ <slot> (do layout cha render),
     nên cần selector global, giới hạn phạm vi bằng tiền tố .app-topbar. -->
<style>
/* Chuông thông báo */
.app-topbar .notif-badge {
    position: absolute;
    top: 2px;
    right: 0;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: var(--app-accent, #663300);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
}

.app-topbar .topbar-avatar {
    object-fit: cover;
    border: 1px solid var(--app-border, #e4e6ea);
}

.app-topbar .notif-panel {
    position: absolute;
    top: 120%;
    right: 0;
    width: 340px;
    max-width: 90vw;
    z-index: 1050;
    border: 1px solid var(--app-border, #e4e6ea);
    border-radius: 10px;
    overflow: hidden;
    background: var(--app-surface, #fff);
}

.app-topbar .notif-head {
    border-bottom: 1px solid var(--app-border, #eee);
}

.app-topbar .notif-list {
    max-height: 380px;
    overflow-y: auto;
}

.app-topbar .notif-item {
    color: var(--app-text, #212529);
    border-bottom: 1px solid rgba(127, 127, 127, 0.12);
    text-decoration: none;
}

.app-topbar .notif-item:hover {
    background: rgba(127, 127, 127, 0.1);
}

.app-topbar .notif-item.unread {
    background: rgba(102, 51, 0, 0.08);
}

.app-topbar .notif-msg {
    font-size: 0.82rem;
    line-height: 1.35;
}

.app-topbar.navbar {
    background-color: var(--app-surface, #ffffff);
    color: var(--app-text, #212529);
    padding: 0.5rem 1rem;
    border-bottom: 2px solid var(--app-accent, #663300) !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.app-topbar .topbar-brand {
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: capitalize;
    text-decoration: none;
    color: var(--app-text, #212529);
    margin-right: 1.5rem;
    display: flex;
    align-items: center;
}

.app-topbar .topbar-brand span {
    color: var(--app-accent, #663300);
}

.app-topbar .navbar-nav {
    display: flex;
    align-items: center;
}

.app-topbar .navbar-nav.ml-auto .nav-item {
    margin-left: 15px;
    font-weight: 500;
    color: var(--app-text, #212529);
    display: flex;
    align-items: center;
}

.app-topbar .navbar-nav.ml-auto .nav-link {
    display: flex;
    align-items: center;
    padding: 8px;
    color: var(--app-text, #212529);
}

.app-topbar .navbar-nav.ml-auto .nav-link i {
    font-size: 16px;
}

/* Áp cho link bên trái (slot), nút dropdown và link bên phải */
.app-topbar .nav-link {
    color: var(--app-text, #212529);
    font-size: 14px;
    transition: color 0.3s ease, background-color 0.3s ease;
    display: flex;
    align-items: center;
}

.app-topbar .nav-link:hover {
    color: var(--app-accent, #663300);
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 5px;
}
</style>
