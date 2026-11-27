<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ, vui lòng thử lại.');
        header('Location: ho_so.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'dismiss_pwd_modal') {
        unset($_SESSION['show_change_pwd_modal']);
        $redirectBack = $_POST['redirect_to'] ?? 'index.php';
        header('Location: ' . $redirectBack);
        exit;
    }

    if ($action === 'doi_mat_khau') {
        $fromModal  = isset($_POST['from_modal']);
        $mkHienTai  = (string) ($_POST['mat_khau_hien_tai'] ?? '');
        $mkMoi      = (string) ($_POST['mat_khau_moi'] ?? '');
        $mkXacNhan  = (string) ($_POST['xac_nhan_mat_khau'] ?? '');

        $stmtAuth = $pdo->prepare("SELECT mat_khau FROM users WHERE id = ? LIMIT 1");
        $stmtAuth->execute([(int) $user['id']]);
        $hashHienTai = (string) $stmtAuth->fetchColumn();

        $errs = [];
        if (!$fromModal && !password_verify($mkHienTai, $hashHienTai)) {
            $errs[] = 'Mật khẩu hiện tại không chính xác.';
        }
        if (strlen($mkMoi) < 6) {
            $errs[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        if ($mkMoi !== $mkXacNhan) {
            $errs[] = 'Mật khẩu xác nhận không trùng khớp.';
        }

        if (!empty($errs)) {
            setFlash('danger', implode(' ', $errs));
            header('Location: ho_so.php');
            exit;
        }

        $hashMoi = password_hash($mkMoi, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET mat_khau = ? WHERE id = ?");
        $upd->execute([$hashMoi, (int) $user['id']]);

        unset($_SESSION['show_change_pwd_modal']);
        if (isset($_SESSION['temp_pwd_user_ids'][(int) $user['id']])) {
            unset($_SESSION['temp_pwd_user_ids'][(int) $user['id']]);
        }

        setFlash('success', 'Đã cập nhật mật khẩu mới thành công!');
        header('Location: ho_so.php');
        exit;
    }

    if ($action === 'cap_nhat_thong_tin') {
        $hoTenMoi = trim((string) ($_POST['ho_ten'] ?? ''));
        if ($hoTenMoi === '') {
            setFlash('danger', 'Họ và tên không được để trống.');
        } else {
            $upd = $pdo->prepare("UPDATE users SET ho_ten = ? WHERE id = ?");
            $upd->execute([$hoTenMoi, (int) $user['id']]);
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['ho_ten'] = $hoTenMoi;
            }
            setFlash('success', 'Đã cập nhật thông tin tài khoản.');
        }
        header('Location: ho_so.php');
        exit;
    }
}

$stmtHoSo = $pdo->prepare("
    SELECT so_cccd, so_blx
    FROM don_thue
    WHERE user_id = ? AND so_cccd IS NOT NULL AND so_cccd <> ''
    ORDER BY id DESC
    LIMIT 1
");
$stmtHoSo->execute([(int) $user['id']]);
$dinhDanhGanNhat = $stmtHoSo->fetch();

$pageTitle = 'Tài khoản cá nhân - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container">
        <h2 class="font-weight-bold text-white mb-1">
            <i class="fa-solid fa-user-gear mr-2" style="color:#01d28e;"></i>Thông Tin Tài Khoản Cá Nhân
        </h2>
        <p class="text-white-50 mb-0">Quản lý hồ sơ cá nhân, trạng thái tài khoản và đổi mật khẩu bảo mật</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="content-card h-100">
                    <h4 class="font-weight-bold text-dark mb-3">
                        <i class="fa-solid fa-id-badge text-primary mr-2"></i>Hồ Sơ Thành Viên
                    </h4>

                    <form method="POST" action="ho_so.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="cap_nhat_thong_tin">

                        <div class="mb-3">
                            <label class="font-weight-semibold">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="ho_ten" class="form-control" required value="<?= e($user['ho_ten']) ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-semibold">Email đăng nhập</label>
                                <input type="text" class="form-control bg-light" readonly value="<?= e($user['email']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-semibold">Số điện thoại</label>
                                <input type="text" class="form-control bg-light" readonly value="<?= e($user['sdt']) ?>">
                            </div>
                        </div>

                        <div class="p-3 rounded mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Vai trò hệ thống:</span>
                                <strong class="small"><?= e($user['vai_tro']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Trạng thái tài khoản:</span>
                                <?php if (($user['trang_thai'] ?? 'Hoạt động') === 'Bị khóa'): ?>
                                    <span class="badge badge-danger">Bị khóa (Nợ vi phạm)</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Đang hoạt động</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Số định danh VNeID đã lưu:</span>
                                <code class="small"><?= e($dinhDanhGanNhat['so_cccd'] ?? 'Chưa phát sinh đơn thuê') ?></code>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Số BLX đã lưu:</span>
                                <code class="small"><?= e($dinhDanhGanNhat['so_blx'] ?? 'Chưa có') ?></code>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary font-weight-bold">
                            <i class="fa-solid fa-floppy-disk mr-1"></i>Lưu thay đổi họ tên
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="content-card h-100">
                    <h4 class="font-weight-bold text-dark mb-3">
                        <i class="fa-solid fa-lock text-success mr-2"></i>Đổi Mật Khẩu Đăng Nhập
                    </h4>

                    <?php if (!empty($_SESSION['show_change_pwd_modal'])): ?>
                        <div class="alert alert-warning small">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Bạn đang sử dụng <strong>mật khẩu tạm thời</strong> vừa khôi phục. Khuyến nghị đặt lại mật khẩu cá nhân mới ngay bên dưới!
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="ho_so.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="doi_mat_khau">

                        <div class="mb-3">
                            <label class="font-weight-semibold">Mật khẩu hiện tại (hoặc mật khẩu tạm) <span class="text-danger">*</span></label>
                            <input type="password" name="mat_khau_hien_tai" class="form-control" required placeholder="Nhập mật khẩu đang dùng">
                        </div>

                        <div class="mb-3">
                            <label class="font-weight-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
                            <input type="password" name="mat_khau_moi" class="form-control" required minlength="6" placeholder="Tối thiểu 6 ký tự">
                        </div>

                        <div class="mb-4">
                            <label class="font-weight-semibold">Xác nhận lại mật khẩu mới <span class="text-danger">*</span></label>
                            <input type="password" name="xac_nhan_mat_khau" class="form-control" required minlength="6" placeholder="Nhập lại mật khẩu mới">
                        </div>

                        <button type="submit" class="btn btn-success font-weight-bold" style="background:#01d28e;border-color:#01d28e;">
                            <i class="fa-solid fa-key mr-1"></i>Cập nhật mật khẩu mới
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
