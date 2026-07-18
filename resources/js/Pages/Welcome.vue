<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const logout = () => router.post(route('logout'));
const goRegister = () => router.visit(route('register.form'));
const goDashboard = () => router.visit(route('dashboard'));
const signupEmail = ref('');
const startSignup = () => router.visit(route('register.form'), { data: { email: signupEmail.value } });

// Cuộn mượt khi bấm các liên kết neo (#features…), chỉ bật trong vòng đời trang này.
let prevScrollBehavior = '';
onMounted(() => {
    prevScrollBehavior = document.documentElement.style.scrollBehavior;
    document.documentElement.style.scrollBehavior = 'smooth';
    document.documentElement.classList.add('landing-page');
    document.body.classList.add('landing-page');
});
onBeforeUnmount(() => {
    document.documentElement.style.scrollBehavior = prevScrollBehavior;
    document.documentElement.classList.remove('landing-page');
    document.body.classList.remove('landing-page');
});

const features = [
    { icon: 'fas fa-table-columns', tag: 'Toàn cảnh', title: 'Board nhìn là hiểu', desc: 'Nhìn toàn bộ luồng công việc theo nhóm, sản phẩm hoặc phòng ban trong một màn hình.' },
    { icon: 'fas fa-list-check', tag: 'Tiến độ', title: 'Trạng thái không mơ hồ', desc: 'Tùy biến các bước từ mới tạo đến hoàn tất để cả đội luôn biết việc đang ở đâu.' },
    { icon: 'fas fa-user-tag', tag: 'Trách nhiệm', title: 'Giao đúng người, đúng việc', desc: 'Gán nhiều người phụ trách và bàn giao có xác nhận để task không mất chủ sở hữu.' },
    { icon: 'fas fa-comments', tag: 'Ngữ cảnh', title: 'Trao đổi ngay trong task', desc: 'Bình luận, checklist và tệp đính kèm nằm cạnh công việc thay vì trôi trong tin nhắn.' },
    { icon: 'fas fa-clock-rotate-left', tag: 'Minh bạch', title: 'Lịch sử luôn sẵn sàng', desc: 'Xem các thay đổi quan trọng để biết điều gì đã xảy ra và ai đã cập nhật.' },
    { icon: 'fas fa-user-shield', tag: 'Kiểm soát', title: 'Quyền theo từng board', desc: 'Tách rõ quyền xem, chỉnh sửa và quản lý thành viên cho từng không gian làm việc.' },
];

const steps = [
    { icon: 'fas fa-user-plus', title: 'Đăng ký & tạo bảng', desc: 'Tạo tài khoản và thiết lập bảng đầu tiên cho nhóm của bạn.' },
    { icon: 'fas fa-tasks', title: 'Thêm & tổ chức việc', desc: 'Tạo thẻ công việc, gán người phụ trách và kéo–thả theo tiến độ.' },
    { icon: 'fas fa-share-alt', title: 'Cộng tác & theo dõi', desc: 'Bình luận, đính kèm và giám sát hiệu quả luồng công việc.' },
];

const testimonials = [
    { quote: 'Từ lúc mọi task có trạng thái và người phụ trách, cuộc họp ngắn hơn hẳn.', role: 'Góc nhìn của một Product Lead', initials: 'PL' },
    { quote: 'Cả team nhìn cùng một board nên việc cần kiểm tra không còn bị trôi trong tin nhắn.', role: 'Góc nhìn của một Team Lead', initials: 'TL' },
    { quote: 'Bàn giao có xác nhận giúp tôi yên tâm rời task mà không làm mất ngữ cảnh.', role: 'Góc nhìn của một Contributor', initials: 'CT' },
];

</script>

