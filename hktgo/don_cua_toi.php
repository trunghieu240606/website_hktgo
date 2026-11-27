<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'huy_don') {
    if (verifyCsrf()) {
        $donId = (int) ($_POST['don_id'] ?? 0);
        $stmtCheck = $pdo->prepare("
            SELECT id, xe_may_id, trang_thai, trang_thai_coc, tong_tien, tien_coc
            FROM don_thue
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmtCheck->execute([$donId, (int) $user['id']]);
        $donHuy = $stmtCheck->fetch();

        if ($donHuy && in_array($donHuy['trang_thai'], ['Chờ thanh toán', 'Chờ duyệt'], true)) {
            $daThanhToan = ($donHuy['trang_thai'] === 'Chờ duyệt' || ($donHuy['trang_thai_coc'] ?? '') === 'Đang giữ cọc');
            $ttCocMoi    = $daThanhToan ? 'Đã hoàn đủ' : 'Chưa thu';
            $ghiChuHuy   = $daThanhToan
                ? 'Khách hàng hủy đơn trước khi nhận xe — Đã hoàn trả lại toàn bộ tiền cọc (' . formatVnd($donHuy['tien_coc']) . ') & tiền thuê.'
                : null;

            $upd = $pdo->prepare("
                UPDATE don_thue
                SET trang_thai = 'Đã hủy',
                    trang_thai_coc = ?,
                    ghi_chu_su_co = COALESCE(?, ghi_chu_su_co)
                WHERE id = ?
            ");
            $upd->execute([$ttCocMoi, $ghiChuHuy, $donId]);

            if (!empty($donHuy['xe_may_id'])) {
                dongBoTrangThaiTatCaXe($pdo, (int) $donHuy['xe_may_id']);
            }

            if ($daThanhToan) {
                setFlash('success', 'Đã hủy đơn thuê #DH' . $donId . ', hoàn trả suất xe về kho và hoàn lại đủ ' . formatVnd($donHuy['tien_coc']) . ' tiền cọc!');
            } else {
                setFlash('info', 'Đã hủy đơn giữ chỗ #DH' . $donId . ' (đơn chưa phát sinh thanh toán) và hoàn trả xe về kho.');
            }
        }
    }
    header('Location: don_cua_toi.php');
    exit;
}

$locTrangThai = trim($_GET['trang_thai'] ?? '');
$cacTrangThai = ['Chờ thanh toán', 'Chờ duyệt', 'Đang thuê', 'Hoàn thành', 'Đã hủy'];

$sql = "
    SELECT d.*, x.ten_xe, x.hang_xe, x.hinh_anh
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
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

function badgeTrangThaiKhach(string $tt): string
{
    switch ($tt) {
        case 'Chờ thanh toán':
            return '<span class="badge-status-cho">Chờ thanh toán</span>';
        case 'Chờ duyệt':
            return '<span class="badge badge-warning text-dark px-2 py-1">Chờ duyệt</span>';
        case 'Đang thuê':
            return '<span class="badge-status-thue">Đang thuê</span>';
        case 'Hoàn thành':
            return '<span class="badge-status-ranh">Hoàn thành</span>';
        case 'Đã hủy':
            return '<span class="badge-status-huy">Đã hủy</span>';
        default:
            return '<span class="badge badge-secondary">' . e($tt) . '</span>';
    }
}

$todayDate = date('Y-m-d');
$pageTitle = 'Đơn Thuê Xe Của Tôi - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
        <div>
            <h2 class="font-weight-bold text-white mb-1">
                <i class="fa-solid fa-rectangle-list mr-2" style="color:#01d28e;"></i>Đơn Thuê Xe Của Tôi
            </h2>
            <p class="text-white-50 mb-0">Theo dõi lịch trình, tiền đặt cọc, biên bản nghiệm thu và hợp đồng điện tử</p>
        </div>
        <a href="index.php#dan-xe" class="btn btn-success font-weight-bold" style="background:#01d28e;border-color:#01d28e;">
            <i class="fa-solid fa-plus mr-1"></i>Đặt thêm xe mới
        </a>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (($user['trang_thai'] ?? 'Hoạt động') === 'Bị khóa'): ?>
            <div class="alert alert-danger shadow-sm mb-4">
                <h6 class="font-weight-bold mb-1">
                    <i class="fa-solid fa-lock mr-1"></i>Tài khoản của bạn đang bị tạm khóa quyền đặt xe
                </h6>
                <p class="mb-0 small">
                    Hệ thống ghi nhận đơn thuê của bạn có phát sinh chi phí hư hỏng hoặc vi phạm phạt nguội vượt quá tiền đặt cọc chưa được tất toán. Vui lòng xem chi tiết bên dưới và liên hệ cửa hàng HKTGO (0365.145.525) để giải quyết.
                </p>
            </div>
        <?php endif; ?>

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

        <div class="content-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="pl-4">Mã đơn</th>
                            <th>Mẫu xe thuê</th>
                            <th>Lịch trình &amp; Điểm giao nhận</th>
                            <th>Chi tiết thanh toán &amp; Cọc</th>
                            <th>Trạng thái &amp; Biên bản</th>
                            <th class="pr-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dsDon)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    Bạn chưa có đơn thuê xe nào trong mục này.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dsDon as $d): ?>
                            <?php
                                $idDon       = (int) $d['id'];
                                $tienCoc     = (float) ($d['tien_coc'] ?? 0);
                                $tienThue    = max(0, (float) $d['tong_tien'] - $tienCoc);
                                $phiQuaHan   = (float) ($d['phi_qua_han'] ?? 0);
                                $phiPhatSinh = (float) ($d['phi_phat_sinh'] ?? 0);
                                $tongKhauTru = $phiQuaHan + $phiPhatSinh;
                                $ttCoc       = $d['trang_thai_coc'] ?? 'Chưa thu';

                                $quaHanDangThue = ($d['trang_thai'] === 'Đang thuê' && $todayDate > $d['ngay_tra']);
                            ?>
                            <tr>
                                <td class="pl-4 font-weight-bold text-primary">
                                    #DH<?= $idDon ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= e($d['hinh_anh'] ?: 'images/car-1.jpg') ?>"
                                             alt="<?= e($d['ten_xe'] ?? 'Xe máy') ?>"
                                             style="width:64px;height:46px;object-fit:cover;border-radius:6px;"
                                             class="mr-2"
                                             onerror="this.src='images/car-1.jpg'">
                                        <div>
                                            <div class="font-weight-bold text-dark"><?= e($d['ten_xe'] ?: 'Mẫu xe đã ngừng kinh doanh') ?></div>
                                            <small class="text-muted"><?= e($d['hang_xe'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <div>
                                        <strong><?= date('d/m/Y', strtotime($d['ngay_nhan'])) ?></strong> &rarr;
                                        <strong><?= date('d/m/Y', strtotime($d['ngay_tra'])) ?></strong>
                                        (<?= (int) $d['so_ngay'] ?> ngày)
                                    </div>
                                    <div class="text-muted"><i class="fa-solid fa-location-dot text-success mr-1"></i>Nhận: <?= e($d['diem_nhan']) ?></div>
                                    <div class="text-muted"><i class="fa-solid fa-flag-checkered text-primary mr-1"></i>Trả: <?= e($d['diem_tra']) ?></div>
                                    <?php if ($quaHanDangThue): ?>
                                        <span class="badge badge-danger mt-1">Đã quá hạn trả xe!</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <div>Tổng trả: <strong class="text-dark"><?= formatVnd($d['tong_tien']) ?></strong></div>
                                    <div class="text-muted">Thuê: <?= formatVnd($tienThue) ?> &bull; Cọc: <strong style="color:#b45309;"><?= formatVnd($tienCoc) ?></strong></div>

                                    <?php if ($tongKhauTru > 0): ?>
                                        <div class="mt-1 px-2 py-1 rounded" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;">
                                            <strong>Khấu trừ vi phạm/hư hỏng: -<?= formatVnd($tongKhauTru) ?></strong>
                                            <?php if ($phiQuaHan > 0): ?>
                                                <div>&bull; Phí quá hạn: <?= formatVnd($phiQuaHan) ?></div>
                                            <?php endif; ?>
                                            <?php if ($phiPhatSinh > 0): ?>
                                                <div>&bull; Phí hư hỏng / phạt nguội: <?= formatVnd($phiPhatSinh) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <div class="mb-1"><?= badgeTrangThaiKhach($d['trang_thai']) ?></div>
                                    <div>
                                        <span class="badge <?= $ttCoc === 'Đã khấu trừ phạt' ? 'badge-danger' : ($ttCoc === 'Đã hoàn đủ' ? 'badge-success' : 'badge-light border text-dark') ?>">
                                            Cọc: <?= e($ttCoc) ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($d['han_hoan_coc']) && $ttCoc === 'Giữ cọc phạt nguội 15 ngày'): ?>
                                        <div class="text-muted mt-1">
                                            Dự kiến hoàn nốt 500k: <strong><?= date('d/m/Y', strtotime($d['han_hoan_coc'])) ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($d['ghi_chu_su_co'])): ?>
                                        <div class="mt-1 p-2 rounded text-danger" style="background:#fff5f5;border-left:3px solid #dc2626;max-width:250px;">
                                            <strong>Biên bản từ cửa hàng:</strong><br>
                                            <?= e($d['ghi_chu_su_co']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="pr-4 text-right">
                                    <div class="d-inline-flex flex-wrap justify-content-end" style="gap: 6px;">
                                        <a href="hop_dong.php?id=<?= $idDon ?>" target="_blank" class="btn btn-sm btn-outline-primary font-weight-bold text-nowrap">
                                            <i class="fa-solid fa-file-contract mr-1"></i>Hợp đồng
                                        </a>

                                        <?php if ($d['trang_thai'] === 'Chờ thanh toán'): ?>
                                            <a href="thanh_toan.php?id=<?= $idDon ?>" class="btn btn-sm btn-success font-weight-bold text-nowrap" style="background:#01d28e;border-color:#01d28e;">
                                                <i class="fa-solid fa-qrcode mr-1"></i>Thanh toán QR
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array($d['trang_thai'], ['Chờ thanh toán', 'Chờ duyệt'], true)): ?>
                                            <form method="POST" action="don_cua_toi.php" class="d-inline"
                                                  onsubmit="return confirm('Bạn có chắc muốn hủy đơn thuê #DH<?= $idDon ?> không?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="huy_don">
                                                <input type="hidden" name="don_id" value="<?= $idDon ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Hủy đơn</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
