<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin($pdo);

if (($user['trang_thai'] ?? 'Hoạt động') === 'Bị khóa') {
    setFlash('danger', 'Tài khoản của bạn hiện đang bị khóa do chưa hoàn tất nghĩa vụ thanh toán vi phạm/phạt nguội. Vui lòng liên hệ cửa hàng!');
    header('Location: index.php');
    exit;
}

$xeId = isset($_REQUEST['xe_id']) ? (int) $_REQUEST['xe_id'] : 0;
if ($xeId <= 0) {
    setFlash('warning', 'Vui lòng chọn một mẫu xe cần thuê trên trang chủ.');
    header('Location: index.php');
    exit;
}

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

$tenDmLower = mb_strtolower((string) ($xe['ten_danh_muc'] ?? ''), 'UTF-8');
$tenXeLower = mb_strtolower((string) $xe['ten_xe'], 'UTF-8');
$mienBlx    = (strpos($tenDmLower, 'điện') !== false || strpos($tenDmLower, '50cc') !== false || strpos($tenXeLower, '50cc') !== false);

function luuAnhGiayTo(array $file, string $prefix, string $label, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = 'Ảnh ' . $label . ' vượt quá dung lượng cho phép của máy chủ.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Tải ảnh ' . $label . ' thất bại (Mã lỗi: ' . $file['error'] . '), vui lòng thử lại.';
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        $errors[] = 'Ảnh ' . $label . ' không được vượt quá 8MB.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowExts = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
    if (!in_array($ext, $allowExts, true)) {
        $errors[] = 'Ảnh ' . $label . ' chỉ chấp nhận định dạng JPG, PNG hoặc WEBP.';
        return null;
    }

    $baseUpload = __DIR__ . '/uploads';
    if (!is_dir($baseUpload)) {
        @mkdir($baseUpload, 0777, true);
    }
    $uploadDir = $baseUpload . '/giay_to';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $fileName = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = 'Không thể lưu tệp ảnh ' . $label . ' vào thư mục uploads/giay_to/.';
        return null;
    }

    return 'uploads/giay_to/' . $fileName;
}

$todayStr    = date('Y-m-d');
$tomorrowStr = date('Y-m-d', strtotime('+1 day'));

