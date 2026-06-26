<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const logout = () => router.post(route('logout'));
const goRegister = () => router.visit(route('register.form'));
const goDashboard = () => router.visit(route('dashboard'));
</script>

<template>
    <Head title="Chào mừng đến MyApp" />

    <section class="top-area">
        <div class="header-area">
            <nav class="navbar navbar-default bootsnav navbar-sticky navbar-scrollspy">
                <div class="container">
                    <div class="navbar-header">
                        <Link class="navbar-brand" :href="route('welcome')">My<span>App</span></Link>
                    </div>
                    <div class="collapse navbar-collapse menu-ui-design" id="navbar-menu">
                        <ul class="nav navbar-nav navbar-right">
                            <li class="scroll active"><a href="#home">Trang chủ</a></li>
                            <li class="scroll"><a href="#features">Tính năng</a></li>
                            <li class="scroll"><a href="#howitworks">Cách hoạt động</a></li>
                            <template v-if="!user">
                                <li><Link :href="route('login.form')">Đăng nhập</Link></li>
                                <li><Link :href="route('register.form')">Đăng ký</Link></li>
                            </template>
                            <template v-else>
                                <li><Link :href="route('dashboard')"><i class="fa fa-th-large"></i> Bảng làm việc</Link></li>
                                <li><a href="#" @click.prevent="logout"><i class="fa fa-sign-out-alt"></i> Đăng xuất</a></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
        <div class="clearfix"></div>
    </section>

    <section id="home" class="welcome-hero">
        <video autoplay muted loop playsinline id="bg-video">
            <source src="/assets/vid/banner_welcome.mp4" type="video/mp4">
        </video>
        <div class="container">
            <div class="welcome-hero-txt">
                <template v-if="user">
                    <h2>Chào mừng trở lại, {{ user.name }}!</h2>
                    <p>Các bảng làm việc của bạn đang chờ. Tiếp tục quản lý công việc, cộng tác với nhóm và hoàn thành nhiều việc hơn.</p>
                </template>
                <template v-else>
                    <h2>Trực quan hóa Luồng công việc & Tăng năng suất</h2>
                    <p>MyApp giúp bạn quản lý công việc, cộng tác với nhóm và hoàn thành nhiều việc hơn. Đơn giản, trực quan, hiệu quả.</p>
                </template>
            </div>
            <div class="welcome-hero-serch-box">
                <div class="welcome-hero-serch" style="width:100%; text-align:center;">
                    <button v-if="!user" class="welcome-hero-btn" @click="goRegister">
                        Bắt đầu Miễn phí <i class="fas fa-arrow-circle-right"></i>
                    </button>
                    <button v-else class="welcome-hero-btn" @click="goDashboard">
                        Vào bảng làm việc <i class="fas fa-arrow-circle-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="list-topics">
        <div class="container">
            <div class="section-header">
                <h2>Tính năng Nổi bật</h2>
                <p>Mọi thứ bạn cần để tối ưu hóa công việc</p>
            </div>
            <div class="list-topics-content">
                <ul>
                    <li><div class="single-list-topics-content"><div class="single-list-topics-icon"><i class="fa fa-th-large"></i></div><h2><a href="#">Bảng Trực quan</a></h2></div></li>
                    <li><div class="single-list-topics-content"><div class="single-list-topics-icon"><i class="fa fa-check-square"></i></div><h2><a href="#">Quản lý Công việc</a></h2></div></li>
                    <li><div class="single-list-topics-content"><div class="single-list-topics-icon"><i class="fa fa-users"></i></div><h2><a href="#">Cộng tác Nhóm</a></h2></div></li>
                    <li><div class="single-list-topics-content"><div class="single-list-topics-icon"><i class="fa fa-line-chart"></i></div><h2><a href="#">Theo dõi Tiến độ</a></h2></div></li>
                    <li><div class="single-list-topics-content"><div class="single-list-topics-icon"><i class="fa fa-cogs"></i></div><h2><a href="#">Tùy chỉnh Linh hoạt</a></h2></div></li>
                </ul>
            </div>
        </div>
    </section>

    <section id="howitworks" class="works">
        <div class="container">
            <div class="section-header">
                <h2>Cách Hoạt động</h2>
                <p>Bắt đầu với MyApp chỉ trong vài phút</p>
            </div>
            <div class="works-content">
                <div class="row">
                    <div class="col-md-4 col-sm-6"><div class="single-how-works"><div class="single-how-works-icon"><i class="fas fa-sign-in-alt"></i></div><h2><a href="#">1. Đăng ký & Tạo<span> Bảng</span> Đầu tiên</a></h2><p>Nhanh chóng đăng ký và thiết lập một bảng cho dự án hoặc luồng công việc nhóm của bạn.</p></div></div>
                    <div class="col-md-4 col-sm-6"><div class="single-how-works"><div class="single-how-works-icon"><i class="fas fa-tasks"></i></div><h2><a href="#">2. Thêm Công việc &<span> Tổ chức</span> Công việc</a></h2><p>Tạo thẻ công việc, gán cho thành viên, đặt hạn chót. Kéo và thả để quản lý tiến độ.</p></div></div>
                    <div class="col-md-4 col-sm-6"><div class="single-how-works"><div class="single-how-works-icon"><i class="fas fa-share-alt"></i></div><h2><a href="#">3. Cộng tác &<span> Theo dõi</span> Tiến độ</a></h2><p>Bình luận trên công việc, đính kèm tệp và nhận thông báo. Giám sát hiệu quả luồng công việc.</p></div></div>
                </div>
            </div>
        </div>
    </section>

    <section id="statistics" class="statistics">
        <div class="container">
            <div class="statistics-counter">
                <div class="col-md-3 col-sm-6"><div class="single-ststistics-box"><div class="statistics-content"><div class="counter">500</div> <span>K+</span></div><h3>Công việc đã Quản lý</h3></div></div>
                <div class="col-md-3 col-sm-6"><div class="single-ststistics-box"><div class="statistics-content"><div class="counter">10</div> <span>k+</span></div><h3>Nhóm đang Hoạt động</h3></div></div>
                <div class="col-md-3 col-sm-6"><div class="single-ststistics-box"><div class="statistics-content"><div class="counter">98</div> <span>%</span></div><h3>Hài lòng Người dùng</h3></div></div>
                <div class="col-md-3 col-sm-6"><div class="single-ststistics-box"><div class="statistics-content"><div class="counter">25</div> <span>%</span></div><h3>Tăng Năng suất</h3></div></div>
            </div>
        </div>
    </section>

    <section id="contact" class="subscription">
        <div class="container">
            <template v-if="!user">
                <div class="subscribe-title text-center">
                    <h2>Sẵn sàng kiểm soát luồng công việc của bạn?</h2>
                    <p>Đăng ký MyApp ngay hôm nay và bắt đầu quản lý công việc hiệu quả hơn.</p>
                </div>
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <Link class="appsLand-btn subscribe-btn" :href="route('register.form')">Đăng ký Miễn phí</Link>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="subscribe-title text-center">
                    <h2>Tiếp tục công việc của bạn</h2>
                    <p>Mọi bảng và công việc của bạn đã sẵn sàng. Quay lại và hoàn thành mục tiêu hôm nay.</p>
                </div>
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <Link class="appsLand-btn subscribe-btn" :href="route('dashboard')">Vào bảng làm việc</Link>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <footer id="footer" class="footer">
        <div class="container">
            <div class="footer-menu">
                <div class="row">
                    <div class="col-sm-3"><div class="navbar-header"><a class="navbar-brand" href="#">My<span>App</span></a></div></div>
                    <div class="col-sm-9">
                        <ul class="footer-menu-item">
                            <li class="scroll"><a href="#features">Tính năng</a></li>
                            <li class="scroll"><a href="#howitworks">Cách hoạt động</a></li>
                            <template v-if="!user">
                                <li><Link :href="route('login.form')">Đăng nhập</Link></li>
                                <li><Link :href="route('register.form')">Đăng ký</Link></li>
                            </template>
                            <template v-else>
                                <li><Link :href="route('dashboard')">Bảng làm việc</Link></li>
                                <li><a href="#" @click.prevent="logout">Đăng xuất</a></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="hm-footer-copyright">
                <div class="row">
                    <div class="col-sm-5"><p>© 2025 MyApp</p></div>
                    <div class="col-sm-7"><div class="footer-social"><span><i class="fa fa-envelope"></i> support@myapp.com</span></div></div>
                </div>
            </div>
        </div>
    </footer>
</template>
