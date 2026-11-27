<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: danh_muc.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM xe_may WHERE danh_muc_xe_id = ?");
        $stmtCount->execute([$id]);
        $soXe = (int) $stmtCount->fetchColumn();

        if ($soXe > 0) {
            setFlash('warning', 'Không thể xóa danh mục này vì đang có ' . $soXe . ' mẫu xe trực thuộc! Vui lòng chuyển danh mục các xe đó trước.');
        } else {
            $del = $pdo->prepare("DELETE FROM danh_muc_xe WHERE id = ?");
            $del->execute([$id]);
            setFlash('success', 'Đã xóa danh mục loại xe thành công.');
        }
        header('Location: danh_muc.php');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id    = (int) ($_POST['id'] ?? 0);
        $ten   = trim($_POST['ten'] ?? '');
        $moTa  = trim($_POST['mo_ta'] ?? '');
        $thuTu = (int) ($_POST['thu_tu'] ?? 0);

        if ($ten === '') {
            setFlash('danger', 'Tên danh mục loại xe không được để trống.');
            header('Location: danh_muc.php' . ($action === 'update' ? '?edit=' . $id : ''));
            exit;
        }

        $stmtDup = $pdo->prepare("SELECT id FROM danh_muc_xe WHERE ten = ? AND id <> ? LIMIT 1");
        $stmtDup->execute([$ten, $id]);
        if ($stmtDup->fetch()) {
            setFlash('danger', 'Tên danh mục "' . $ten . '" đã tồn tại!');
            header('Location: danh_muc.php' . ($action === 'update' ? '?edit=' . $id : ''));
            exit;
        }

        if ($action === 'create') {
            $ins = $pdo->prepare("INSERT INTO danh_muc_xe (ten, mo_ta, thu_tu) VALUES (?, ?, ?)");
            $ins->execute([$ten, $moTa, $thuTu]);
            setFlash('success', 'Đã thêm danh mục "' . $ten . '" thành công.');
        } else {
            $upd = $pdo->prepare("UPDATE danh_muc_xe SET ten = ?, mo_ta = ?, thu_tu = ? WHERE id = ?");
            $upd->execute([$ten, $moTa, $thuTu, $id]);
            setFlash('success', 'Đã cập nhật danh mục "' . $ten . '" thành công.');
        }

        header('Location: danh_muc.php');
        exit;
    }
}

$dmSua = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM danh_muc_xe WHERE id = ? LIMIT 1");
    $stmtEdit->execute([(int) $_GET['edit']]);
    $dmSua = $stmtEdit->fetch() ?: null;
}

$dsDanhMuc = $pdo->query("
    SELECT dm.*, COUNT(x.id) AS so_mau_xe
    FROM danh_muc_xe dm
    LEFT JOIN xe_may x ON x.danh_muc_xe_id = dm.id
    GROUP BY dm.id
    ORDER BY dm.thu_tu ASC, dm.id ASC
")->fetchAll();

$pageTitle = 'Quản lý Danh mục loại xe - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1 fw-bold text-dark">Danh mục loại xe máy</h1>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <?= $dmSua ? 'Chỉnh sửa danh mục #' . (int) $dmSua['id'] : 'Thêm danh mục mới' ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="danh_muc.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="<?= $dmSua ? 'update' : 'create' ?>">
                    <?php if ($dmSua): ?>
                        <input type="hidden" name="id" value="<?= (int) $dmSua['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" name="ten" class="form-control" required
                               value="<?= e($dmSua['ten'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Mô tả ngắn</label>
                        <textarea name="mo_ta" class="form-control" rows="3" placeholder="Mô tả đặc điểm dòng xe..."><?= e($dmSua['mo_ta'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Thứ tự ưu tiên hiển thị</label>
                        <input type="number" name="thu_tu" class="form-control" min="0" max="99"
                               value="<?= e((string) ($dmSua['thu_tu'] ?? 1)) ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">
                            <i class="fa-solid fa-floppy-disk me-1"></i><?= $dmSua ? 'Lưu thay đổi' : 'Tạo danh mục' ?>
                        </button>
                        <?php if ($dmSua): ?>
                            <a href="danh_muc.php" class="btn btn-outline-secondary">Hủy</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">Danh sách danh mục hiện có (<?= count($dsDanhMuc) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Thứ tự</th>
                                <th>Tên danh mục</th>
                                <th>Mô tả</th>
                                <th class="text-center">Số mẫu xe</th>
                                <th class="pe-3 text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dsDanhMuc as $dm): ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-muted">#<?= (int) $dm['thu_tu'] ?></td>
                                <td class="fw-bold text-dark"><?= e($dm['ten']) ?></td>
                                <td class="text-muted small"><?= e($dm['mo_ta'] ?: '—') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= (int) $dm['so_mau_xe'] ?> mẫu xe
                                    </span>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="danh_muc.php?edit=<?= (int) $dm['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form method="POST" action="danh_muc.php" class="d-inline"
                                              onsubmit="return confirm('Xác nhận xóa danh mục <?= e($dm['ten']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $dm['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
