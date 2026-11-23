<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: xe.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmtRef = $pdo->prepare("SELECT COUNT(*) FROM don_thue WHERE xe_may_id = ?");
        $stmtRef->execute([$id]);
        if ((int) $stmtRef->fetchColumn() > 0) {
            setFlash('warning', 'Không thể xóa mẫu xe này vì đã phát sinh đơn thuê trong lịch sử! Bạn có thể chỉnh tổng số lượng về 0 (khi không còn đơn đang giữ xe) nếu ngừng cho thuê.');
        } else {
            $del = $pdo->prepare("DELETE FROM xe_may WHERE id = ?");
            $del->execute([$id]);
            setFlash('success', 'Đã xóa mẫu xe khỏi kho thành công.');
        }
        header('Location: xe.php');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id          = (int) ($_POST['id'] ?? 0);
        $tenXe       = trim(preg_replace('/\s+/', ' ', $_POST['ten_xe'] ?? ''));
        $hangXe      = trim($_POST['hang_xe'] ?? 'Honda');
        $danhMucId   = (int) ($_POST['danh_muc_xe_id'] ?? 0);
        $giaThue     = (float) ($_POST['gia_thue'] ?? 0);
        $tienCoc     = max(0, (float) ($_POST['tien_coc'] ?? 1000000));
        $soLuong     = (int) ($_POST['so_luong'] ?? 1);
        $hinhAnhText = trim($_POST['hinh_anh_text'] ?? '');

        $minSoLuong = ($action === 'create') ? 1 : 0;
        if ($tenXe === '' || $hangXe === '' || $giaThue <= 0 || $soLuong < $minSoLuong) {
            setFlash('danger', 'Vui lòng nhập đầy đủ Tên mẫu xe, Hãng sản xuất, Đơn giá (> 0) và Tổng số lượng hợp lệ.');
            header('Location: xe.php' . ($action === 'update' ? '?edit=' . $id : ''));
            exit;
        }

        $stmtDup = $pdo->prepare("SELECT id, ten_xe FROM xe_may WHERE LOWER(ten_xe) = LOWER(?) AND id <> ? LIMIT 1");
        $stmtDup->execute([$tenXe, $id]);
        if ($dupRow = $stmtDup->fetch()) {
            setFlash('danger', 'Mẫu xe "' . $dupRow['ten_xe'] . '" đã tồn tại trong kho! Hãy sửa số lượng của mẫu đó thay vì thêm trùng.');
            header('Location: xe.php' . ($action === 'update' ? '?edit=' . $id : ''));
            exit;
        }
        
        if ($action === 'update') {
            $stmtHold = $pdo->prepare("
                SELECT COUNT(*) FROM don_thue
                WHERE xe_may_id = ?
                  AND trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
            ");
            $stmtHold->execute([$id]);
            $soDonDangGiu = (int) $stmtHold->fetchColumn();

            if ($soLuong < $soDonDangGiu) {
                setFlash('warning', 'Không thể giảm tổng số lượng xuống ' . $soLuong . ' vì mẫu xe này đang có ' . $soDonDangGiu . ' đơn giữ chỗ hoặc đang thuê!');
                header('Location: xe.php?edit=' . $id);
                exit;
            }
        }

        $hinhAnhPath = $hinhAnhText !== '' ? $hinhAnhText : 'images/car-1.jpg';
        if (!empty($_FILES['hinh_anh_file']['name']) && $_FILES['hinh_anh_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['hinh_anh_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $uploadDir = dirname(__DIR__) . '/images/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $newName = 'xe_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['hinh_anh_file']['tmp_name'], $uploadDir . '/' . $newName)) {
                    $hinhAnhPath = 'images/uploads/' . $newName;
                }
            } else {
                setFlash('warning', 'Chỉ chấp nhận tệp ảnh định dạng JPG, PNG hoặc WEBP.');
                header('Location: xe.php' . ($action === 'update' ? '?edit=' . $id : ''));
                exit;
            }
        }

        $dmParam = $danhMucId > 0 ? $danhMucId : null;

        if ($action === 'create') {
            $ins = $pdo->prepare("
                INSERT INTO xe_may (danh_muc_xe_id, ten_xe, hang_xe, gia_thue, tien_coc, so_luong, trang_thai, hinh_anh)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ttBanDau = $soLuong > 0 ? 'Đang rảnh' : 'Hết xe';
            $ins->execute([$dmParam, $tenXe, $hangXe, $giaThue, $tienCoc, $soLuong, $ttBanDau, $hinhAnhPath]);
            $newXeId = (int) $pdo->lastInsertId();
            dongBoTrangThaiTatCaXe($pdo, $newXeId);
            setFlash('success', 'Đã thêm mẫu xe mới "' . $tenXe . '" vào kho thành công.');
        } else {
            $upd = $pdo->prepare("
                UPDATE xe_may
                SET danh_muc_xe_id = ?, ten_xe = ?, hang_xe = ?, gia_thue = ?, tien_coc = ?, so_luong = ?, hinh_anh = ?
                WHERE id = ?
            ");
            $upd->execute([$dmParam, $tenXe, $hangXe, $giaThue, $tienCoc, $soLuong, $hinhAnhPath, $id]);
            dongBoTrangThaiTatCaXe($pdo, $id);
            setFlash('success', 'Đã cập nhật thông tin mẫu xe #' . $id . ' thành công.');
        }

        header('Location: xe.php');
        exit;
    }
}

