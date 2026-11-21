<?php
/**
 * dat_xe.php
 * Trang đặt xe máy trực tuyến & tạo đơn giữ chỗ 15 phút
 * - Tự động tính số ngày và tổng tiền trên máy chủ PHP (không tin dữ liệu tiền từ trình duyệt)
 * - Bọc PDO Transaction + SELECT ... FOR UPDATE chống đặt trùng (Race Condition) khi còn chiếc cuối
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);

$xeId = isset($_REQUEST['xe_id']) ? (int) $_REQUEST['xe_id'] : 0;
if ($xeId <= 0) {
    setFlash('warning', 'Vui lòng chọn một mẫu xe cần thuê trên trang chủ.');
    header('Location: index.php');
    exit;
}

// Truy vấn thông tin xe hiển thị trên giao diện
$stmtXe = $pdo->prepare("
    SELECT x.*, dm.ten AS ten_danh_muc
    FROM xe_may x
    LEFT JOIN danh_muc_xe dm ON dm.id = x.danh_muc_xe_id
    WHERE x.id = ?
    LIMIT 1
");
$stmtXe->execute([$xeId]);
$xe = $stmtXe->fetch();

if (!$xe) {
    setFlash('danger', 'Mẫu xe bạn chọn không tồn tại trong hệ thống.');
    header('Location: index.php');
    exit;
}

$conLaiHienTai = tinhSoLuongConLai($pdo, (int) $xe['id'], (int) $xe['so_luong'], false);

$todayStr    = date('Y-m-d');
$tomorrowStr = date('Y-m-d', strtotime('+1 day'));

// Giá trị mặc định trên biểu mẫu
$tenKhachHang = $_POST['ten_khach_hang'] ?? $user['ho_ten'];
$soDienThoai  = $_POST['so_dien_thoai']  ?? $user['sdt'];
$ngayNhan     = $_POST['ngay_nhan']      ?? $todayStr;
$ngayTra      = $_POST['ngay_tra']       ?? $tomorrowStr;
$diemNhan     = $_POST['diem_nhan']      ?? 'Cơ sở HKTGO Cầu Giấy - Hà Nội';
$diemTra      = $_POST['diem_tra']       ?? 'Cơ sở HKTGO Cầu Giấy - Hà Nội';
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Phiên biểu mẫu không hợp lệ, vui lòng thử lại.';
    }

    $tenKhachHang = trim($tenKhachHang);
    $soDienThoai  = normalizePhone($soDienThoai);
    $ngayNhan     = trim($ngayNhan);
    $ngayTra      = trim($ngayTra);
    $diemNhan     = trim($diemNhan);
    $diemTra      = trim($diemTra);

    if ($tenKhachHang === '' || mb_strlen($tenKhachHang) < 2) {
        $errors[] = 'Vui lòng nhập họ tên người nhận xe hợp lệ.';
    }
    if (!preg_match('/^0\d{9}$/', $soDienThoai)) {
        $errors[] = 'Số điện thoại liên hệ phải gồm đúng 10 chữ số bắt đầu bằng số 0.';
    }

    $dtNhan  = DateTime::createFromFormat('Y-m-d', $ngayNhan);
    $dtTra   = DateTime::createFromFormat('Y-m-d', $ngayTra);
    $dtToday = DateTime::createFromFormat('Y-m-d', $todayStr);

    if (!$dtNhan || $dtNhan->format('Y-m-d') !== $ngayNhan) {
        $errors[] = 'Ngày nhận xe không đúng định dạng năm-tháng-ngày.';
    } elseif ($dtNhan < $dtToday) {
        $errors[] = 'Ngày nhận xe không được nhỏ hơn ngày hiện tại.';
    }

    if (!$dtTra || $dtTra->format('Y-m-d') !== $ngayTra) {
        $errors[] = 'Ngày trả xe không đúng định dạng năm-tháng-ngày.';
    } elseif ($dtNhan && $dtTra < $dtNhan) {
        $errors[] = 'Ngày trả xe phải lớn hơn hoặc bằng ngày nhận xe.';
    }

    if ($diemNhan === '' || $diemTra === '') {
        $errors[] = 'Vui lòng nhập đầy đủ địa điểm nhận xe và địa điểm trả xe.';
    }

    $soNgay = 1;
    if (empty($errors)) {
        $soNgay = (int) $dtNhan->diff($dtTra)->days + 1;
        if ($soNgay > 90) {
            $errors[] = 'Thời gian đặt thuê trực tuyến tối đa là 90 ngày cho mỗi đơn.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Khóa dòng xe trong giao dịch để chống 2 khách đặt trùng chiếc xe cuối cùng
            $stmtLock = $pdo->prepare("SELECT * FROM xe_may WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$xeId]);
            $xeLocked = $stmtLock->fetch();

            if (!$xeLocked) {
                $pdo->rollBack();
                $errors[] = 'Mẫu xe không còn tồn tại trong hệ thống.';
            } else {
                $conLaiTrongKho = tinhSoLuongConLai($pdo, $xeId, (int) $xeLocked['so_luong'], true);
                if ($conLaiTrongKho <= 0) {
                    $pdo->rollBack();
                    $errors[] = 'Rất tiếc, mẫu xe này vừa hết xe khả dụng. Vui lòng chọn mẫu xe khác!';
                    $conLaiHienTai = 0;
                } else {
                    // Tính tổng tiền hoàn toàn ở phía máy chủ PHP
                    $giaThueDon = (float) $xeLocked['gia_thue'];
                    $tongTien   = $soNgay * $giaThueDon;

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO don_thue (
                            user_id, xe_may_id, ten_khach_hang, so_dien_thoai,
                            ngay_nhan, ngay_tra, diem_nhan, diem_tra,
                            so_ngay, tong_tien, trang_thai, han_thanh_toan
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, 'Chờ thanh toán', DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        )
                    ");
                    $stmtInsert->execute([
                        (int) $user['id'],
                        $xeId,
                        $tenKhachHang,
                        $soDienThoai,
                        $ngayNhan,
                        $ngayTra,
                        $diemNhan,
                        $diemTra,
                        $soNgay,
                        $tongTien,
                    ]);

                    $donId = (int) $pdo->lastInsertId();

                    // Đồng bộ lại trạng thái xe ('Đang rảnh' / 'Hết xe')
                    dongBoTrangThaiTatCaXe($pdo, $xeId);

                    $pdo->commit();

                    setFlash('success', 'Đã tạo đơn thuê #DH' . $donId . ' và giữ chỗ xe trong 15 phút. Vui lòng quét mã QR để thanh toán!');
                    header('Location: thanh_toan.php?id=' . $donId);
                    exit;
                }
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Không thể lưu đơn đặt xe lúc này. Vui lòng thử lại.';
        }
    }
}

$anhXe = !empty($xe['hinh_anh']) ? $xe['hinh_anh'] : 'images/car-1.jpg';
$pageTitle = 'Đặt thuê xe ' . $xe['ten_xe'] . ' - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container">
        <h2 class="font-weight-bold text-white mb-1">
            <i class="fa-solid fa-calendar-check mr-2" style="color:#01d28e;"></i>Đặt Thuê Xe Máy Trực Tuyến
        </h2>
        <p class="text-white-50 mb-0">Điền thông tin lịch trình thuê xe — Hệ thống tự động giữ chỗ xe trong 15 phút để thanh toán QR</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Thông tin mẫu xe đã chọn -->
            <div class="col-lg-5 mb-4">
                <div class="content-card h-100">
                    <img src="<?= e($anhXe) ?>" alt="<?= e($xe['ten_xe']) ?>"
                         class="img-fluid rounded mb-3 w-100" style="height:230px;object-fit:cover;"
                         onerror="this.src='images/car-1.jpg'">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-light border text-secondary"><?= e($xe['ten_danh_muc'] ?? 'Xe máy') ?></span>
                        <?php if ($conLaiHienTai > 0): ?>
                            <span class="badge-stock-ok">
                                <i class="fa-solid fa-circle-check mr-1"></i>Còn <?= $conLaiHienTai ?>/<?= (int) $xe['so_luong'] ?> xe
                            </span>
                        <?php else: ?>
                            <span class="badge-stock-out">Hết xe</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-weight-bold text-dark mb-2"><?= e($xe['ten_xe']) ?></h4>
                    <p class="text-muted small mb-3">
                        Hãng sản xuất: <strong><?= e($xe['hang_xe']) ?></strong> &bull;
                        Danh mục: <strong><?= e($xe['ten_danh_muc'] ?? 'Xe máy') ?></strong>
                    </p>

                    <div class="p-3 rounded mb-3" style="background:#f1f6ff;border:1px solid #d7e5ff;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Đơn giá niêm yết:</span>
                            <strong class="text-primary"><?= formatVnd($xe['gia_thue']) ?> / ngày</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Số ngày dự kiến:</span>
                            <strong id="previewSoNgay">2 ngày</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold text-dark">Tạm tính tổng tiền:</span>
                            <span id="previewTongTien" class="price-tag"><?= formatVnd($xe['gia_thue'] * 2) ?></span>
                        </div>
                    </div>

                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left mr-1"></i>Chọn mẫu xe khác
                    </a>
                </div>
            </div>

            <!-- Biểu mẫu nhập thông tin đặt xe -->
            <div class="col-lg-7 mb-4">
                <div class="content-card">
                    <h4 class="font-weight-bold text-dark mb-3">Thông tin người thuê &amp; lịch nhận xe</h4>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($conLaiHienTai <= 0): ?>
                        <div class="alert alert-warning">
                            Mẫu xe này hiện đã hết số lượng xe khả dụng. Vui lòng quay lại <a href="index.php" class="font-weight-bold">Trang chủ</a> để chọn xe khác.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="dat_xe.php" id="formDatXe">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="xe_id" value="<?= (int) $xe['id'] ?>">

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold small text-dark">Họ và tên người nhận xe <span class="text-danger">*</span></label>
                                    <input type="text" name="ten_khach_hang" class="form-control" required value="<?= e($tenKhachHang) ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold small text-dark">Số điện thoại liên hệ (10 số) <span class="text-danger">*</span></label>
                                    <input type="text" name="so_dien_thoai" class="form-control" required placeholder="09xxxxxxxx" value="<?= e($soDienThoai) ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold small text-dark">Ngày nhận xe <span class="text-danger">*</span></label>
                                    <input type="date" name="ngay_nhan" id="ngayNhanInput" class="form-control" min="<?= e($todayStr) ?>" required value="<?= e($ngayNhan) ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-bold small text-dark">Ngày trả xe <span class="text-danger">*</span></label>
                                    <input type="date" name="ngay_tra" id="ngayTraInput" class="form-control" min="<?= e($todayStr) ?>" required value="<?= e($ngayTra) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold small text-dark">Địa điểm nhận xe <span class="text-danger">*</span></label>
                                <input type="text" name="diem_nhan" class="form-control" required value="<?= e($diemNhan) ?>" placeholder="VD: Cơ sở HKTGO Cầu Giấy hoặc địa chỉ giao xe">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold small text-dark">Địa điểm trả xe <span class="text-danger">*</span></label>
                                <input type="text" name="diem_tra" class="form-control" required value="<?= e($diemTra) ?>" placeholder="VD: Cơ sở HKTGO Cầu Giấy">
                            </div>

                            <div class="alert alert-info small mb-4">
                                <i class="fa-solid fa-clock mr-1"></i>
                                Sau khi bấm <strong>Xác nhận đặt xe</strong>, quý khách vui lòng thanh toán trong vòng <strong>15 phút</strong> .Nếu quá 15 phút chưa xác nhận, đơn sẽ tự động hủy
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg btn-block font-weight-bold">
                                <i class="fa-solid fa-qrcode mr-2"></i>Xác nhận đặt xe &amp; Chuyển sang Thanh toán QR
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var giaThue = <?= (float) $xe['gia_thue'] ?>;
    var elNhan = document.getElementById('ngayNhanInput');
    var elTra  = document.getElementById('ngayTraInput');
    var elNgay = document.getElementById('previewSoNgay');
    var elTien = document.getElementById('previewTongTien');

    function capNhatTamTinh() {
        if (!elNhan || !elTra || !elNhan.value || !elTra.value) return;
        var d1 = new Date(elNhan.value + 'T00:00:00');
        var d2 = new Date(elTra.value + 'T00:00:00');
        if (d2 < d1) {
            elNgay.textContent = 'Ngày trả < ngày nhận';
            elTien.textContent = '0 ₫';
            return;
        }
        var diffDays = Math.round((d2 - d1) / 86400000) + 1;
        var tong = diffDays * giaThue;
        elNgay.textContent = diffDays + ' ngày';
        elTien.textContent = tong.toLocaleString('vi-VN') + ' ₫';
    }

    if (elNhan && elTra) {
        elNhan.addEventListener('change', capNhatTamTinh);
        elTra.addEventListener('change', capNhatTamTinh);
        capNhatTamTinh();
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
