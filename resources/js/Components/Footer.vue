<script setup>
// Footer dùng chung cho layout người dùng và admin
</script>

<template>
    <footer id="footer" class="footer">
        <div class="container">
            <div class="hm-footer-copyright">
                <div class="row">
                    <div class="col-sm-5"><p>© 2025 MyApp - Hà Xuân Phúc</p></div>
                    <div class="col-sm-7">
                        <div class="footer-social">
                            <span><i class="fa fa-envelope"></i> support@myapp.com</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer {
    background-color: #1c1c1c;
    color: #dee2e6;
    padding: 20px 0;
    font-size: 14px;
    line-height: 1.6;
}

.footer .container {
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 15px;
}

.hm-footer-copyright {
    border-top: 1px solid #333;
    padding-top: 15px;
    margin-top: 15px;
}

.hm-footer-copyright p {
    margin: 0;
    color: #adb5bd;
}

.footer-social {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.footer-social span {
    color: #adb5bd;
    font-size: 14px;
    transition: color 0.3s ease;
    display: flex;
    align-items: center;
}

.footer-social span i {
    margin-right: 6px;
}

.footer-social span:hover {
    color: #ff545a;
}

.footer-social a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    background-color: #dee2e6;
    color: #6c757d;
    border-radius: 50%;
    text-decoration: none;
    font-size: 16px;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.footer-social a:hover {
    background-color: #ff545a;
    color: #fff;
}
</style>
