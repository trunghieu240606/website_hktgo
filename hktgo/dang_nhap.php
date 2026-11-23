<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/index.php' : 'index.php'));
    exit;
}

$error = '';
$taiKhoan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Phiên biểu mẫu không hợp lệ, vui lòng thử lại.';
    } else {
        $taiKhoan = trim($_POST['tai_khoan'] ?? '');
        $matKhau  = (string) ($_POST['mat_khau'] ?? '');

        if ($taiKhoan === '' || $matKhau === '') {
            $error = 'Vui lòng nhập Email/Số điện thoại và Mật khẩu.';
        } else {
            $phoneNorm = normalizePhone($taiKhoan);
            $stmt = $pdo->prepare("
                SELECT id, ho_ten, email, sdt, mat_khau, vai_tro, trang_thai
                FROM users
                WHERE LOWER(email) = LOWER(?) OR sdt = ?
                LIMIT 1
            ");
            $stmt->execute([$taiKhoan, $phoneNorm]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($matKhau, $user['mat_khau'])) {
                $error = 'Email/Số điện thoại hoặc mật khẩu không chính xác!';
            } else {
                $redirectTarget = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['redirect_after_login']);

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'         => (int) $user['id'],
                    'ho_ten'     => $user['ho_ten'],
                    'email'      => $user['email'],
                    'sdt'        => $user['sdt'],
                    'vai_tro'    => strtoupper($user['vai_tro']),
                    'trang_thai' => $user['trang_thai'] ?? 'Hoạt động',
                ];

                if (strtoupper($user['vai_tro']) === 'ADMIN') {
                    header('Location: admin/index.php');
                } elseif ($redirectTarget !== '' && !preg_match('#^(?:https?:)?//#i', $redirectTarget)) {
                    setFlash('success', 'Xin chào ' . $user['ho_ten'] . '! Bạn đã đăng nhập thành công.');
                    header('Location: ' . $redirectTarget);
                } else {
                    setFlash('success', 'Xin chào ' . $user['ho_ten'] . '! Bạn đã đăng nhập thành công.');
                    header('Location: index.php');
                }
                exit;
            }
        }
    }
}

$pageTitle = 'Đăng nhập hệ thống - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="content-card">
                    <div class="text-center mb-4">
                        <h3 class="font-weight-bold text-dark mb-1">Đăng nhập</h3>
                        <p class="text-muted small mb-0">Chào mừng bạn quay lại hệ thống thuê xe HKTGO</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="dang_nhap.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                        <div class="form-group">
                            <label class="font-weight-bold small">Email hoặc Số điện thoại</label>
                            <input type="text" name="tai_khoan" class="form-control" value="<?= e($taiKhoan) ?>" placeholder="Nhập email hoặc số điện thoại" required autofocus>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small">Mật khẩu</label>
                            <input type="password" name="mat_khau" class="form-control" placeholder="Nhập mật khẩu" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2 mt-3">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i>Đăng nhập
                        </button>
                    </form>

                    <div class="text-center mt-4 small">
                        Chưa có tài khoản? <a href="dang_ky.php" class="font-weight-bold">Đăng ký ngay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
