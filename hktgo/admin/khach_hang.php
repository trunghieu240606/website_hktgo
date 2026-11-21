<?php
/**
 * admin/khach_hang.php
 * Quản lý Tài khoản & Đổi vai trò phân quyền (Set Role: ADMIN / USER)
 * Chặn tự hạ quyền khi hệ thống chỉ còn duy nhất 01 tài khoản ADMIN
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_role') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: khach_hang.php');
        exit;
    }

    $targetId = (int) ($_POST['user_id'] ?? 0);
    $newRole  = strtoupper(trim($_POST['vai_tro'] ?? ''));

    if (!in_array($newRole, ['ADMIN', 'USER'], true)) {
        setFlash('danger', 'Vai trò được chọn không hợp lệ (chỉ hỗ trợ ADMIN hoặc USER).');
        header('Location: khach_hang.php');
        exit;
    }

    $stmtTarget = $pdo->prepare("SELECT id, ho_ten, email, vai_tro FROM users WHERE id = ? LIMIT 1");
    $stmtTarget->execute([$targetId]);
    $targetUser = $stmtTarget->fetch();

    if (!$targetUser) {
        setFlash('danger', 'Không tìm thấy tài khoản người dùng.');
    } elseif ($targetUser['vai_tro'] === 'ADMIN' && $newRole !== 'ADMIN') {
        // Kiểm tra số lượng ADMIN còn lại để bảo vệ không mất trắng quyền quản trị
        $soAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE vai_tro = 'ADMIN'")->fetchColumn();
        if ($soAdmin <= 1) {
            setFlash('warning', 'Không thể hạ quyền tài khoản "' . $targetUser['email'] . '" vì đây là Quản trị viên (ADMIN) duy nhất của hệ thống!');
        } else {
            $upd = $pdo->prepare("UPDATE users SET vai_tro = ? WHERE id = ?");
            $upd->execute([$newRole, $targetId]);
            setFlash('success', 'Đã chuyển vai trò của "' . $targetUser['ho_ten'] . '" sang ' . $newRole . '.');
        }
    } else {
        $upd = $pdo->prepare("UPDATE users SET vai_tro = ? WHERE id = ?");
        $upd->execute([$newRole, $targetId]);
        setFlash('success', 'Đã cập nhật vai trò của "' . $targetUser['ho_ten'] . '" thành ' . $newRole . '.');
    }

    header('Location: khach_hang.php');
    exit;
}

$dsTaiKhoan = $pdo->query("
    SELECT u.id, u.ho_ten, u.email, u.sdt, u.vai_tro, u.ngay_tao,
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
    <p class="text-muted mb-0">Tra cứu danh sách người dùng và cấp/thu hồi vai trò <strong>ADMIN</strong> hoặc <strong>USER</strong></p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
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
                        <th>Họ và tên</th>
                        <th>Email đăng nhập</th>
                        <th>Số điện thoại</th>
                        <th class="text-center">Số đơn thuê</th>
                        <th>Vai trò hiện tại</th>
                        <th class="pe-3 text-end" style="width: 250px;">Đổi vai trò (Set Role)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dsTaiKhoan as $tk):
                        $laAdmin = ($tk['vai_tro'] === 'ADMIN');
                    ?>
                    <tr>
                        <td class="ps-3 fw-semibold text-muted">#<?= (int) $tk['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($tk['ho_ten']) ?></div>
                            <small class="text-muted">Tạo ngày <?= date('d/m/Y', strtotime($tk['ngay_tao'])) ?></small>
                        </td>
                        <td><?= e($tk['email']) ?></td>
                        <td><code><?= e($tk['sdt']) ?></code></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border"><?= (int) $tk['so_don_thue'] ?> đơn</span>
                        </td>
                        <td>
                            <?php if ($laAdmin): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="fa-solid fa-user-shield me-1"></i>ADMIN
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="fa-solid fa-user me-1"></i>USER
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 text-end">
                            <form method="POST" action="khach_hang.php" class="d-inline-flex align-items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="user_id" value="<?= (int) $tk['id'] ?>">
                                <select name="vai_tro" class="form-select form-select-sm" style="width: 115px;">
                                    <option value="USER" <?= $tk['vai_tro'] === 'USER' ? 'selected' : '' ?>>USER</option>
                                    <option value="ADMIN" <?= $tk['vai_tro'] === 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary fw-semibold">Lưu</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
