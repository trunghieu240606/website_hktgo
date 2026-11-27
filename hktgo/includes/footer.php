<?php
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
                    <li class="mb-2"><a href="<?= baseUrl('docs/dieu_khoan_hktgo.pdf') ?>" target="_blank" class="text-white-50">Điều khoản &amp; Chính sách thuê xe (PDF)</a></li>
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

<?php if (isLoggedIn() && !empty($_SESSION['show_change_pwd_modal'])): ?>
<div class="modal fade" id="modalGoiYDoiMatKhau" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold mb-0">
                    <i class="fa-solid fa-shield-halved mr-2"></i>Đổi Mật Khẩu Bảo Mật
                </h5>
                <button type="button" class="close text-white btn-dismiss-pwd" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="<?= baseUrl('ho_so.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="doi_mat_khau">
                <input type="hidden" name="from_modal" value="1">
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Bạn vừa đăng nhập bằng <strong>mật khẩu tạm thời</strong>. Bạn có muốn đặt lại mật khẩu cá nhân dễ nhớ ngay bây giờ không? (Có thể bấm <em>Để sau</em> và đổi bất kỳ lúc nào trong mục <strong>Tài khoản cá nhân</strong>).
                    </p>
                    <div class="mb-3">
                        <label class="font-weight-semibold small">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="mat_khau_moi" class="form-control" required minlength="6" placeholder="Nhập mật khẩu mới (tối thiểu 6 ký tự)">
                    </div>
                    <div class="mb-2">
                        <label class="font-weight-semibold small">Nhập lại mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="xac_nhan_mat_khau" class="form-control" required minlength="6" placeholder="Xác nhận lại mật khẩu mới">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-dismiss-pwd">Để sau</button>
                    <button type="submit" class="btn btn-success font-weight-bold" style="background:#01d28e;border-color:#01d28e;">
                        <i class="fa-solid fa-check mr-1"></i>Đổi mật khẩu ngay
                    </button>
                </div>
            </form>
            <form id="formDismissPwdModal" method="POST" action="<?= baseUrl('ho_so.php') ?>" class="d-none">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="dismiss_pwd_modal">
                <input type="hidden" name="redirect_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery !== 'undefined' && jQuery('#modalGoiYDoiMatKhau').length) {
        jQuery('#modalGoiYDoiMatKhau').modal('show');
    }
    var dismissBtns = document.querySelectorAll('.btn-dismiss-pwd');
    dismissBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('formDismissPwdModal').submit();
        });
    });
});
</script>
<?php endif; ?>

</body>
</html>
