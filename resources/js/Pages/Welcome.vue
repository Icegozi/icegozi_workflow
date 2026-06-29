<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const logout = () => router.post(route('logout'));
const goRegister = () => router.visit(route('register.form'));
const goDashboard = () => router.visit(route('dashboard'));

// Cuộn mượt khi bấm các liên kết neo (#features…), chỉ bật trong vòng đời trang này.
let prevScrollBehavior = '';
onMounted(() => {
    prevScrollBehavior = document.documentElement.style.scrollBehavior;
    document.documentElement.style.scrollBehavior = 'smooth';
});
onBeforeUnmount(() => {
    document.documentElement.style.scrollBehavior = prevScrollBehavior;
});

const features = [
    { icon: 'fas fa-columns', title: 'Bảng trực quan', desc: 'Tổ chức công việc theo cột Kanban, kéo–thả trực quan.' },
    { icon: 'fas fa-check-double', title: 'Quản lý công việc', desc: 'Giao việc, đặt hạn chót, đính kèm tệp và theo dõi tiến độ.' },
    { icon: 'fas fa-users', title: 'Cộng tác nhóm', desc: 'Mời thành viên, phân quyền và bình luận ngay trên công việc.' },
    { icon: 'fas fa-bell', title: 'Thông báo & lịch sử', desc: 'Nhận thông báo và xem lại toàn bộ thay đổi của công việc.' },
    { icon: 'fas fa-bolt', title: 'Nhanh & gọn nhẹ', desc: 'Giao diện nhẹ, thao tác tức thì, tập trung vào việc cần làm.' },
];

const steps = [
    { icon: 'fas fa-user-plus', title: 'Đăng ký & tạo bảng', desc: 'Tạo tài khoản và thiết lập bảng đầu tiên cho nhóm của bạn.' },
    { icon: 'fas fa-tasks', title: 'Thêm & tổ chức việc', desc: 'Tạo thẻ công việc, gán người phụ trách và kéo–thả theo tiến độ.' },
    { icon: 'fas fa-share-alt', title: 'Cộng tác & theo dõi', desc: 'Bình luận, đính kèm và giám sát hiệu quả luồng công việc.' },
];

const stats = [
    { value: '500', unit: 'K+', label: 'Công việc đã quản lý' },
    { value: '10', unit: 'K+', label: 'Nhóm đang hoạt động' },
    { value: '98', unit: '%', label: 'Người dùng hài lòng' },
    { value: '25', unit: '%', label: 'Tăng năng suất' },
];
</script>

