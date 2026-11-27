<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$hoTen = '';
$email = '';
$sdt   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Phiên biểu mẫu không hợp lệ, vui lòng tải lại trang.';
    } else {
        $hoTen   = trim($_POST['ho_ten'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $sdt     = normalizePhone($_POST['sdt'] ?? '');
        $matKhau = (string) ($_POST['mat_khau'] ?? '');
        $nhapLai = (string) ($_POST['nhap_lai_mat_khau'] ?? '');

        if ($hoTen === '' || $email === '' || $sdt === '' || $matKhau === '') {
            $error = 'Vui lòng điền đầy đủ Họ tên, Email, Số điện thoại và Mật khẩu.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Địa chỉ Email không đúng định dạng.';
        } elseif (!preg_match('/^0[1-9][0-9]{8}$/', $sdt)) {
            $error = 'Số điện thoại phải gồm 10 chữ số hợp lệ (ví dụ: 0912345678).';
        } elseif (mb_strlen($matKhau) < 6) {
            $error = 'Mật khẩu phải có tối thiểu 6 ký tự.';
        } elseif ($matKhau !== $nhapLai) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $stmtChk = $pdo->prepare("SELECT id, email, sdt FROM users WHERE email = ? OR sdt = ? LIMIT 1");
            $stmtChk->execute([$email, $sdt]);
            $existed = $stmtChk->fetch();

            if ($existed) {
                $error = ($existed['email'] === $email)
                    ? 'Địa chỉ Email này đã được đăng ký!'
                    : 'Số điện thoại này đã được đăng ký!';
            } else {
                $hash = password_hash($matKhau, PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("
                    INSERT INTO users (ho_ten, email, sdt, mat_khau, vai_tro)
                    VALUES (?, ?, ?, ?, 'USER')
                ");
                $stmtIns->execute([$hoTen, $email, $sdt, $hash]);

                setFlash('success', 'Đăng ký tài khoản thành công! Mời bạn đăng nhập.');
                header('Location: dang_nhap.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Đăng ký tài khoản - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="content-card">
                    <div class="text-center mb-4">
                        <h3 class="font-weight-bold text-dark mb-1">Đăng ký tài khoản</h3>
                        <p class="text-muted small mb-0">Tạo tài khoản khách hàng HKTGO để đặt thuê xe trực tuyến</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="dang_ky.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                        <div class="form-group">
                            <label class="font-weight-bold small">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="ho_ten" class="form-control" value="<?= e($hoTen) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small">Địa chỉ Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small">Số điện thoại (10 số) <span class="text-danger">*</span></label>
                            <input type="tel" name="sdt" class="form-control" maxlength="10" value="<?= e($sdt) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="mat_khau" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="nhap_lai_mat_khau" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2 mt-3">
                            <i class="fa-solid fa-user-plus mr-1"></i>Đăng ký tài khoản
                        </button>
                    </form>

                    <div class="text-center mt-4 small">
                        Đã có tài khoản? <a href="dang_nhap.php" class="font-weight-bold">Đăng nhập ngay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
