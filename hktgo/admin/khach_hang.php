<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: khach_hang.php');
        exit;
    }

    $action   = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['user_id'] ?? 0);

    $stmtTarget = $pdo->prepare("SELECT id, ho_ten, email, vai_tro, trang_thai FROM users WHERE id = ? LIMIT 1");
    $stmtTarget->execute([$targetId]);
    $targetUser = $stmtTarget->fetch();

    if (!$targetUser) {
        setFlash('danger', 'Không tìm thấy tài khoản người dùng.');
        header('Location: khach_hang.php');
        exit;
    }

    if ($action === 'set_role') {
        $newRole = strtoupper(trim($_POST['vai_tro'] ?? ''));

        if (!in_array($newRole, ['ADMIN', 'USER'], true)) {
            setFlash('danger', 'Vai trò được chọn không hợp lệ (chỉ hỗ trợ ADMIN hoặc USER).');
        } elseif ($targetUser['vai_tro'] === 'ADMIN' && $newRole !== 'ADMIN') {
            $soAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE vai_tro = 'ADMIN'")->fetchColumn();
            if ($soAdmin <= 1) {
                setFlash('warning', 'Không thể hạ quyền tài khoản "' . $targetUser['email'] . '" vì đây là Quản trị viên (ADMIN) duy nhất của hệ thống!');
            } else {
                $upd = $pdo->prepare("UPDATE users SET vai_tro = ? WHERE id = ?");
                $upd->execute([$newRole, $targetId]);
                setFlash('success', 'Đã chuyển vai trò của "' . $targetUser['ho_ten'] . '" sang ' . $newRole . '.');
            }
        } else {
            $upd = $pdo->prepare("UPDATE users SET vai_tro = ?, trang_thai = CASE WHEN ? = 'ADMIN' THEN 'Hoạt động' ELSE trang_thai END WHERE id = ?");
            $upd->execute([$newRole, $newRole, $targetId]);
            setFlash('success', 'Đã cập nhật vai trò của "' . $targetUser['ho_ten'] . '" thành ' . $newRole . '.');
        }
    } elseif ($action === 'toggle_lock') {
        if ($targetUser['vai_tro'] === 'ADMIN') {
            setFlash('warning', 'Không thể khóa tài khoản Quản trị viên (ADMIN)!');
        } else {
            $dangKhoa = (($targetUser['trang_thai'] ?? 'Hoạt động') === 'Bị khóa');
            $ttMoi    = $dangKhoa ? 'Hoạt động' : 'Bị khóa';
            $upd = $pdo->prepare("UPDATE users SET trang_thai = ? WHERE id = ?");
            $upd->execute([$ttMoi, $targetId]);
            setFlash(
                $dangKhoa ? 'success' : 'warning',
                ($dangKhoa ? 'Đã mở khóa quyền đặt xe cho tài khoản "' : 'Đã tạm khóa quyền đặt xe đối với tài khoản "') . $targetUser['ho_ten'] . '".'
            );
        }
    }

    header('Location: khach_hang.php');
    exit;
}

$dsTaiKhoan = $pdo->query("
    SELECT u.id, u.ho_ten, u.email, u.sdt, u.vai_tro, u.trang_thai, u.ngay_tao,
           COUNT(d.id) AS so_don_thue
    FROM users u
    LEFT JOIN don_thue d ON d.user_id = u.id
    GROUP BY u.id
    ORDER BY FIELD(u.vai_tro, 'ADMIN', 'USER'), u.id ASC
")->fetchAll();

$pageTitle = 'Quản lý Tài khoản & Set Role - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1 fw-bold text-dark">Quản lý Tài khoản &amp; Phân quyền (Set Role)</h1>
    <p class="text-muted mb-0">Tra cứu danh sách người dùng, khóa/mở khóa tài khoản vi phạm và cấp/thu hồi vai trò <strong>ADMIN</strong> hoặc <strong>USER</strong></p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0 fw-bold">Danh sách tài khoản hệ thống (<?= count($dsTaiKhoan) ?>)</h5>
        <span class="small text-muted">
            <i class="fa-solid fa-shield-halved text-primary me-1"></i>Hệ thống tự động bảo vệ tài khoản ADMIN cuối cùng
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Họ tên &amp; Liên hệ</th>
                        <th class="text-center">Đơn thuê</th>
                        <th>Vai trò &amp; Trạng thái</th>
                        <th class="pe-3 text-end">Phân quyền &amp; Khóa/Mở</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dsTaiKhoan as $tk):
                        $laAdmin  = ($tk['vai_tro'] === 'ADMIN');
                        $dangKhoa = (($tk['trang_thai'] ?? 'Hoạt động') === 'Bị khóa');
                    ?>
                    <tr>
                        <td class="ps-3 fw-semibold text-muted">#<?= (int) $tk['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($tk['ho_ten']) ?></div>
                            <div class="small text-muted"><?= e($tk['email']) ?> &bull; <code><?= e($tk['sdt']) ?></code></div>
                            <small class="text-muted">Tạo ngày <?= date('d/m/Y', strtotime($tk['ngay_tao'])) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border text-nowrap"><?= (int) $tk['so_don_thue'] ?> đơn</span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <?php if ($laAdmin): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-user-shield me-1"></i>ADMIN
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        <i class="fa-solid fa-user me-1"></i>USER
                                    </span>
                                <?php endif; ?>

                                <?php if ($dangKhoa): ?>
                                    <span class="badge badge-status-huy">
                                        <i class="fa-solid fa-lock me-1"></i>Bị khóa
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-status-ranh">Hoạt động</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="pe-3 text-end">
                            <div class="d-inline-flex flex-wrap align-items-center justify-content-end gap-1">
                                <form method="POST" action="khach_hang.php" class="d-inline-flex align-items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $tk['id'] ?>">
                                    <select name="vai_tro" class="form-select form-select-sm" style="width: 105px;">
                                        <option value="USER" <?= $tk['vai_tro'] === 'USER' ? 'selected' : '' ?>>USER</option>
                                        <option value="ADMIN" <?= $tk['vai_tro'] === 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary fw-semibold text-nowrap">Lưu</button>
                                </form>
                                <?php if (!$laAdmin): ?>
                                    <form method="POST" action="khach_hang.php" class="d-inline"
                                          onsubmit="return confirm('Xác nhận <?= $dangKhoa ? 'mở khóa' : 'tạm khóa' ?> tài khoản <?= e($tk['ho_ten']) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="toggle_lock">
                                        <input type="hidden" name="user_id" value="<?= (int) $tk['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $dangKhoa ? 'btn-outline-success' : 'btn-outline-danger' ?> text-nowrap"
                                                title="<?= $dangKhoa ? 'Mở khóa quyền đặt xe' : 'Khóa quyền đặt xe' ?>">
                                            <i class="fa-solid <?= $dangKhoa ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