<template>
    <Head title="MyApp — Quản lý công việc trực quan" />

    <div class="lp">
        <!-- Header -->
        <header class="lp-header">
            <div class="lp-container lp-header__inner">
                <Link class="lp-brand" :href="route('welcome')">My<span>App</span></Link>

                <nav class="lp-nav">
                    <a href="#features">Tính năng</a>
                    <a href="#how">Cách hoạt động</a>
                    <a href="#stats">Hiệu quả</a>
                </nav>

                <div class="lp-header__actions">
                    <template v-if="!user">
                        <Link class="lp-btn lp-btn--ghost" :href="route('login.form')">Đăng nhập</Link>
                        <Link class="lp-btn lp-btn--solid" :href="route('register.form')">Đăng ký</Link>
                    </template>
                    <template v-else>
                        <Link class="lp-btn lp-btn--ghost" :href="route('dashboard')">
                            <i class="fas fa-th-large"></i> Bảng làm việc
                        </Link>
                        <a class="lp-btn lp-btn--ghost" href="#" @click.prevent="logout" title="Đăng xuất">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section id="home" class="lp-hero">
            <video class="lp-hero__video" autoplay muted loop playsinline>
                <source src="/assets/vid/banner_welcome.mp4" type="video/mp4">
            </video>
            <div class="lp-hero__overlay"></div>

            <div class="lp-container lp-hero__content">
                <template v-if="user">
                    <h1>Chào mừng trở lại, {{ user.name }}!</h1>
                    <p>Các bảng làm việc của bạn đang chờ. Tiếp tục quản lý công việc và cộng tác với nhóm.</p>
                </template>
                <template v-else>
                    <h1>Trực quan hóa luồng công việc,<br>tăng năng suất nhóm</h1>
                    <p>MyApp giúp bạn quản lý công việc, cộng tác và hoàn thành nhiều hơn — đơn giản, trực quan, hiệu quả.</p>
                </template>

                <div class="lp-hero__cta">
                    <button v-if="!user" class="lp-btn lp-btn--solid lp-btn--lg" @click="goRegister">
                        Bắt đầu miễn phí <i class="fas fa-arrow-right"></i>
                    </button>
                    <button v-else class="lp-btn lp-btn--solid lp-btn--lg" @click="goDashboard">
                        Vào bảng làm việc <i class="fas fa-arrow-right"></i>
                    </button>
                    <a v-if="!user" href="#features" class="lp-btn lp-btn--light lp-btn--lg">Khám phá tính năng</a>
                </div>
            </div>
        </section>

        <!-- Tính năng -->
        <section id="features" class="lp-section">
            <div class="lp-container">
                <header class="lp-head">
                    <h2>Tính năng nổi bật</h2>
                    <p>Mọi thứ bạn cần để tối ưu hóa công việc</p>
                </header>
                <div class="lp-grid lp-grid--cards">
                    <article v-for="f in features" :key="f.title" class="lp-card">
                        <div class="lp-card__icon"><i :class="f.icon"></i></div>
                        <h3>{{ f.title }}</h3>
                        <p>{{ f.desc }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Cách hoạt động -->
        <section id="how" class="lp-section lp-section--alt">
            <div class="lp-container">
                <header class="lp-head">
                    <h2>Cách hoạt động</h2>
                    <p>Bắt đầu với MyApp chỉ trong vài phút</p>
                </header>
                <div class="lp-grid lp-grid--steps">
                    <article v-for="(s, i) in steps" :key="s.title" class="lp-step">
                        <span class="lp-step__num">{{ i + 1 }}</span>
                        <div class="lp-step__icon"><i :class="s.icon"></i></div>
                        <h3>{{ s.title }}</h3>
                        <p>{{ s.desc }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Hiệu quả -->
        <section id="stats" class="lp-stats">
            <div class="lp-container lp-grid lp-grid--stats">
                <div v-for="st in stats" :key="st.label" class="lp-stat">
                    <div class="lp-stat__num">{{ st.value }}<span>{{ st.unit }}</span></div>
                    <div class="lp-stat__label">{{ st.label }}</div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="lp-cta">
            <div class="lp-container">
                <template v-if="!user">
                    <h2>Sẵn sàng kiểm soát luồng công việc?</h2>
                    <p>Đăng ký MyApp ngay hôm nay và bắt đầu làm việc hiệu quả hơn.</p>
                    <Link class="lp-btn lp-btn--solid lp-btn--lg" :href="route('register.form')">Đăng ký miễn phí</Link>
                </template>
                <template v-else>
                    <h2>Tiếp tục công việc của bạn</h2>
                    <p>Mọi bảng và công việc đã sẵn sàng. Quay lại và hoàn thành mục tiêu hôm nay.</p>
                    <Link class="lp-btn lp-btn--solid lp-btn--lg" :href="route('dashboard')">Vào bảng làm việc</Link>
                </template>
            </div>
        </section>

        <!-- Footer -->
        <footer class="lp-footer">
            <div class="lp-container lp-footer__top">
                <Link class="lp-brand" :href="route('welcome')">My<span>App</span></Link>
                <nav class="lp-footer__links">
                    <a href="#features">Tính năng</a>
                    <a href="#how">Cách hoạt động</a>
                    <template v-if="!user">
                        <Link :href="route('login.form')">Đăng nhập</Link>
                        <Link :href="route('register.form')">Đăng ký</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('dashboard')">Bảng làm việc</Link>
                        <a href="#" @click.prevent="logout">Đăng xuất</a>
                    </template>
                </nav>
            </div>
            <div class="lp-footer__bottom">
                <div class="lp-container">
                    <span>© 2025 MyApp — Hà Xuân Phúc</span>
                    <span><i class="fas fa-envelope"></i> support@myapp.com</span>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.lp {
    --accent: #ff545a;
    --accent-dark: #f43032;
    --ink: #2d333a;
    --muted: #6c757d;
    --bg-alt: #f8fafb;
    font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: var(--ink);
    line-height: 1.6;
}

.lp *,
.lp *::before,
.lp *::after {
    box-sizing: border-box;
}

.lp-container {
    width: min(1140px, 92%);
    margin-inline: auto;
}

/* Nút dùng chung */
.lp-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.15s ease, background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    white-space: nowrap;
}

.lp-btn:hover { transform: translateY(-2px); }

.lp-btn--solid { background: var(--accent); color: #fff; }
.lp-btn--solid:hover { background: var(--accent-dark); box-shadow: 0 8px 18px rgba(255, 84, 90, 0.35); }

.lp-btn--ghost { color: var(--ink); border-color: #e3e6ea; background: #fff; }
.lp-btn--ghost:hover { color: var(--accent); border-color: var(--accent); }

.lp-btn--light { background: rgba(255, 255, 255, 0.15); color: #fff; border-color: rgba(255, 255, 255, 0.6); }
.lp-btn--light:hover { background: #fff; color: var(--ink); }

.lp-btn--lg { padding: 14px 30px; font-size: 16px; }

/* Thương hiệu */
.lp-brand {
    font-size: 24px;
    font-weight: 800;
    color: var(--ink);
    text-decoration: none;
    letter-spacing: -0.5px;
}
.lp-brand span { color: var(--accent); }

/* Header sticky */
.lp-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid #eef0f3;
}

.lp-header__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    height: 70px;
}

.lp-nav {
    display: flex;
    gap: 28px;
    margin-inline: auto;
}

.lp-nav a {
    color: #5b636b;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s ease;
}
.lp-nav a:hover { color: var(--accent); }

.lp-header__actions { display: flex; gap: 10px; }

/* Hero */
.lp-hero {
    position: relative;
    display: flex;
    align-items: center;
    min-height: clamp(520px, 78vh, 760px);
    overflow: hidden;
    text-align: center;
    color: #fff;
}

.lp-hero__video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: -2;
}

.lp-hero__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(41, 47, 58, 0.55), rgba(41, 47, 58, 0.78));
    z-index: -1;
}