$dsDanhMuc = $pdo->query("SELECT id, ten FROM danh_muc_xe ORDER BY thu_tu ASC, ten ASC")->fetchAll();

$xeSua = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM xe_may WHERE id = ? LIMIT 1");
    $stmtEdit->execute([(int) $_GET['edit']]);
    $xeSua = $stmtEdit->fetch() ?: null;
}

$dsXe = $pdo->query("
    SELECT x.*, dm.ten AS ten_danh_muc,
           COALESCE(d.dang_giu, 0) AS dang_giu,
           GREATEST(0, x.so_luong - COALESCE(d.dang_giu, 0)) AS con_lai
    FROM xe_may x
    LEFT JOIN danh_muc_xe dm ON dm.id = x.danh_muc_xe_id
    LEFT JOIN (
        SELECT xe_may_id, COUNT(*) AS dang_giu
        FROM don_thue
        WHERE trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
        GROUP BY xe_may_id
    ) d ON d.xe_may_id = x.id
    ORDER BY x.id ASC
")->fetchAll();

$pageTitle = 'Quản lý Kho xe - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">Quản lý Kho xe máy</h1>
        <p class="text-muted mb-0">Mỗi mẫu xe có tên duy nhất; khi nhập thêm xe cùng loại chỉ cần tăng tổng số lượng của mẫu đó</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <?php if ($xeSua): ?>
                        <i class="fa-solid fa-pen-to-square text-primary me-1"></i>Sửa mẫu xe #<?= (int) $xeSua['id'] ?>
                    <?php else: ?>
                        <i class="fa-solid fa-plus-circle text-primary me-1"></i>Thêm mẫu xe mới
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="xe.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="<?= $xeSua ? 'update' : 'create' ?>">
                    <?php if ($xeSua): ?>
                        <input type="hidden" name="id" value="<?= (int) $xeSua['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tên mẫu xe (Duy nhất) <span class="text-danger">*</span></label>
                        <input type="text" name="ten_xe" class="form-control" required
                               value="<?= e($xeSua['ten_xe'] ?? '') ?>" placeholder="VD: Honda Air Blade 160">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Hãng sản xuất <span class="text-danger">*</span></label>
                            <input type="text" name="hang_xe" class="form-control" required
                                   value="<?= e($xeSua['hang_xe'] ?? 'Honda') ?>" placeholder="Honda, Yamaha...">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Danh mục loại xe</label>
                            <select name="danh_muc_xe_id" class="form-select">
                                <option value="0">-- Chọn loại --</option>
                                <?php foreach ($dsDanhMuc as $dm): ?>
                                    <option value="<?= (int) $dm['id'] ?>"
                                        <?= ((int) ($xeSua['danh_muc_xe_id'] ?? 0) === (int) $dm['id']) ? 'selected' : '' ?>>
                                        <?= e($dm['ten']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label fw-semibold small">Giá thuê / ngày (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="gia_thue" class="form-control" min="10000" step="10000" required
                                   value="<?= e((string) ($xeSua['gia_thue'] ?? 150000)) ?>">
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold small">Tổng số lượng <span class="text-danger">*</span></label>
                            <input type="number" name="so_luong" class="form-control" min="<?= $xeSua ? '0' : '1' ?>" max="200" required
                                   value="<?= e((string) ($xeSua['so_luong'] ?? 5)) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tiền đặt cọc tài sản (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" name="tien_coc" class="form-control" min="0" step="100000" required
                               value="<?= e((string) ($xeSua['tien_coc'] ?? 1000000)) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tải ảnh xe lên (tùy chọn)</label>
                        <input type="file" name="hinh_anh_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Hoặc đường dẫn ảnh sẵn có</label>
                        <input type="text" name="hinh_anh_text" class="form-control form-control-sm"
                               value="<?= e($xeSua['hinh_anh'] ?? 'images/car-1.jpg') ?>" placeholder="images/car-1.jpg">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">
                            <i class="fa-solid fa-floppy-disk me-1"></i><?= $xeSua ? 'Lưu cập nhật' : 'Thêm vào kho' ?>
                        </button>
                        <?php if ($xeSua): ?>
                            <a href="xe.php" class="btn btn-outline-secondary">Hủy</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">Danh sách mẫu xe trong kho (<?= count($dsXe) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ảnh</th>
                                <th>Mẫu xe &amp; Hãng</th>
                                <th>Danh mục</th>
                                <th>Giá thuê &amp; Cọc</th>
                                <th>Tồn kho thực</th>
                                <th class="pe-3 text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dsXe as $x):
                                $conLai  = (int) $x['con_lai'];
                                $dangGiu = (int) $x['dang_giu'];
                                $anhSrc  = '../' . ltrim($x['hinh_anh'] ?: 'images/car-1.jpg', '/');
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <img src="<?= e($anhSrc) ?>" alt="<?= e($x['ten_xe']) ?>" class="vehicle-thumb"
                                         onerror="this.src='../images/car-1.jpg'">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($x['ten_xe']) ?></div>
                                    <small class="text-muted">Hãng: <?= e($x['hang_xe']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($x['ten_danh_muc'] ?? 'Chưa gán') ?></span>
                                </td>
                                <td class="price-nowrap">
                                    <div class="fw-semibold text-primary"><?= formatVnd($x['gia_thue']) ?>/ngày</div>
                                    <small class="text-muted">Cọc: <?= formatVnd($x['tien_coc'] ?? 1000000) ?></small>
                                </td>
                                <td>
                                    <?php if ($conLai > 0): ?>
                                        <span class="badge badge-status-ranh">Còn <?= $conLai ?>/<?= (int) $x['so_luong'] ?> xe</span>
                                    <?php else: ?>
                                        <span class="badge badge-status-huy">Hết xe (0/<?= (int) $x['so_luong'] ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($dangGiu > 0): ?>
                                        <div class="small text-muted mt-1"><?= $dangGiu ?> đang giữ/thuê</div>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="xe.php?edit=<?= (int) $x['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa xe">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form method="POST" action="xe.php" class="d-inline"
                                              onsubmit="return confirm('Xác nhận xóa mẫu xe <?= e($x['ten_xe']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa xe">
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
