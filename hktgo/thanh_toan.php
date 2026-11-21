<?php
/**
 * thanh_toan.php
 * Trang hiển thị mã QR chuyển khoản (VietQR + ảnh tĩnh dự phòng), đồng hồ đếm ngược 15 phút
 * và nút "Tôi đã chuyển khoản" chuyển đơn sang trạng thái 'Chờ duyệt'
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);
$donId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($donId <= 0) {
    setFlash('warning', 'Không tìm thấy mã đơn thuê cần thanh toán.');
    header('Location: don_cua_toi.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.*, x.ten_xe, x.hang_xe, x.gia_thue, x.hinh_anh,
           GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), d.han_thanh_toan)) AS giay_con_lai
    FROM don_thue d
    INNER JOIN xe_may x ON x.id = d.xe_may_id
    WHERE d.id = ?
    LIMIT 1
");
$stmt->execute([$donId]);
$don = $stmt->fetch();

if (!$don || ((int) $don['user_id'] !== (int) $user['id'] && !isAdmin())) {
    setFlash('danger', 'Bạn không có quyền truy cập đơn thuê này.');
    header('Location: don_cua_toi.php');
    exit;
}

// Xử lý khi khách hàng bấm "Tôi đã chuyển khoản" hoặc "Hủy đơn"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Phiên xác thực không hợp lệ, vui lòng thử lại.');
        header('Location: thanh_toan.php?id=' . $donId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'xac_nhan_ck') {
        if ($don['trang_thai'] !== 'Chờ thanh toán') {
            setFlash('info', 'Đơn thuê #DH' . $donId . ' hiện đang ở trạng thái "' . $don['trang_thai'] . '".');
            header('Location: don_cua_toi.php');
            exit;
        }
        if ((int) $don['giay_con_lai'] <= 0) {
            tuDongHuyDonQuaHanVaDongBoKho($pdo);
            setFlash('danger', 'Đơn thuê #DH' . $donId . ' đã quá thời hạn giữ chỗ 15 phút nên hệ thống đã tự động hủy. Vui lòng đặt lại đơn mới!');
            header('Location: don_cua_toi.php');
            exit;
        }

        $upd = $pdo->prepare("
            UPDATE don_thue
            SET trang_thai = 'Chờ duyệt',
                thoi_diem_thanh_toan = NOW()
            WHERE id = ? AND trang_thai = 'Chờ thanh toán'
        ");
        $upd->execute([$donId]);
        dongBoTrangThaiTatCaXe($pdo, (int) $don['xe_may_id']);

        setFlash('success', 'Đã ghi nhận thông báo chuyển khoản cho đơn #DH' . $donId . '! Quản trị viên sẽ kiểm tra và duyệt giao xe cho bạn.');
        header('Location: don_cua_toi.php');
        exit;
    }

    if ($action === 'huy_don') {
        if (in_array($don['trang_thai'], ['Chờ thanh toán', 'Chờ duyệt'], true)) {
            $upd = $pdo->prepare("UPDATE don_thue SET trang_thai = 'Đã hủy' WHERE id = ?");
            $upd->execute([$donId]);
            dongBoTrangThaiTatCaXe($pdo, (int) $don['xe_may_id']);
            setFlash('info', 'Đã hủy đơn thuê #DH' . $donId . ' và hoàn trả suất xe về kho.');
        }
        header('Location: don_cua_toi.php');
        exit;
    }
}

$noiDungCk = 'HKTGO DH' . $don['id'] . ' ' . $don['so_dien_thoai'];
$soTienInt = (int) round((float) $don['tong_tien']);
$vietQrUrl = 'https://img.vietqr.io/image/'
    . rawurlencode(VIETQR_BANK_ID . '-' . VIETQR_ACCOUNT_NO . '-compact2.png')
    . '?amount=' . $soTienInt
    . '&addInfo=' . rawurlencode($noiDungCk)
    . '&accountName=' . rawurlencode(VIETQR_ACCOUNT_NAME);

$giayConLai = (int) $don['giay_con_lai'];
$dangChoThanhToan = ($don['trang_thai'] === 'Chờ thanh toán' && $giayConLai > 0);

$pageTitle = 'Thanh toán QR đơn #DH' . $don['id'] . ' - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container">
        <h2 class="font-weight-bold text-white mb-1">
            <i class="fa-solid fa-qrcode mr-2" style="color:#01d28e;"></i>Thanh Toán Chuyển Khoản Mã QR
        </h2>
        <p class="text-white-50 mb-0">Mã đơn thuê: <strong>#DH<?= (int) $don['id'] ?></strong> — Quét mã QR trên ứng dụng Ngân hàng và xác nhận chuyển khoản</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Cột trái: Mã QR và đồng hồ đếm ngược 15 phút -->
            <div class="col-lg-6 mb-4">
                <div class="content-card text-center h-100">
                    <?php if ($dangChoThanhToan): ?>
                        <div class="alert alert-warning d-inline-block px-4 py-2 mb-3">
                            <i class="fa-solid fa-hourglass-half mr-1"></i>
                            Thời gian giữ chỗ còn lại:
                            <strong id="countdownTimer" class="text-danger" style="font-size:18px;">--:--</strong>
                        </div>

                        <div class="my-2">
                            <img src="<?= e($vietQrUrl) ?>"
                                 alt="Mã VietQR thanh toán đơn #DH<?= (int) $don['id'] ?>"
                                 class="img-fluid rounded border p-2 shadow-sm"
                                 style="max-width: 300px; background: #fff;"
                                 onerror="this.onerror=null; this.src='images/momo-qr-fixed.png';">
                        </div>

                        <div class="text-left mx-auto mt-3 p-3 rounded" style="max-width:420px;background:#f8fafc;border:1px solid #e2e8f0;">
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Ngân hàng thụ hưởng:</span>
                                <strong>MB Bank (Quân Đội)</strong>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Số tài khoản:</span>
                                <code><?= e(VIETQR_ACCOUNT_NO) ?></code>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Chủ tài khoản:</span>
                                <strong><?= e(VIETQR_ACCOUNT_NAME) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted small">Số tiền cần chuyển:</span>
                                <strong class="text-primary"><?= formatVnd($don['tong_tien']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted small">Nội dung chuyển khoản:</span>
                                <strong class="text-danger"><?= e($noiDungCk) ?></strong>
                            </div>
                        </div>

                        <form method="POST" action="thanh_toan.php?id=<?= (int) $don['id'] ?>" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <button type="submit" name="action" value="xac_nhan_ck" class="btn btn-success btn-lg btn-block font-weight-bold" style="background:#01d28e;border-color:#01d28e;">
                                <i class="fa-solid fa-circle-check mr-2"></i>Tôi đã thanh toán
                            </button>
                        </form>

                        <form method="POST" action="thanh_toan.php?id=<?= (int) $don['id'] ?>" class="mt-2" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn giữ chỗ này không?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <button type="submit" name="action" value="huy_don" class="btn btn-outline-danger btn-sm">
                                <i class="fa-solid fa-xmark mr-1"></i>Hủy đặt xe
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="py-4">
                            <i class="fa-solid fa-circle-info fa-3x text-primary mb-3"></i>
                            <h5 class="font-weight-bold">Trạng thái hiện tại: <span class="text-primary"><?= e($don['trang_thai']) ?></span></h5>
                            <p class="text-muted">Đơn thuê này không còn ở bước chờ quét mã thanh toán ban đầu.</p>
                            <a href="don_cua_toi.php" class="btn btn-primary font-weight-bold">
                                <i class="fa-solid fa-rectangle-list mr-1"></i>Xem trong Đơn của tôi
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cột phải: Chi tiết đơn thuê -->
            <div class="col-lg-6 mb-4">
                <div class="content-card h-100">
                    <h4 class="font-weight-bold text-dark mb-3">Thông tin chi tiết đơn thuê #DH<?= (int) $don['id'] ?></h4>
                    <div class="d-flex align-items-center p-3 rounded mb-3" style="background:#f8fafc;border:1px solid #e6ecf5;">
                        <img src="<?= e($don['hinh_anh'] ?: 'images/car-1.jpg') ?>" alt="<?= e($don['ten_xe']) ?>"
                             style="width:92px;height:66px;object-fit:cover;border-radius:8px;" class="mr-3"
                             onerror="this.src='images/car-1.jpg'">
                        <div>
                            <h5 class="font-weight-bold text-dark mb-1"><?= e($don['ten_xe']) ?></h5>
                            <small class="text-muted">Hãng sản xuất: <?= e($don['hang_xe']) ?></small>
                        </div>
                    </div>

                    <div class="border rounded mb-3" style="border-color:#e6ecf5 !important;">
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Người nhận xe:</span>
                            <strong><?= e($don['ten_khach_hang']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Số điện thoại:</span>
                            <strong><?= e($don['so_dien_thoai']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Ngày nhận xe:</span>
                            <strong><?= date('d/m/Y', strtotime($don['ngay_nhan'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Ngày trả xe:</span>
                            <strong><?= date('d/m/Y', strtotime($don['ngay_tra'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Số ngày thuê:</span>
                            <strong><?= (int) $don['so_ngay'] ?> ngày &times; <?= formatVnd($don['gia_thue']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Điểm nhận xe:</span>
                            <span class="text-right"><?= e($don['diem_nhan']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                            <span class="text-muted">Điểm trả xe:</span>
                            <span class="text-right"><?= e($don['diem_tra']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center px-3 py-3 bg-light">
                            <strong class="text-dark">Tổng cộng thanh toán:</strong>
                            <span class="price-tag"><?= formatVnd($don['tong_tien']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($dangChoThanhToan): ?>
<script>
(function () {
    var remain = <?= (int) $giayConLai ?>;
    var el = document.getElementById('countdownTimer');
    function renderTimer() {
        if (!el) return;
        if (remain <= 0) {
            el.textContent = '00:00 (Đã hết hạn)';
            window.location.reload();
            return;
        }
        var m = Math.floor(remain / 60);
        var s = remain % 60;
        el.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
        remain--;
    }
    renderTimer();
    setInterval(renderTimer, 1000);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