.lp-hero__content { max-width: 760px; margin-inline: auto; }

.lp-hero h1 {
    font-size: clamp(32px, 5vw, 52px);
    font-weight: 800;
    line-height: 1.2;
    margin: 0 0 18px;
}

.lp-hero p {
    font-size: clamp(16px, 2vw, 19px);
    color: rgba(255, 255, 255, 0.9);
    margin: 0 auto 32px;
    max-width: 600px;
}

.lp-hero__cta {
    display: flex;
    gap: 14px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Sections */
.lp-section { padding: clamp(60px, 9vw, 100px) 0; scroll-margin-top: 70px; }
.lp-section--alt { background: var(--bg-alt); }

.lp-head { text-align: center; max-width: 560px; margin: 0 auto clamp(40px, 6vw, 64px); }
.lp-head h2 {
    font-size: clamp(26px, 3.5vw, 34px);
    font-weight: 700;
    margin: 0 0 12px;
}
.lp-head p { color: var(--muted); font-size: 17px; margin: 0; }

/* Lưới */
.lp-grid { display: grid; gap: 24px; }
.lp-grid--cards { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.lp-grid--steps { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.lp-grid--stats { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 30px; }

/* Thẻ tính năng */
.lp-card {
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 14px;
    padding: 32px 26px;
    text-align: center;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.lp-card:hover { transform: translateY(-6px); box-shadow: 0 14px 30px rgba(0, 0, 0, 0.08); }

.lp-card__icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 18px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: rgba(255, 84, 90, 0.1);
    color: var(--accent);
    font-size: 26px;
}
.lp-card h3 { font-size: 18px; font-weight: 600; margin: 0 0 10px; }
.lp-card p { color: var(--muted); font-size: 14.5px; margin: 0; }

/* Bước thực hiện */
.lp-step {
    position: relative;
    background: #fff;
    border-radius: 14px;
    padding: 40px 28px 30px;
    text-align: center;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}
.lp-step__num {
    position: absolute;
    top: -18px;
    left: 50%;
    transform: translateX(-50%);
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--accent);
    color: #fff;
    font-weight: 700;
}
.lp-step__icon { font-size: 34px; color: var(--ink); margin-bottom: 16px; }
.lp-step h3 { font-size: 18px; font-weight: 600; margin: 0 0 10px; }
.lp-step p { color: var(--muted); font-size: 14.5px; margin: 0; }

/* Thống kê */
.lp-stats {
    padding: clamp(56px, 8vw, 90px) 0;
    background: linear-gradient(135deg, #2d333a, #1c1c1c);
    color: #fff;
    text-align: center;
}
.lp-stat__num { font-size: clamp(38px, 5vw, 52px); font-weight: 800; }
.lp-stat__num span { color: var(--accent); margin-left: 4px; }
.lp-stat__label { color: rgba(255, 255, 255, 0.75); margin-top: 6px; font-size: 15px; }

/* CTA */
.lp-cta { padding: clamp(60px, 9vw, 96px) 0; text-align: center; background: var(--bg-alt); }
.lp-cta h2 { font-size: clamp(24px, 3.2vw, 32px); font-weight: 700; margin: 0 0 14px; }
.lp-cta p { color: var(--muted); font-size: 17px; margin: 0 auto 28px; max-width: 520px; }

/* Footer */
.lp-footer { background: #1c1c1c; color: #adb5bd; }
.lp-footer__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 40px 0;
}
.lp-footer .lp-brand { color: #fff; }
.lp-footer__links { display: flex; gap: 24px; flex-wrap: wrap; }
.lp-footer__links a { color: #adb5bd; text-decoration: none; font-size: 14px; transition: color 0.2s ease; }
.lp-footer__links a:hover { color: var(--accent); }

.lp-footer__bottom { border-top: 1px solid #333; padding: 18px 0; }
.lp-footer__bottom .lp-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 13.5px;
}

/* Responsive */
@media (max-width: 768px) {
    .lp-nav { display: none; }
    .lp-header__inner { height: 62px; }
}
</style>