<template>
    <!-- app.js sẽ dùng tên ứng dụng mặc định khi title rỗng; tránh lặp tên trên tab. -->
    <Head title="" />

    <div class="lp">
        <!-- Header -->
        <header class="lp-header">
            <div class="lp-container lp-header__inner">
                <Link class="lp-brand" :href="route('welcome')">Ic_go-<span>wf</span></Link>

                <nav class="lp-nav">
                    <a href="#features">Tính năng</a>
                    <a href="#how">Cách hoạt động</a>
                    <a href="#reviews">Góc nhìn đội ngũ</a>
                    <a href="#start">Bắt đầu</a>
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
            <div class="lp-orb lp-orb--one"></div>
            <div class="lp-orb lp-orb--two"></div>
            <div class="lp-container lp-hero__content">
                <div class="lp-hero__main">
                    <div class="lp-hero__copy">
                        <template v-if="user">
                            <span class="lp-eyebrow"><i class="fas fa-wand-magic-sparkles"></i> Không bỏ lỡ việc quan trọng</span>
                            <h1>Chào mừng trở lại, {{ user.name }}!</h1>
                            <p>Các board và task của bạn đã sẵn sàng. Tiếp tục công việc trong đúng ngữ cảnh.</p>
                        </template>
                        <template v-else>
                            <span class="lp-eyebrow"><i class="fas fa-layer-group"></i> Không gian làm việc chung cho cả đội</span>
                            <h1>Biến kế hoạch thành<br><em>nhịp làm việc rõ ràng.</em></h1>
                            <p>Ic_go-wf tập hợp task, trạng thái, người phụ trách và lịch sử thay đổi để đội bạn luôn biết việc tiếp theo.</p>
                        </template>
                        <div class="lp-hero__cta">
                            <button v-if="!user" class="lp-btn lp-btn--solid lp-btn--lg" @click="goRegister">
                                Tạo board đầu tiên <i class="fas fa-arrow-right"></i>
                            </button>
                            <button v-else class="lp-btn lp-btn--solid lp-btn--lg" @click="goDashboard">
                                Vào bảng làm việc <i class="fas fa-arrow-right"></i>
                            </button>
                            <a v-if="!user" href="#features" class="lp-btn lp-btn--light lp-btn--lg">Xem cách hoạt động</a>
                        </div>
                        <div class="lp-hero__proof">
                            <span><i class="fas fa-check"></i> Board theo nhóm việc</span>
                            <span><i class="fas fa-check"></i> Trạng thái task riêng biệt</span>
                        </div>
                    </div>
                    <div class="lp-board-preview" aria-label="Minh họa board công việc">
                        <div class="lp-board-preview__top"><span></span><span></span><span></span><strong>Ra mắt phiên bản mới</strong></div>
                        <div class="lp-board-preview__columns">
                            <div class="lp-mini-column">
                                <div class="lp-mini-column__title"><i class="fas fa-code"></i> Backend <b>2</b></div>
                                <div class="lp-mini-card"><span class="lp-mini-card__tag is-blue">Tính năng</span><strong>API thông báo</strong><small><i class="fas fa-circle-check"></i> Đang làm</small></div>
                                <div class="lp-mini-card is-short"><span class="lp-mini-avatar">HN</span><strong>Tối ưu truy vấn</strong></div>
                            </div>
                            <div class="lp-mini-column">
                                <div class="lp-mini-column__title"><i class="fas fa-palette"></i> Thiết kế <b>1</b></div>
                                <div class="lp-mini-card is-lifted"><span class="lp-mini-card__tag is-orange">Ưu tiên</span><strong>Hoàn thiện màn hình dashboard</strong><small><i class="fas fa-circle-check"></i> Kiểm tra</small></div>
                            </div>
                            <div class="lp-mini-column lp-mini-column--fade">
                                <div class="lp-mini-column__title"><i class="fas fa-vial"></i> QA <b>1</b></div>
                                <div class="lp-mini-card"><span class="lp-mini-card__tag is-green">Đã xong</span><strong>Checklist kiểm thử</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tính năng -->
        <section id="features" class="lp-section lp-section--features">
            <div class="lp-container">
                <header class="lp-head">
                    <span class="lp-kicker">Không chỉ là danh sách việc cần làm</span>
                    <h2>Một nơi để công việc được<br>rõ ràng và tiếp tục tiến lên.</h2>
                    <p>Từ lúc tạo task đến khi bàn giao, mọi thông tin đội bạn cần đều ở đúng ngữ cảnh.</p>
                </header>
                <div class="lp-grid lp-grid--cards">
                    <article v-for="f in features" :key="f.title" class="lp-card">
                        <div class="lp-card__top"><div class="lp-card__icon"><i :class="f.icon"></i></div><span>{{ f.tag }}</span></div>
                        <h3>{{ f.title }}</h3>
                        <p>{{ f.desc }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="reviews" class="lp-section lp-reviews">
            <div class="lp-container">
                <header class="lp-head">
                    <span class="lp-kicker">Làm việc nhẹ đầu hơn</span>
                    <h2>Những thay đổi nhỏ, tạo nhịp làm việc tốt hơn.</h2>
                    <p>Ic_go-wf được thiết kế quanh những điều đội ngũ thường cần ở một luồng công việc chung.</p>
                </header>
                <div class="lp-review-grid">
                    <article v-for="review in testimonials" :key="review.role" class="lp-review">
                        <div class="lp-review__stars" aria-label="5 trên 5 sao"><i v-for="star in 5" :key="star" class="fas fa-star"></i></div>
                        <blockquote>“{{ review.quote }}”</blockquote>
                        <div class="lp-review__person"><span>{{ review.initials }}</span><small>{{ review.role }}</small></div>
                    </article>
                </div>
            </div>
        </section>

        <section class="lp-offer">
            <div class="lp-container lp-offer__inner">
                <div><span class="lp-kicker">Ưu đãi khởi động</span><h2>Tạo workspace đầu tiên của bạn ngay hôm nay.</h2><p>Bắt đầu miễn phí, thiết lập board theo cách đội bạn đang làm việc.</p></div>
                <Link v-if="!user" class="lp-btn lp-btn--light lp-btn--lg" :href="route('register.form')">Nhận ưu đãi <i class="fas fa-arrow-right"></i></Link>
                <Link v-else class="lp-btn lp-btn--light lp-btn--lg" :href="route('dashboard')">Mở workspace <i class="fas fa-arrow-right"></i></Link>
            </div>
        </section>

        <!-- Cách hoạt động -->
        <section id="how" class="lp-section lp-section--alt">
            <div class="lp-container">
                <header class="lp-head">
                    <h2>Cách hoạt động</h2>
                    <p>Từ ý tưởng đến công việc hoàn thành trong một luồng rõ ràng.</p>
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

        <!-- CTA -->
        <section id="start" class="lp-cta">
            <div class="lp-container">
                <template v-if="!user">
                    <span class="lp-kicker">Bắt đầu trong vài phút</span>
                    <h2>Sẵn sàng để công việc tiến về phía trước?</h2>
                    <p>Để lại email công việc để mở form tạo tài khoản và tạo board đầu tiên của bạn.</p>
                    <form class="lp-signup-form" @submit.prevent="startSignup">
                        <label class="sr-only" for="landing-email">Email công việc</label>
                        <input id="landing-email" v-model="signupEmail" type="email" required autocomplete="email" placeholder="ban@congty.com">
                        <button class="lp-btn lp-btn--solid lp-btn--lg" type="submit">Tạo tài khoản <i class="fas fa-arrow-right"></i></button>
                    </form>
                    <small class="lp-signup-note"><i class="fas fa-lock"></i> Bạn sẽ hoàn tất thông tin ở bước đăng ký tiếp theo.</small>
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
                <Link class="lp-brand" :href="route('welcome')">Ic_go-<span>wf</span></Link>
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
                    <span>© {{ new Date().getFullYear() }} Ic_go-wf</span>
                    <span><i class="fas fa-shield-halved"></i> Làm việc rõ ràng, phối hợp an tâm</span>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
:global(html.landing-page) {
    height: auto;
    overflow-y: auto;
}

:global(body.landing-page) {
    height: auto;
    min-height: 0;
    overflow: visible;
}

.lp {
    --accent: #663300;
    --accent-dark: #4d2600;
    --ink: #2d333a;
    --muted: #6c757d;
    --bg: #ffffff;
    --bg-alt: #f8fafb;
    --surface: #ffffff;
    --border: #eef0f3;
    --header-bg: rgba(255, 255, 255, 0.95);
    font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: var(--ink);
    line-height: 1.6;
    transition: color 0.25s ease;
}

/* Chế độ tối cho trang chào mừng: chỉ đảo biến, các phần vốn tối (hero/stats/footer) giữ nguyên */
:global([data-theme="dark"]) .lp {
    --accent: #c68a4e;
    --accent-dark: #a5763f;
    --ink: #e6e8eb;
    --muted: #9aa0a6;
    --bg: #15171a;
    --bg-alt: #1a1d21;
    --surface: #1d2024;
    --border: #2c3036;
    --header-bg: rgba(21, 23, 26, 0.95);
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
.lp-btn--solid:hover { background: var(--accent-dark); box-shadow: 0 8px 18px rgba(102, 51, 0, 0.35); }

.lp-btn--ghost { color: var(--ink); border-color: var(--border); background: var(--surface); }
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
    background: var(--header-bg);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--border);
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
    color: var(--muted);
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
    min-height: clamp(620px, 82vh, 800px);
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(circle at 20% 15%, rgba(218, 148, 75, 0.55), transparent 35%),
        radial-gradient(circle at 85% 80%, rgba(117, 70, 197, 0.38), transparent 42%),
        linear-gradient(135deg, #121925, #242d42);
}

.lp-hero__content { position: relative; z-index: 1; }

.lp-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(1px);
    opacity: 0.45;
    pointer-events: none;
    animation: lp-float 9s ease-in-out infinite;
}
.lp-orb--one { width: 220px; height: 220px; top: 12%; left: -70px; background: #e8a35a; }
.lp-orb--two { width: 280px; height: 280px; right: -90px; bottom: -100px; background: #7448b8; animation-delay: -4s; }

.lp-hero__main {
    display: grid;
    grid-template-columns: minmax(0, 0.94fr) minmax(420px, 1.06fr);
    gap: clamp(38px, 7vw, 88px);
    align-items: center;
}

.lp-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 11px;
    margin-bottom: 18px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 99px;
    color: #f8d8b8;
    background: rgba(255, 255, 255, 0.08);
    font-size: 13px;
    font-weight: 600;
}

.lp-hero h1 {
    font-size: clamp(32px, 5vw, 52px);
    font-weight: 800;
    line-height: 1.2;
    margin: 0 0 18px;
}
.lp-hero h1 em { color: #f1b575; font-style: normal; }

.lp-hero p {
    font-size: clamp(16px, 2vw, 19px);
    color: rgba(255, 255, 255, 0.9);
    margin: 0 auto 32px;
    max-width: 600px;
}

.lp-hero__cta {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}

.lp-hero__proof { display: flex; flex-wrap: wrap; gap: 14px 20px; margin-top: 22px; color: rgba(255, 255, 255, 0.78); font-size: 13px; }
.lp-hero__proof i { color: #9ae6bb; margin-right: 5px; }

.lp-board-preview {
    min-width: 0;
    padding: 12px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 30px 70px rgba(4, 8, 18, 0.38);
    backdrop-filter: blur(14px);
    transform: perspective(1200px) rotateY(-4deg) rotateX(2deg);
    animation: lp-board-enter 0.8s ease-out both, lp-board-float 6s 0.8s ease-in-out infinite;
}

.lp-board-preview__top { display: flex; align-items: center; gap: 5px; min-height: 28px; padding: 0 5px 10px; color: #fff; font-size: 11px; }
.lp-board-preview__top span { width: 7px; height: 7px; border-radius: 50%; background: rgba(255, 255, 255, 0.46); }
.lp-board-preview__top strong { margin-left: 7px; font-weight: 600; }
.lp-board-preview__columns { display: grid; grid-template-columns: repeat(3, minmax(130px, 1fr)); gap: 8px; }
.lp-mini-column { min-height: 230px; padding: 9px; border-radius: 10px; background: rgba(235, 240, 249, 0.88); color: #202838; }
.lp-mini-column--fade { opacity: 0.88; }
.lp-mini-column__title { display: flex; align-items: center; gap: 6px; margin: 1px 1px 10px; color: #606a79; font-size: 10px; font-weight: 700; }
.lp-mini-column__title i { color: var(--accent); }
.lp-mini-column__title b { display: grid; place-items: center; width: 16px; height: 16px; margin-left: auto; border-radius: 50%; background: #dfe4eb; font-size: 9px; }
.lp-mini-card { display: flex; flex-direction: column; gap: 7px; padding: 10px; margin-bottom: 8px; border-radius: 8px; background: #fff; box-shadow: 0 2px 7px rgba(38, 49, 66, 0.12); font-size: 10px; }
.lp-mini-card strong { line-height: 1.35; font-size: 11px; }
.lp-mini-card small { color: #647184; font-size: 9px; }
.lp-mini-card small i { color: #e4933f; }
.lp-mini-card__tag { width: fit-content; padding: 2px 5px; border-radius: 4px; font-size: 8px; font-weight: 700; }
.lp-mini-card__tag.is-blue { color: #1265b7; background: #e5f0ff; }
.lp-mini-card__tag.is-orange { color: #aa5200; background: #fff0df; }
.lp-mini-card__tag.is-green { color: #17764b; background: #e2f7eb; }
.lp-mini-card.is-short { min-height: 42px; flex-direction: row; align-items: center; }
.lp-mini-card.is-lifted { animation: lp-card-pulse 3.8s 1s ease-in-out infinite; }
.lp-mini-avatar { display: grid; place-items: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 50%; background: #604394; color: #fff; font-size: 7px; font-weight: 700; }

/* Sections */
.lp-section { padding: clamp(60px, 9vw, 100px) 0; scroll-margin-top: 70px; }
.lp-section--alt, .lp-section--features { background: var(--bg-alt); }

.lp-head { text-align: center; max-width: 560px; margin: 0 auto clamp(40px, 6vw, 64px); }
.lp-head h2 {
    font-size: clamp(26px, 3.5vw, 34px);
    font-weight: 700;
    margin: 0 0 12px;
}
.lp-head p { color: var(--muted); font-size: 17px; margin: 0; }
.lp-kicker { display: inline-block; margin-bottom: 9px; color: var(--accent); font-size: 12px; font-weight: 800; letter-spacing: 0.11em; text-transform: uppercase; }

/* Lưới */
.lp-grid { display: grid; gap: 24px; }
.lp-grid--cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.lp-grid--steps { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.lp-grid--stats { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 30px; }

/* Thẻ tính năng */
.lp-card {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 26px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.lp-card:hover { transform: translateY(-6px); box-shadow: 0 14px 30px rgba(0, 0, 0, 0.08); }

.lp-card__top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 25px; }
.lp-card__top > span { padding: 4px 8px; border-radius: 99px; color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.lp-card__icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: rgba(102, 51, 0, 0.1);
    color: var(--accent);
    font-size: 20px;
}
.lp-card h3 { font-size: 18px; font-weight: 700; margin: 0 0 10px; }
.lp-card p { color: var(--muted); font-size: 14px; margin: 0; }

/* Bước thực hiện */
.lp-step {
    position: relative;
    background: var(--surface);
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

/* Đánh giá & ưu đãi */
.lp-reviews { background: var(--bg); }
.lp-review-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.lp-review { display: flex; flex-direction: column; min-height: 230px; padding: 26px; border: 1px solid var(--border); border-radius: 16px; background: var(--surface); box-shadow: 0 8px 24px rgba(28, 33, 45, 0.05); }
.lp-review__stars { color: #d88a2c; font-size: 12px; letter-spacing: 2px; }
.lp-review blockquote { margin: 17px 0 22px; color: var(--ink); font-size: 16px; font-weight: 600; line-height: 1.65; }
.lp-review__person { display: flex; align-items: center; gap: 10px; margin-top: auto; }
.lp-review__person span { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; color: #fff; background: linear-gradient(135deg, var(--accent), #b6783b); font-size: 10px; font-weight: 800; }
.lp-review__person small { color: var(--muted); font-size: 12px; }

.lp-offer { padding: 28px 0; color: #fff; background: linear-gradient(120deg, #4d2600, #7c4310 58%, #ba7533); }
.lp-offer__inner { display: flex; align-items: center; justify-content: space-between; gap: 30px; }
.lp-offer .lp-kicker { color: #f9d6ad; }
.lp-offer h2 { margin: 0 0 5px; font-size: clamp(23px, 3vw, 31px); }
.lp-offer p { margin: 0; color: rgba(255, 255, 255, 0.8); }
.lp-offer .lp-btn { flex: 0 0 auto; }

/* CTA */
.lp-cta { padding: clamp(60px, 9vw, 96px) 0; text-align: center; background: var(--bg-alt); }
.lp-cta h2 { font-size: clamp(24px, 3.2vw, 32px); font-weight: 700; margin: 0 0 14px; }
.lp-cta p { color: var(--muted); font-size: 17px; margin: 0 auto 28px; max-width: 520px; }
.lp-signup-form { display: flex; width: min(100%, 600px); gap: 10px; margin: 0 auto; padding: 7px; border: 1px solid var(--border); border-radius: 13px; background: var(--surface); box-shadow: 0 12px 28px rgba(28, 33, 45, 0.08); }
.lp-signup-form input { flex: 1; min-width: 0; border: 0; outline: 0; padding: 8px 13px; color: var(--ink); background: transparent; font: inherit; }
.lp-signup-form input::placeholder { color: var(--muted); }
.lp-signup-form .lp-btn { border-radius: 9px; }
.lp-signup-note { display: block; margin-top: 13px; color: var(--muted); font-size: 12px; }
.lp-signup-note i { margin-right: 4px; color: var(--accent); }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

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

@keyframes lp-float {
    50% { transform: translate(16px, -22px) scale(1.08); }
}

@keyframes lp-board-enter {
    from { opacity: 0; transform: perspective(1200px) translateY(24px) rotateY(-9deg) rotateX(3deg); }
    to { opacity: 1; transform: perspective(1200px) rotateY(-4deg) rotateX(2deg); }
}

@keyframes lp-board-float {
    50% { transform: perspective(1200px) translateY(-8px) rotateY(-3deg) rotateX(1deg); }
}

@keyframes lp-card-pulse {
    50% { transform: translateY(-4px); box-shadow: 0 8px 18px rgba(38, 49, 66, 0.18); }
}

/* Responsive */
@media (max-width: 768px) {
    .lp-nav { display: none; }
    .lp-header__inner { height: 62px; }

    .lp-hero { min-height: auto; padding: 74px 0; }
    .lp-hero__main { grid-template-columns: 1fr; gap: 40px; }
    .lp-board-preview { width: min(100%, 560px); margin: 0 auto; }
    .lp-grid--cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .lp-review-grid { grid-template-columns: 1fr; }
    .lp-offer__inner { align-items: flex-start; flex-direction: column; }
}

@media (max-width: 575.98px) {
    .lp-container {
        width: calc(100% - 24px);
    }

    .lp-header__inner {
        gap: 6px;
    }

    .lp-brand {
        font-size: 20px;
    }

    .lp-header__actions {
        gap: 4px;
    }

    .lp-header__actions .lp-btn {
        min-height: 42px;
        padding: 8px 10px;
        font-size: 13px;
    }

    .lp-hero {
        min-height: calc(100dvh - 62px);
        padding: 48px 0;
    }

    .lp-hero h1 {
        font-size: 30px;
    }

    .lp-hero h1 br {
        display: none;
    }

    .lp-hero p {
        margin-bottom: 24px;
        font-size: 15px;
    }

    .lp-hero__cta {
        flex-direction: column;
    }

    .lp-hero__cta .lp-btn {
        width: 100%;
        justify-content: center;
    }

    .lp-hero__proof { display: grid; gap: 7px; }
    .lp-board-preview { padding: 8px; border-radius: 13px; transform: none; }
    .lp-board-preview__columns { grid-template-columns: minmax(126px, 1fr) minmax(126px, 1fr); overflow: hidden; }
    .lp-mini-column--fade { display: none; }

    .lp-review { min-height: 0; padding: 22px; }
    .lp-offer { padding: 40px 0; }
    .lp-offer .lp-btn { width: 100%; justify-content: center; }

    .lp-section {
        padding: 52px 0;
    }

    .lp-head {
        margin-bottom: 34px;
    }

    .lp-grid {
        gap: 14px;
    }

    .lp-grid--cards,
    .lp-grid--stats {
        grid-template-columns: 1fr;
    }

    /* Các bước được vuốt ngang để giữ kích thước card dễ đọc trên mobile. */
    .lp-grid--steps {
        display: flex;
        gap: 14px;
        padding: 18px 2px 8px;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scroll-snap-type: x proximity;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .lp-grid--steps::-webkit-scrollbar {
        display: none;
    }

    .lp-grid--steps .lp-step {
        flex: 0 0 min(82vw, 320px);
        scroll-snap-align: start;
    }

    .lp-card {
        padding: 24px 18px;
    }

    .lp-stats {
        padding: 48px 0;
    }

    .lp-cta {
        padding: 52px 0;
    }

    .lp-cta .lp-btn {
        width: 100%;
        justify-content: center;
    }

    .lp-signup-form { flex-direction: column; padding: 8px; }
    .lp-signup-form input { min-height: 46px; }

    .lp-footer__top {
        align-items: flex-start;
        flex-direction: column;
        padding: 30px 0;
    }

    .lp-footer__links {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .lp-footer__bottom .lp-container {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    .lp *, .lp *::before, .lp *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
