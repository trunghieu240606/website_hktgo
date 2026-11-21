<?php
/**
 * includes/footer.php
 * Chân trang dùng chung phân hệ Khách hàng HKTGO
 */
?>
</main>
<footer class="ftco-footer ftco-bg-dark ftco-section" style="padding: 45px 0 30px; background: #0b162c; margin-top: auto;">
    <div class="container">
        <div class="row mb-3">
            <div class="col-md-5 mb-3">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fa-solid fa-motorcycle mr-2" style="color:#01d28e;"></i>HKT<span style="color:#01d28e;">GO</span>
                </h5>
                <p class="text-white-50 small mb-2">
                    Hệ thống đặt và cho thuê xe máy tự lái trực tuyến nhanh chóng, thông minh
                </p>
            </div>
            <div class="col-md-4 mb-3">
                <h6 class="text-white font-weight-bold mb-3">Liên kết nhanh</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= baseUrl('index.php') ?>" class="text-white-50">Trang chủ</a></li>
                    <li class="mb-2"><a href="<?= baseUrl('don_cua_toi.php') ?>" class="text-white-50">Tra cứu Đơn của tôi</a></li>
                    <li class="mb-2"><a href="<?= baseUrl('dang_nhap.php') ?>" class="text-white-50">Đăng nhập / Đăng ký tài khoản</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-3">
                <h6 class="text-white font-weight-bold mb-3">Thông tin liên hệ</h6>
                <p class="text-white-50 small mb-1"><i class="fa-solid fa-location-dot mr-2 text-success"></i>41A Phú Diễn, Bắc Từ Liêm, Hà Nội</p>
                <p class="text-white-50 small mb-1"><i class="fa-solid fa-phone mr-2 text-success"></i>0365 145 525</p>
                <p class="text-white-50 small mb-0"><i class="fa-solid fa-envelope mr-2 text-success"></i>hotro@hktgo.vn</p>
            </div>
        </div>
        <hr style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center text-white-50 small">
            &copy; <?= date('Y') ?> <strong>HKTGO</strong> — Hệ thống quản lý và cho thuê xe máy trực tuyến
        </div>
    </div>
</footer>

<script src="<?= baseUrl('js/jquery.min.js') ?>"></script>
<script src="<?= baseUrl('js/popper.min.js') ?>"></script>
<script src="<?= baseUrl('js/bootstrap.min.js') ?>"></script>
</body>
</html>