$tenKhachHang   = $_POST['ten_khach_hang'] ?? $user['ho_ten'];
$soDienThoai    = $_POST['so_dien_thoai']  ?? $user['sdt'];
$soCccd         = $_POST['so_cccd']        ?? '';
$soBlx          = $_POST['so_blx']         ?? '';
$ngayNhan       = $_POST['ngay_nhan']      ?? $todayStr;
$ngayTra        = $_POST['ngay_tra']       ?? $tomorrowStr;
$diemNhan       = $_POST['diem_nhan']      ?? 'Cơ sở HKTGO Cầu Giấy - Hà Nội';
$diemTra        = $_POST['diem_tra']       ?? 'Cơ sở HKTGO Cầu Giấy - Hà Nội';
$dongYDieuKhoan = isset($_POST['dong_y_dieu_khoan']);
$errors         = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Phiên biểu mẫu không hợp lệ hoặc ảnh quá lớn vượt giới hạn máy chủ, vui lòng thử lại.';
    }

    $tenKhachHang   = trim((string) ($_POST['ten_khach_hang'] ?? ''));
    $soDienThoai    = normalizePhone((string) ($_POST['so_dien_thoai'] ?? ''));
    $soCccd         = preg_replace('/\s+/', '', (string) ($_POST['so_cccd'] ?? ''));
    $soBlx          = strtoupper(trim((string) ($_POST['so_blx'] ?? '')));
    $ngayNhan       = trim((string) ($_POST['ngay_nhan'] ?? ''));
    $ngayTra        = trim((string) ($_POST['ngay_tra'] ?? ''));
    $diemNhan       = trim((string) ($_POST['diem_nhan'] ?? ''));
    $diemTra        = trim((string) ($_POST['diem_tra'] ?? ''));
    $dongYDieuKhoan = isset($_POST['dong_y_dieu_khoan']);

    if ($tenKhachHang === '') {
        $errors[] = 'Vui lòng nhập họ tên người nhận xe (khớp với VNeID).';
    }
    if (!preg_match('/^0\d{9}$/', $soDienThoai)) {
        $errors[] = 'Số điện thoại liên hệ phải gồm đúng 10 chữ số và bắt đầu bằng số 0.';
    }
    if (!preg_match('/^\d{12}$/', $soCccd)) {
        $errors[] = 'Số định danh cá nhân / CCCD trên VNeID phải gồm đúng 12 chữ số.';
    }

    if (!isset($_FILES['anh_vneid']) || $_FILES['anh_vneid']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Vui lòng chọn tệp ảnh chụp màn hình định danh VNeID (hoặc CCCD).';
    }

    if (!$mienBlx) {
        if ($soBlx === '' || strlen($soBlx) < 8) {
            $errors[] = 'Vui lòng nhập đầy đủ số Giấy phép lái xe (BLX) hợp lệ.';
        }
        if (!isset($_FILES['anh_blx']) || $_FILES['anh_blx']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Vui lòng chọn tệp ảnh chụp Bằng lái xe (BLX).';
        }
    }

    if ($diemNhan === '' || $diemTra === '') {
        $errors[] = 'Vui lòng nhập đầy đủ địa điểm nhận xe và địa điểm trả xe.';
    }
    if (!$dongYDieuKhoan) {
        $errors[] = 'Bạn cần tích chọn đồng ý với Điều khoản & Chính sách thuê xe của HKTGO.';
    }

    $dtNhan  = DateTime::createFromFormat('Y-m-d', $ngayNhan);
    $dtTra   = DateTime::createFromFormat('Y-m-d', $ngayTra);
    $dtToday = DateTime::createFromFormat('Y-m-d', $todayStr);

    $soNgay = 1;
    if (!$dtNhan || $dtNhan->format('Y-m-d') !== $ngayNhan) {
        $errors[] = 'Ngày nhận xe không hợp lệ.';
    } elseif ($dtNhan < $dtToday) {
        $errors[] = 'Ngày nhận xe không được nhỏ hơn ngày hiện tại (' . date('d/m/Y') . ').';
    }

    if (!$dtTra || $dtTra->format('Y-m-d') !== $ngayTra) {
        $errors[] = 'Ngày trả xe không hợp lệ.';
    } elseif ($dtNhan && $dtTra < $dtNhan) {
        $errors[] = 'Ngày trả xe phải lớn hơn hoặc bằng ngày nhận xe.';
    }

    if (empty($errors) && $dtNhan && $dtTra) {
        $soNgay = (int) $dtNhan->diff($dtTra)->days + 1;
        if ($soNgay > 90) {
            $errors[] = 'Thời gian thuê tối đa cho một đơn trực tuyến là 90 ngày.';
        }
    }

    $pathAnhVneid = null;
    $pathAnhBlx   = null;
    if (empty($errors)) {
        $pathAnhVneid = luuAnhGiayTo($_FILES['anh_vneid'], 'vneid', 'VNeID/CCCD', $errors);
        if (isset($_FILES['anh_blx']) && $_FILES['anh_blx']['error'] !== UPLOAD_ERR_NO_FILE) {
            $pathAnhBlx = luuAnhGiayTo($_FILES['anh_blx'], 'blx', 'Bằng lái xe', $errors);
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmtLock = $pdo->prepare("SELECT * FROM xe_may WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$xeId]);
            $xeLocked = $stmtLock->fetch();

            if (!$xeLocked) {
                $pdo->rollBack();
                $errors[] = 'Mẫu xe không còn tồn tại trong hệ thống.';
            } else {
                $conLaiTrongKho = tinhSoLuongConLai($pdo, $xeId, (int) $xeLocked['so_luong'], true, $ngayNhan, $ngayTra);
                if ($conLaiTrongKho <= 0) {
                    $pdo->rollBack();
                    $errors[] = 'Mẫu xe này đã kín lịch trong khoảng thời gian từ ' . date('d/m/Y', strtotime($ngayNhan)) . ' đến ' . date('d/m/Y', strtotime($ngayTra)) . '. Vui lòng chọn ngày khác hoặc đổi mẫu xe!';
                } else {
                    $giaThueDon    = (float) $xeLocked['gia_thue'];
                    $tienCocDon    = (float) ($xeLocked['tien_coc'] ?? 1000000);
                    $tienThueXe    = $soNgay * $giaThueDon;
                    $tongThanhToan = $tienThueXe + $tienCocDon;

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO don_thue (
                            user_id, xe_may_id, ten_khach_hang, so_dien_thoai,
                            so_cccd, anh_vneid, so_blx, anh_blx, da_dong_y_dieu_khoan,
                            ngay_nhan, ngay_tra, diem_nhan, diem_tra,
                            so_ngay, tong_tien, tien_coc, trang_thai, trang_thai_coc, han_thanh_toan
                        ) VALUES (
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, 1,
                            ?, ?, ?, ?,
                            ?, ?, ?, 'Chờ thanh toán', 'Chưa thu', DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        )
                    ");
                    $stmtInsert->execute([
                        (int) $user['id'],
                        $xeId,
                        $tenKhachHang,
                        $soDienThoai,
                        $soCccd,
                        $pathAnhVneid,
                        $soBlx !== '' ? $soBlx : null,
                        $pathAnhBlx,
                        $ngayNhan,
                        $ngayTra,
                        $diemNhan,
                        $diemTra,
                        $soNgay,
                        $tongThanhToan,
                        $tienCocDon,
                    ]);

                    $donId = (int) $pdo->lastInsertId();

                    dongBoTrangThaiTatCaXe($pdo, $xeId);

                    $pdo->commit();

                    setFlash('success', 'Đã tạo đơn thuê #DH' . $donId . ' (bao gồm tiền thuê + tiền cọc) và giữ chỗ xe trong 15 phút. Vui lòng quét mã QR để thanh toán!');
                    header('Location: thanh_toan.php?id=' . $donId);
                    exit;
                }
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Có lỗi xảy ra khi khởi tạo đơn thuê: ' . $ex->getMessage();
        }
    }
}

$giaThueMacDinh = (float) $xe['gia_thue'];
$tienCocMacDinh = (float) ($xe['tien_coc'] ?? 1000000);

$pageTitle = 'Đặt thuê ' . $xe['ten_xe'] . ' - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container">
        <h2 class="font-weight-bold text-white mb-1">Đặt Thuê Xe Máy Trực Tuyến</h2>
        <p class="text-white-50 mb-0">Xác thực VNeID, Bằng lái xe và giữ chỗ trong 15 phút</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">

            <div class="col-lg-5 mb-4">
                <div class="content-card h-100">
                    <div class="position-relative mb-3">
                        <img src="<?= e($xe['hinh_anh'] ?: 'images/car-1.jpg') ?>"
                             alt="<?= e($xe['ten_xe']) ?>"
                             class="img-fluid rounded w-100"
                             style="height: 230px; object-fit: cover;"
                             onerror="this.src='images/car-1.jpg'">
                        <span class="badge badge-primary position-absolute" style="top:12px;left:12px;font-size:13px;padding:6px 12px;">
                            <?= e($xe['ten_danh_muc'] ?: 'Xe máy') ?>
                        </span>
                    </div>

                    <h3 class="font-weight-bold text-dark mb-1"><?= e($xe['ten_xe']) ?></h3>
                    <p class="text-muted mb-3">
                        <i class="fa-solid fa-industry mr-1"></i>Hãng xe: <strong><?= e($xe['hang_xe']) ?></strong>
                    </p>

                    <div class="d-flex justify-content-between align-items-center p-3 rounded mb-2" style="background:#f4f7fb;">
                        <div>
                            <small class="text-muted d-block">Đơn giá thuê theo ngày</small>
                            <span class="price-tag"><?= formatVnd($giaThueMacDinh) ?><small class="text-muted font-weight-normal">/ngày</small></span>
                        </div>
                        <div class="text-right">
                            <small class="text-muted d-block">Tình trạng kho</small>
                            <?php if ($conLaiHienTai > 0): ?>
                                <span class="badge-status-ranh">Còn <?= $conLaiHienTai ?> / <?= (int) $xe['so_luong'] ?> xe</span>
                            <?php else: ?>
                                <span class="badge-status-het">Tạm hết xe</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 rounded mb-3" style="background:#fffbeb;border:1px solid #fde68a;">
                        <div>
                            <small class="text-muted d-block">Tiền đặt cọc tài sản (hoàn trả sau thuê)</small>
                            <strong style="font-size:17px;color:#b45309;"><?= formatVnd($tienCocMacDinh) ?></strong>
                        </div>
                        <span class="badge badge-warning text-dark">Giữ 500k phạt nguội 15 ngày</span>
                    </div>

                    <ul class="list-unstyled text-muted small mb-0">
                        <li class="mb-2"><i class="fa-solid fa-id-card text-success mr-2"></i>Yêu cầu định danh mức 2 VNeID &amp; BLX hợp lệ.</li>
                        <li class="mb-2"><i class="fa-solid fa-file-contract text-success mr-2"></i>Tự động lập Hợp đồng thuê xe số ngay sau khi đặt.</li>
                        <li class="mb-2"><i class="fa-solid fa-clock text-success mr-2"></i>Giữ xe trong <strong>15 phút</strong> để hoàn tất quét mã VietQR.</li>
                        <li><i class="fa-solid fa-shield-halved text-success mr-2"></i>Hoàn cọc tài sản ngay khi trả xe, đối soát phạt nguội sau 15 ngày.</li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="content-card">
                    <h4 class="font-weight-bold text-dark mb-3">
                        <i class="fa-solid fa-file-signature text-primary mr-2"></i>Hồ Sơ Xác Thực &amp; Lịch Thuê Xe
                    </h4>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ((int) $xe['so_luong'] <= 0): ?>
                        <div class="alert alert-warning">
                            <strong>Mẫu xe này hiện đang tạm ngừng cho thuê.</strong> Bạn vui lòng quay lại trang chủ để chọn mẫu xe khác đang sẵn sàng.
                        </div>
                        <a href="index.php#dan-xe" class="btn btn-primary font-weight-bold">
                            <i class="fa-solid fa-arrow-left mr-1"></i>Xem các mẫu xe khác
                        </a>
                    <?php else: ?>
                        <?php if ($conLaiHienTai <= 0): ?>
                            <div class="alert alert-info py-2 small">
                                <i class="fa-solid fa-calendar-check mr-1"></i>
                                Mẫu xe này hiện đang có khách thuê, bạn vẫn có thể chọn <strong>Ngày nhận xe &amp; Ngày trả xe</strong> trống lịch bên dưới để <strong>đặt lịch trước</strong>.
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="dat_xe.php?xe_id=<?= (int) $xe['id'] ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="xe_id" value="<?= (int) $xe['id'] ?>">

                            <h6 class="font-weight-bold text-primary text-uppercase small mb-2">1. Thông tin người nhận xe</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Họ tên người nhận (khớp <span class="text-nowrap">VNeID) <span class="text-danger">*</span></span></label>
                                    <input type="text" name="ten_khach_hang" class="form-control" required
                                           value="<?= e($tenKhachHang) ?>" placeholder="Nguyễn Văn A">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Số điện thoại liên <span class="text-nowrap">hệ <span class="text-danger">*</span></span></label>
                                    <input type="text" name="so_dien_thoai" class="form-control" required
                                           value="<?= e($soDienThoai) ?>" placeholder="0912345678">
                                </div>
                            </div>

                            <h6 class="font-weight-bold text-primary text-uppercase small mt-2 mb-2">2. Xác thực định danh VNeID &amp; Bằng lái xe (BLX)</h6>
                            <div class="p-3 rounded mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-semibold">Số định danh / CCCD <span class="text-nowrap">(VNeID) <span class="text-danger">*</span></span></label>
                                        <input type="text" name="so_cccd" class="form-control" required maxlength="12"
                                               value="<?= e($soCccd) ?>" placeholder="Nhập đủ 12 chữ số CCCD">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-semibold">Ảnh chụp màn hình VNeID / <span class="text-nowrap">CCCD <span class="text-danger">*</span></span></label>
                                        <input type="file" name="anh_vneid" id="inputAnhVneid" class="form-control" style="height:auto;padding:6px;" required accept="image/*">
                                        <div id="previewBoxVneid" class="mt-2 d-none">
                                            <img id="imgPreviewVneid" src="" alt="Xem trước VNeID" class="img-thumbnail" style="max-height:110px;">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="font-weight-semibold">
                                            Số Bằng lái xe (BLX)
                                            <?php if ($mienBlx): ?>
                                                <span class="badge badge-info">Miễn (xe &le;50cc)</span>
                                            <?php else: ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="text" name="so_blx" class="form-control" <?= $mienBlx ? '' : 'required' ?>
                                               value="<?= e($soBlx) ?>" placeholder="Ví dụ: 010123456789">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="font-weight-semibold">
                                            Ảnh chụp mặt trước BLX
                                            <?= $mienBlx ? '' : '<span class="text-danger">*</span>' ?>
                                        </label>
                                        <input type="file" name="anh_blx" id="inputAnhBlx" class="form-control" style="height:auto;padding:6px;" <?= $mienBlx ? '' : 'required' ?> accept="image/*">
                                        <div id="previewBoxBlx" class="mt-2 d-none">
                                            <img id="imgPreviewBlx" src="" alt="Xem trước BLX" class="img-thumbnail" style="max-height:110px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="font-weight-bold text-primary text-uppercase small mt-2 mb-2">3. Thời gian &amp; Địa điểm giao nhận</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Ngày nhận xe <span class="text-danger">*</span></label>
                                    <input type="date" name="ngay_nhan" id="ngayNhanInput" class="form-control" required
                                           min="<?= e($todayStr) ?>" value="<?= e($ngayNhan) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Ngày trả xe <span class="text-danger">*</span></label>
                                    <input type="date" name="ngay_tra" id="ngayTraInput" class="form-control" required
                                           min="<?= e($todayStr) ?>" value="<?= e($ngayTra) ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Điểm nhận xe <span class="text-danger">*</span></label>
                                    <input type="text" name="diem_nhan" class="form-control" required
                                           value="<?= e($diemNhan) ?>" placeholder="Cơ sở HKTGO hoặc địa chỉ giao xe">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Điểm trả xe <span class="text-danger">*</span></label>
                                    <input type="text" name="diem_tra" class="form-control" required
                                           value="<?= e($diemTra) ?>" placeholder="Cơ sở HKTGO hoặc địa chỉ trả xe">
                                </div>
                            </div>

                            <div class="p-3 rounded mb-3" style="background:#f8fafc;border:1px solid #cbd5e1;">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="dongYDieuKhoan" name="dong_y_dieu_khoan" value="1" required <?= $dongYDieuKhoan ? 'checked' : '' ?>>
                                    <label class="custom-control-label font-weight-semibold text-dark" for="dongYDieuKhoan">
                                        Tôi đã đọc kỹ và hoàn toàn đồng ý với
                                        <a href="docs/dieu_khoan_hktgo.pdf" target="_blank" class="text-primary font-weight-bold" style="text-decoration: underline;">
                                            Điều khoản &amp; Chính sách thuê xe HKTGO
                                        </a>
                                        <span class="text-danger">*</span>
                                    </label>
                                </div>
                            </div>

                            <div class="p-3 rounded mb-4" style="background:#eef6ff;border:1px solid #cfe2ff;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Số ngày thuê dự kiến:</span>
                                    <strong id="previewSoNgay">2 ngày</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Tiền thuê xe (<span id="previewDonGiaText"><?= formatVnd($giaThueMacDinh) ?></span>/ngày):</span>
                                    <strong id="previewTienThue"><?= formatVnd($giaThueMacDinh * 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                    <span class="text-muted">Tiền đặt cọc tài sản:</span>
                                    <strong><?= formatVnd($tienCocMacDinh) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-dark">Tổng thanh toán ban đầu (Thuê + Cọc):</span>
                                    <span class="price-tag" id="previewTongTien"><?= formatVnd(($giaThueMacDinh * 2) + $tienCocMacDinh) ?></span>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
                                <a href="index.php#dan-xe" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-arrow-left mr-1"></i>Chọn xe khác
                                </a>
                                <button type="submit" class="btn btn-success btn-lg font-weight-bold px-4" style="background:#01d28e;border-color:#01d28e;">
                                    <i class="fa-solid fa-circle-check mr-2"></i>Xác nhận đặt xe &amp; Lập hợp đồng
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {

    var giaThue = <?= $giaThueMacDinh ?>;
    var tienCoc = <?= $tienCocMacDinh ?>;
    var elNhan  = document.getElementById('ngayNhanInput');
    var elTra   = document.getElementById('ngayTraInput');
    var elNgay  = document.getElementById('previewSoNgay');
    var elThue  = document.getElementById('previewTienThue');
    var elTien  = document.getElementById('previewTongTien');

    function capNhatTamTinh() {
        if (!elNhan || !elTra || !elNhan.value || !elTra.value) return;
        var d1 = new Date(elNhan.value + 'T00:00:00');
        var d2 = new Date(elTra.value + 'T00:00:00');
        if (d2 < d1) {
            elNgay.textContent = 'Ngày trả < ngày nhận';
            elThue.textContent = '0\u00A0₫';
            elTien.textContent = tienCoc.toLocaleString('vi-VN') + '\u00A0₫';
            return;
        }
        var diffDays = Math.round((d2 - d1) / 86400000) + 1;
        var tienThue = diffDays * giaThue;
        var tong     = tienThue + tienCoc;
        elNgay.textContent = diffDays + ' ngày';
        elThue.textContent = tienThue.toLocaleString('vi-VN') + '\u00A0₫';
        elTien.textContent = tong.toLocaleString('vi-VN') + '\u00A0₫';
    }

    if (elNhan && elTra) {
        elNhan.addEventListener('change', capNhatTamTinh);
        elTra.addEventListener('change', capNhatTamTinh);
        capNhatTamTinh();
    }

    function bindPreview(inputId, boxId, imgId) {
        var inp = document.getElementById(inputId);
        var box = document.getElementById(boxId);
        var img = document.getElementById(imgId);
        if (!inp || !box || !img) return;
        inp.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    img.src = e.target.result;
                    box.classList.remove('d-none');
                };
                reader.readAsDataURL(this.files[0]);
            } else {
                box.classList.add('d-none');
            }
        });
    }
    bindPreview('inputAnhVneid', 'previewBoxVneid', 'imgPreviewVneid');
    bindPreview('inputAnhBlx', 'previewBoxBlx', 'imgPreviewBlx');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
