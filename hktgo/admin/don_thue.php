<?php
/**
 * admin/don_thue.php
 * Quản lý Đơn thuê xe: Duyệt giao xe ('Đang thuê'), Nhận trả xe ('Hoàn thành') và Hủy đơn ('Đã hủy')
 * Tự động đồng bộ lại trạng thái tồn kho xe sau mỗi lần chuyển trạng thái đơn
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: don_thue.php');
        exit;
    }

    $donId  = (int) ($_POST['don_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmtDon = $pdo->prepare("SELECT id, xe_may_id, trang_thai FROM don_thue WHERE id = ? LIMIT 1");
    $stmtDon->execute([$donId]);
    $donRow = $stmtDon->fetch();

    if (!$donRow) {
        setFlash('danger', 'Không tìm thấy đơn thuê cần cập nhật.');
    } else {
        $xeId = (int) $donRow['xe_may_id'];
        $ttHienTai = $donRow['trang_thai'];

        if ($action === 'duyet_giao_xe') {
            if (in_array($ttHienTai, ['Chờ duyệt', 'Chờ thanh toán'], true)) {
                $upd = $pdo->prepare("
                    UPDATE don_thue
                    SET trang_thai = 'Đang thuê',
                        thoi_diem_thanh_toan = COALESCE(thoi_diem_thanh_toan, NOW())
                    WHERE id = ?
                ");
                $upd->execute([$donId]);
                dongBoTrangThaiTatCaXe($pdo, $xeId);
                setFlash('success', 'Đã duyệt và bàn giao xe cho đơn #DH' . $donId . ' (Trạng thái: Đang thuê).');
            } else {
                setFlash('warning', 'Đơn #DH' . $donId . ' không ở trạng thái chờ duyệt để giao xe.');
            }
        } elseif ($action === 'nhan_tra_xe') {
            if ($ttHienTai === 'Đang thuê') {
                $upd = $pdo->prepare("UPDATE don_thue SET trang_thai = 'Hoàn thành' WHERE id = ?");
                $upd->execute([$donId]);
                dongBoTrangThaiTatCaXe($pdo, $xeId);
                setFlash('success', 'Đã xác nhận khách trả xe thành công cho đơn #DH' . $donId . ' (Hoàn thành & cộng vào doanh thu).');
            } else {
                setFlash('warning', 'Chỉ những đơn đang ở trạng thái "Đang thuê" mới có thể nhận trả xe.');
            }
        } elseif ($action === 'huy_don') {
            if ($ttHienTai !== 'Hoàn thành' && $ttHienTai !== 'Đã hủy') {
                $upd = $pdo->prepare("UPDATE don_thue SET trang_thai = 'Đã hủy' WHERE id = ?");
                $upd->execute([$donId]);
                dongBoTrangThaiTatCaXe($pdo, $xeId);
                setFlash('info', 'Đã hủy đơn thuê #DH' . $donId . ' và hoàn trả suất xe về kho.');
            } else {
                setFlash('warning', 'Không thể hủy đơn đã hoàn thành hoặc đã hủy trước đó.');
            }
        }
    }

    header('Location: don_thue.php');
    exit;
}

$locTrangThai = trim($_GET['trang_thai'] ?? '');
$tuKhoa       = trim($_GET['q'] ?? '');
$cacTrangThai = ['Chờ thanh toán', 'Chờ duyệt', 'Đang thuê', 'Hoàn thành', 'Đã hủy'];

$sql = "
    SELECT d.*, x.ten_xe, x.hang_xe, u.email AS user_email
    FROM don_thue d
    INNER JOIN xe_may x ON x.id = d.xe_may_id
    INNER JOIN users u ON u.id = d.user_id
    WHERE 1=1
";
$params = [];

if (in_array($locTrangThai, $cacTrangThai, true)) {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $locTrangThai;
}
if ($tuKhoa !== '') {
    $sql .= " AND (d.ten_khach_hang LIKE ? OR d.so_dien_thoai LIKE ? OR x.ten_xe LIKE ?)";
    $kw = '%' . $tuKhoa . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$sql .= " ORDER BY d.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsDon = $stmt->fetchAll();

function badgeDonAdmin(string $tt): string
{
    switch ($tt) {
        case 'Chờ thanh toán':
            return '<span class="badge badge-status-cho">Chờ thanh toán (15p)</span>';
        case 'Chờ duyệt':
            return '<span class="badge bg-warning text-dark">Chờ duyệt CK</span>';
        case 'Đang thuê':
            return '<span class="badge badge-status-thue">Đang thuê</span>';
        case 'Hoàn thành':
            return '<span class="badge badge-status-ranh">Hoàn thành</span>';
        case 'Đã hủy':
            return '<span class="badge badge-status-huy">Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">' . e($tt) . '</span>';
    }
}

$pageTitle = 'Quản lý Đơn thuê xe - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">Quản lý Đơn thuê xe</h1>
        <p class="text-muted mb-0">Đối soát chuyển khoản, duyệt bàn giao xe, xác nhận trả xe hoàn thành và hủy đơn</p>
    </div>
</div>

<!-- Thanh tìm kiếm & lọc trạng thái -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="don_thue.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Tìm theo tên khách, SĐT hoặc tên mẫu xe</label>
                <input type="text" name="q" class="form-control" value="<?= e($tuKhoa) ?>" placeholder="Nhập từ khóa cần tra cứu...">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Lọc theo trạng thái đơn</label>
                <select name="trang_thai" class="form-select">
                    <option value="">-- Tất cả trạng thái --</option>
                    <?php foreach ($cacTrangThai as $tt): ?>
                        <option value="<?= e($tt) ?>" <?= $locTrangThai === $tt ? 'selected' : '' ?>><?= e($tt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i>Lọc đơn
                </button>
                <a href="don_thue.php" class="btn btn-outline-secondary" title="Làm mới">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Bảng danh sách đơn thuê -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">Danh sách đơn thuê (<?= count($dsDon) ?> đơn)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Xe thuê</th>
                        <th>Lịch nhận / trả</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th class="pe-3 text-end">Thao tác nghiệp vụ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dsDon)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Không có đơn thuê nào phù hợp.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dsDon as $d): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary">#DH<?= (int) $d['id'] ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($d['ten_khach_hang']) ?></div>
                                <small class="text-muted"><?= e($d['so_dien_thoai']) ?> &bull; <?= e($d['user_email']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($d['ten_xe']) ?></div>
                                <small class="text-muted">Hãng: <?= e($d['hang_xe']) ?></small>
                            </td>
                            <td class="small">
                                <div><strong><?= date('d/m/Y', strtotime($d['ngay_nhan'])) ?></strong> &rarr; <strong><?= date('d/m/Y', strtotime($d['ngay_tra'])) ?></strong> (<?= (int) $d['so_ngay'] ?> ngày)</div>
                                <div class="text-muted">Nhận: <?= e($d['diem_nhan']) ?></div>
                            </td>
                            <td class="fw-bold text-dark"><?= formatVnd($d['tong_tien']) ?></td>
                            <td>
                                <?= badgeDonAdmin($d['trang_thai']) ?>
                                <?php if (!empty($d['thoi_diem_thanh_toan'])): ?>
                                    <div class="small text-success mt-1">
                                        <i class="fa-solid fa-check me-1"></i>Báo CK: <?= date('H:i d/m', strtotime($d['thoi_diem_thanh_toan'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end">
                                <?php if (in_array($d['trang_thai'], ['Chờ duyệt', 'Chờ thanh toán'], true)): ?>
                                    <form method="POST" action="don_thue.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="don_id" value="<?= (int) $d['id'] ?>">
                                        <input type="hidden" name="action" value="duyet_giao_xe">
                                        <button type="submit" class="btn btn-sm btn-primary fw-semibold me-1">
                                            <i class="fa-solid fa-key me-1"></i>Duyệt giao xe
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($d['trang_thai'] === 'Đang thuê'): ?>
                                    <form method="POST" action="don_thue.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="don_id" value="<?= (int) $d['id'] ?>">
                                        <input type="hidden" name="action" value="nhan_tra_xe">
                                        <button type="submit" class="btn btn-sm btn-success fw-semibold me-1">
                                            <i class="fa-solid fa-circle-check me-1"></i>Nhận trả xe
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!in_array($d['trang_thai'], ['Hoàn thành', 'Đã hủy'], true)): ?>
                                    <form method="POST" action="don_thue.php" class="d-inline"
                                          onsubmit="return confirm('Xác nhận hủy đơn #DH<?= (int) $d['id'] ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="don_id" value="<?= (int) $d['id'] ?>">
                                        <input type="hidden" name="action" value="huy_don">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-ban me-1"></i>Hủy
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($d['trang_thai'], ['Hoàn thành', 'Đã hủy'], true)): ?>
                                    <span class="text-muted small">Đã đóng</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
