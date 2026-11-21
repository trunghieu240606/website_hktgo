<?php
/**
 * don_cua_toi.php
 * Trang khách hàng tra cứu lịch sử đơn thuê của chính mình,
 * tiếp tục thanh toán đơn đang giữ chỗ 15 phút hoặc chủ động hủy đơn
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);

// Xử lý yêu cầu hủy đơn của khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'huy_don') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ.');
        header('Location: don_cua_toi.php');
        exit;
    }

    $donId = (int) ($_POST['don_id'] ?? 0);
    $stmtCheck = $pdo->prepare("SELECT id, xe_may_id, trang_thai FROM don_thue WHERE id = ? AND user_id = ? LIMIT 1");
    $stmtCheck->execute([$donId, (int) $user['id']]);
    $donRow = $stmtCheck->fetch();

    if (!$donRow) {
        setFlash('danger', 'Không tìm thấy đơn thuê của bạn.');
    } elseif (!in_array($donRow['trang_thai'], ['Chờ thanh toán', 'Chờ duyệt'], true)) {
        setFlash('warning', 'Chỉ có thể hủy đơn khi đang ở trạng thái "Chờ thanh toán" hoặc "Chờ duyệt".');
    } else {
        $upd = $pdo->prepare("UPDATE don_thue SET trang_thai = 'Đã hủy' WHERE id = ? AND user_id = ?");
        $upd->execute([$donId, (int) $user['id']]);
        dongBoTrangThaiTatCaXe($pdo, (int) $donRow['xe_may_id']);
        setFlash('success', 'Đã hủy đơn thuê #DH' . $donId . ' thành công và hoàn trả suất xe về kho.');
    }

    header('Location: don_cua_toi.php');
    exit;
}

$locTrangThai = trim($_GET['trang_thai'] ?? '');
$cacTrangThai = ['Chờ thanh toán', 'Chờ duyệt', 'Đang thuê', 'Hoàn thành', 'Đã hủy'];

$sql = "
    SELECT d.*, x.ten_xe, x.hang_xe, x.hinh_anh
    FROM don_thue d
    INNER JOIN xe_may x ON x.id = d.xe_may_id
    WHERE d.user_id = ?
";
$params = [(int) $user['id']];

if (in_array($locTrangThai, $cacTrangThai, true)) {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $locTrangThai;
}

$sql .= " ORDER BY d.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsDon = $stmt->fetchAll();

function badgeDonKhach(string $tt): string
{
    switch ($tt) {
        case 'Chờ thanh toán':
            return '<span class="badge badge-warning px-2 py-1">Chờ thanh toán (15p)</span>';
        case 'Chờ duyệt':
            return '<span class="badge badge-info px-2 py-1">Chờ duyệt giao xe</span>';
        case 'Đang thuê':
            return '<span class="badge badge-primary px-2 py-1">Đang thuê xe</span>';
        case 'Hoàn thành':
            return '<span class="badge badge-success px-2 py-1">Hoàn thành</span>';
        case 'Đã hủy':
            return '<span class="badge badge-secondary px-2 py-1">Đã hủy</span>';
        default:
            return '<span class="badge badge-light border">' . e($tt) . '</span>';
    }
}

$pageTitle = 'Đơn thuê xe của tôi - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="font-weight-bold text-white mb-1">
                <i class="fa-solid fa-rectangle-list mr-2" style="color:#01d28e;"></i>Đơn Thuê Xe Của Tôi
            </h2>
        </div>
        <a href="index.php" class="btn btn-success font-weight-bold mt-3 mt-md-0" style="background:#01d28e;border-color:#01d28e;">
            <i class="fa-solid fa-plus mr-1"></i>Đặt thêm xe mới
        </a>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <!-- Thanh lọc nhanh theo trạng thái đơn -->
        <div class="mb-4 d-flex flex-wrap" style="gap: 8px;">
            <a href="don_cua_toi.php" class="btn btn-sm <?= $locTrangThai === '' ? 'btn-primary font-weight-bold' : 'btn-outline-secondary bg-white' ?>">
                Tất cả đơn
            </a>
            <?php foreach ($cacTrangThai as $tt): ?>
                <a href="don_cua_toi.php?trang_thai=<?= urlencode($tt) ?>"
                   class="btn btn-sm <?= $locTrangThai === $tt ? 'btn-primary font-weight-bold' : 'btn-outline-secondary bg-white' ?>">
                    <?= e($tt) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($dsDon)): ?>
            <div class="content-card text-center py-5">
                <i class="fa-solid fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted mb-3">Bạn chưa có đơn thuê xe nào trong mục này.</h5>
                <a href="index.php" class="btn btn-primary font-weight-bold">
                    <i class="fa-solid fa-motorcycle mr-1"></i>Chọn xe trên trang chủ ngay
                </a>
            </div>
        <?php else: ?>
            <div class="content-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Mẫu xe thuê</th>
                                <th>Lịch trình thuê</th>
                                <th>Điểm nhận / trả</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dsDon as $d): ?>
                            <tr>
                                <td class="font-weight-bold text-primary">#DH<?= (int) $d['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= e($d['hinh_anh'] ?: 'images/car-1.jpg') ?>" alt="<?= e($d['ten_xe']) ?>"
                                             style="width:56px;height:40px;object-fit:cover;border-radius:6px;" class="mr-2"
                                             onerror="this.src='images/car-1.jpg'">
                                        <div>
                                            <div class="font-weight-bold text-dark"><?= e($d['ten_xe']) ?></div>
                                            <small class="text-muted"><?= e($d['hang_xe']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <div><strong><?= date('d/m/Y', strtotime($d['ngay_nhan'])) ?></strong> &rarr; <strong><?= date('d/m/Y', strtotime($d['ngay_tra'])) ?></strong></div>
                                    <span class="text-muted">(<?= (int) $d['so_ngay'] ?> ngày)</span>
                                </td>
                                <td class="small">
                                    <div><i class="fa-solid fa-location-dot text-success mr-1"></i><?= e($d['diem_nhan']) ?></div>
                                    <div class="text-muted"><i class="fa-solid fa-flag-checkered mr-1"></i><?= e($d['diem_tra']) ?></div>
                                </td>
                                <td class="font-weight-bold text-dark"><?= formatVnd($d['tong_tien']) ?></td>
                                <td>
                                    <?= badgeDonKhach($d['trang_thai']) ?>
                                    <?php if ($d['trang_thai'] === 'Chờ thanh toán' && !empty($d['han_thanh_toan'])): ?>
                                        <div class="small text-danger mt-1">
                                            Hạn: <?= date('H:i d/m', strtotime($d['han_thanh_toan'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($d['trang_thai'] === 'Chờ thanh toán'): ?>
                                        <a href="thanh_toan.php?id=<?= (int) $d['id'] ?>" class="btn btn-sm btn-success font-weight-bold mb-1" style="background:#01d28e;border-color:#01d28e;">
                                            <i class="fa-solid fa-qrcode mr-1"></i>Thanh toán QR
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($d['trang_thai'], ['Chờ thanh toán', 'Chờ duyệt'], true)): ?>
                                        <form method="POST" action="don_cua_toi.php" class="d-inline"
                                              onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn #DH<?= (int) $d['id'] ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="huy_don">
                                            <input type="hidden" name="don_id" value="<?= (int) $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger mb-1">
                                                <i class="fa-solid fa-xmark mr-1"></i>Hủy đơn
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
