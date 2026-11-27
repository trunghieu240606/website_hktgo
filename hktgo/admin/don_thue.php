<?php
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

    $stmtDon = $pdo->prepare("
        SELECT d.*, x.gia_thue
        FROM don_thue d
        LEFT JOIN xe_may x ON x.id = d.xe_may_id
        WHERE d.id = ?
        LIMIT 1
    ");
    $stmtDon->execute([$donId]);
    $donRow = $stmtDon->fetch();

    if (!$donRow) {
        setFlash('danger', 'Không tìm thấy đơn thuê cần cập nhật.');
    } else {
        $xeId      = (int) ($donRow['xe_may_id'] ?? 0);
        $userIdDon = (int) ($donRow['user_id'] ?? 0);
        $ttHienTai = $donRow['trang_thai'];

        if ($action === 'duyet_giao_xe') {
            if (in_array($ttHienTai, ['Chờ duyệt', 'Chờ thanh toán'], true)) {
                $upd = $pdo->prepare("
                    UPDATE don_thue
                    SET trang_thai = 'Đang thuê',
                        trang_thai_coc = 'Đang giữ cọc',
                        thoi_diem_thanh_toan = COALESCE(thoi_diem_thanh_toan, NOW())
                    WHERE id = ?
                ");
                $upd->execute([$donId]);
                if ($xeId > 0) {
                    dongBoTrangThaiTatCaXe($pdo, $xeId);
                }
                setFlash('success', 'Đã đối soát hồ sơ và bàn giao xe cho đơn #DH' . $donId . ' (Đang thuê - Đang giữ cọc).');
            } else {
                setFlash('warning', 'Đơn #DH' . $donId . ' không ở trạng thái chờ duyệt để giao xe.');
            }
        }

        elseif ($action === 'nhan_tra_xe') {
            if ($ttHienTai === 'Đang thuê') {
                $phiQuaHan   = max(0, (float) ($_POST['phi_qua_han'] ?? 0));
                $phiHuHong   = max(0, (float) ($_POST['phi_hu_hong'] ?? 0));
                $giuCoc500k  = isset($_POST['giu_coc_phat_nguoi']);
                $ghiChuSuCo  = trim((string) ($_POST['ghi_chu_su_co'] ?? ''));

                $tienCocBanDau = (float) ($donRow['tien_coc'] ?? 0);
                $tongKhauTru   = $phiQuaHan + $phiHuHong;

                if ($giuCoc500k) {
                    $trangThaiCoc = 'Giữ cọc phạt nguội 15 ngày';
                    $hanHoanCoc   = date('Y-m-d H:i:s', strtotime('+15 days'));
                    $tienGiuLai   = min(500000, max(0, $tienCocBanDau - $tongKhauTru));
                    $tienHoanNgay = max(0, $tienCocBanDau - $tongKhauTru - $tienGiuLai);
                } else {
                    $trangThaiCoc = 'Đã hoàn đủ';
                    $hanHoanCoc   = null;
                    $tienGiuLai   = 0;
                    $tienHoanNgay = max(0, $tienCocBanDau - $tongKhauTru);
                }

                $tienKhachBu = max(0, $tongKhauTru - $tienCocBanDau);

                $upd = $pdo->prepare("
                    UPDATE don_thue
                    SET trang_thai = 'Hoàn thành',
                        phi_qua_han = ?,
                        phi_phat_sinh = ?,
                        trang_thai_coc = ?,
                        han_hoan_coc = ?,
                        ghi_chu_su_co = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $phiQuaHan,
                    $phiHuHong,
                    $trangThaiCoc,
                    $hanHoanCoc,
                    $ghiChuSuCo !== '' ? $ghiChuSuCo : null,
                    $donId,
                ]);

                if ($xeId > 0) {
                    dongBoTrangThaiTatCaXe($pdo, $xeId);
                }

                $msg = 'Đã hoàn tất nhận trả xe đơn #DH' . $donId . '. Hoàn cọc tại quầy: ' . formatVnd($tienHoanNgay) . '.';
                if ($giuCoc500k) {
                    $msg .= ' Giữ lại ' . formatVnd($tienGiuLai) . ' tra cứu phạt nguội trong 15 ngày.';
                }
                if ($tienKhachBu > 0) {
                    $msg .= ' Lưu ý: Khách cần đóng bù thêm ' . formatVnd($tienKhachBu) . ' do chi phí vượt tiền cọc!';
                }
                setFlash('success', $msg);
            } else {
                setFlash('warning', 'Chỉ những đơn đang ở trạng thái "Đang thuê" mới có thể nhận trả xe.');
            }
        }
        elseif ($action === 'hoan_not_coc') {
            if ($ttHienTai === 'Hoàn thành' && ($donRow['trang_thai_coc'] ?? '') === 'Giữ cọc phạt nguội 15 ngày') {
                $upd = $pdo->prepare("
                    UPDATE don_thue
                    SET trang_thai_coc = 'Đã hoàn đủ',
                        ghi_chu_su_co = CONCAT(COALESCE(ghi_chu_su_co, ''), ' | Đã hoàn nốt cọc phạt nguội lúc ', DATE_FORMAT(NOW(), '%H:%i %d/%m/%Y'))
                    WHERE id = ?
                ");
                $upd->execute([$donId]);
                setFlash('success', 'Đã xác nhận hoàn trả nốt phần cọc phạt nguội cho đơn #DH' . $donId . ' (Đã hoàn đủ).');
            }
        }

        elseif ($action === 'xu_ly_phat_nguoi') {
            $tienPhatNguoi = max(0, (float) ($_POST['tien_phat_nguoi'] ?? 0));
            $noiDungPhat   = trim((string) ($_POST['noi_dung_phat'] ?? ''));
            $khoaTaiKhoan  = isset($_POST['khoa_tai_khoan']);

            $phiPhatCu   = (float) ($donRow['phi_phat_sinh'] ?? 0);
            $tongPhatMoi = $phiPhatCu + $tienPhatNguoi;
            $ghiChuCu    = trim((string) ($donRow['ghi_chu_su_co'] ?? ''));
            $ghiChuMoi   = ($ghiChuCu !== '' ? $ghiChuCu . ' | ' : '') . '[Phạt nguội +' . formatVnd($tienPhatNguoi) . ']: ' . $noiDungPhat;

            $upd = $pdo->prepare("
                UPDATE don_thue
                SET phi_phat_sinh = ?,
                    trang_thai_coc = 'Đã khấu trừ phạt',
                    ghi_chu_su_co = ?
                WHERE id = ?
            ");
            $upd->execute([$tongPhatMoi, $ghiChuMoi, $donId]);

            if ($khoaTaiKhoan && $userIdDon > 0) {
                $updUser = $pdo->prepare("UPDATE users SET trang_thai = 'Bị khóa' WHERE id = ? AND vai_tro = 'USER'");
                $updUser->execute([$userIdDon]);
                setFlash('warning', 'Đã khấu trừ phạt nguội đơn #DH' . $donId . ' và KHÓA tài khoản khách hàng do chưa tất toán vi phạm.');
            } else {
                setFlash('info', 'Đã ghi nhận biên bản phạt nguội và khấu trừ tiền cọc cho đơn #DH' . $donId . '.');
            }
        }

        elseif ($action === 'huy_don') {
            if ($ttHienTai !== 'Hoàn thành' && $ttHienTai !== 'Đã hủy') {
                $daGiuCoc  = in_array(($donRow['trang_thai_coc'] ?? ''), ['Đang giữ cọc', 'Giữ cọc phạt nguội 15 ngày'], true)
                             || in_array($ttHienTai, ['Chờ duyệt', 'Đang thuê'], true);
                $ttCocMoi  = $daGiuCoc ? 'Đã hoàn đủ' : 'Chưa thu';
                $ghiChuHuy = $daGiuCoc
                    ? 'Quản trị viên hủy đơn — Đã hoàn trả đủ tiền cọc (' . formatVnd($donRow['tien_coc']) . ') cho khách hàng.'
                    : null;

                $upd = $pdo->prepare("
                    UPDATE don_thue
                    SET trang_thai = 'Đã hủy',
                        trang_thai_coc = ?,
                        ghi_chu_su_co = COALESCE(?, ghi_chu_su_co)
                    WHERE id = ?
                ");
                $upd->execute([$ttCocMoi, $ghiChuHuy, $donId]);

                if ($xeId > 0) {
                    dongBoTrangThaiTatCaXe($pdo, $xeId);
                }

                if ($daGiuCoc) {
                    setFlash('success', 'Đã hủy đơn #DH' . $donId . ', trả xe về kho và xác nhận HOÀN ĐỦ CỌC (' . formatVnd($donRow['tien_coc']) . ') cho khách.');
                } else {
                    setFlash('info', 'Đã hủy đơn giữ chỗ #DH' . $donId . ' (chưa thu tiền) và hoàn trả suất xe về kho.');
                }
            } else {
                setFlash('warning', 'Không thể hủy đơn đã hoàn thành hoặc đã hủy trước đó.');
            }
        }

        elseif ($action === 'mo_khoa_tai_khoan') {
            if ($userIdDon > 0) {
                $updUser = $pdo->prepare("UPDATE users SET trang_thai = 'Hoạt động' WHERE id = ?");
                $updUser->execute([$userIdDon]);
                setFlash('success', 'Đã MỞ KHÓA tài khoản cho khách hàng "' . $donRow['ten_khach_hang'] . '". Khách có thể đặt xe bình thường.');
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
    SELECT d.*, x.ten_xe, x.hang_xe, x.gia_thue, u.email AS user_email, u.trang_thai AS user_trang_thai
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
    LEFT JOIN users u ON u.id = d.user_id
    WHERE 1=1
";
$params = [];

if (in_array($locTrangThai, $cacTrangThai, true)) {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $locTrangThai;
}
if ($tuKhoa !== '') {
    $sql .= " AND (d.ten_khach_hang LIKE ? OR d.so_dien_thoai LIKE ? OR d.so_cccd LIKE ? OR x.ten_xe LIKE ?)";
    $kw = '%' . $tuKhoa . '%';
    $params[] = $kw;
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
            return '<span class="badge bg-warning text-dark">Chờ duyệt CK &amp; HS</span>';
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

$todayDate = date('Y-m-d');
$pageTitle = 'Quản lý Đơn thuê xe - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .hkt-modal .modal-content {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        background-color: #ffffff !important;
        border-radius: 12px !important;
        border: 1px solid #dee2e6 !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2) !important;
    }
    .hkt-modal .modal-header,
    .hkt-modal .modal-body,
    .hkt-modal .modal-footer {
        width: 100% !important;
        display: block !important;
    }
    .hkt-modal .modal-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }
    .hkt-modal .modal-footer {
        display: flex !important;
        justify-content: flex-end !important;
        gap: 8px !important;
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">Quản lý đơn thuê</h1>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="don_thue.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Tìm theo tên khách, SĐT, số VNeID hoặc tên xe</label>
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
                        <th>Khách hàng &amp; Định danh</th>
                        <th>Xe thuê &amp; Lịch trình</th>
                        <th>Thuê / Cọc / Phạt</th>
                        <th>Trạng thái &amp; Cọc</th>
                        <th class="pe-3 text-end">Thao tác nghiệp vụ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dsDon)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Không có đơn thuê nào phù hợp.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dsDon as $d): ?>
                        <?php
                            $idDon = (int) $d['id'];
                            $tienCoc = (float) ($d['tien_coc'] ?? 0);
                            $tienThue = max(0, (float) $d['tong_tien'] - $tienCoc);
                            $phiQuaHan = (float) ($d['phi_qua_han'] ?? 0);
                            $phiPhatSinh = (float) ($d['phi_phat_sinh'] ?? 0);

                            $soNgayQuaHan = 0;
                            if ($d['trang_thai'] === 'Đang thuê' && $todayDate > $d['ngay_tra']) {
                                $dtTraDuKien = new DateTime($d['ngay_tra']);
                                $dtHomNay    = new DateTime($todayDate);
                                $soNgayQuaHan = (int) $dtTraDuKien->diff($dtHomNay)->days;
                            }
                        ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-primary">#DH<?= $idDon ?></div>
                                <a href="../hop_dong.php?id=<?= $idDon ?>" target="_blank" class="small text-decoration-none">
                                    <i class="fa-solid fa-file-contract me-1"></i>Hợp đồng
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= e($d['ten_khach_hang']) ?>
                                    <?php if (($d['user_trang_thai'] ?? '') === 'Bị khóa'): ?>
                                        <span class="badge bg-danger ms-1">TK Bị khóa</span>
                                        <form method="POST" action="don_thue.php" class="d-inline ms-1"
                                              onsubmit="return confirm('Xác nhận khách hàng đã tất toán vi phạm và MỞ KHÓA tài khoản này?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="don_id" value="<?= $idDon ?>">
                                            <input type="hidden" name="action" value="mo_khoa_tai_khoan">
                                            <button type="submit" class="btn btn-outline-success py-0 px-2" style="font-size: 11px;">
                                                <i class="fa-solid fa-lock-open me-1"></i>Mở khóa TK
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">SĐT: <?= e($d['so_dien_thoai']) ?></div>
                                <div class="small text-muted">VNeID: <code><?= e($d['so_cccd'] ?: 'N/A') ?></code> &bull; BLX: <code><?= e($d['so_blx'] ?: 'Miễn') ?></code></div>
                                <button type="button" class="btn btn-link btn-sm p-0 text-info" data-bs-toggle="modal" data-bs-target="#modalHoSo<?= $idDon ?>">
                                    <i class="fa-solid fa-id-card me-1"></i>Xem ảnh VNeID / BLX
                                </button>
                            </td>
                            <td class="small">
                                <div class="fw-semibold text-dark"><?= e($d['ten_xe'] ?: 'Xe đã ngừng kinh doanh') ?></div>
                                <div>
                                    <strong><?= date('d/m/Y', strtotime($d['ngay_nhan'])) ?></strong> &rarr;
                                    <strong><?= date('d/m/Y', strtotime($d['ngay_tra'])) ?></strong> (<?= (int) $d['so_ngay'] ?> ngày)
                                </div>
                                <?php if ($soNgayQuaHan > 0): ?>
                                    <span class="badge bg-danger mt-1">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Quá hạn <?= $soNgayQuaHan ?> ngày!
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <div>Thuê: <strong><?= formatVnd($tienThue) ?></strong></div>
                                <div>Cọc: <strong style="color:#b45309;"><?= formatVnd($tienCoc) ?></strong></div>
                                <?php if ($phiQuaHan > 0 || $phiPhatSinh > 0): ?>
                                    <div class="text-danger fw-semibold">Khấu trừ: -<?= formatVnd($phiQuaHan + $phiPhatSinh) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= badgeDonAdmin($d['trang_thai']) ?></div>
                                <div class="small mt-1">
                                    <span class="badge bg-light text-dark border"><?= e($d['trang_thai_coc'] ?? 'Chưa thu') ?></span>
                                </div>
                                <?php if (!empty($d['han_hoan_coc']) && ($d['trang_thai_coc'] ?? '') === 'Giữ cọc phạt nguội 15 ngày'): ?>
                                    <div class="small text-muted mt-1">
                                        Hạn hoàn cọc: <strong><?= date('d/m/Y', strtotime($d['han_hoan_coc'])) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($d['ghi_chu_su_co'])): ?>
                                    <div class="small text-danger mt-1" style="max-width:210px;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($d['ghi_chu_su_co']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info text-dark fw-semibold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalChiTietTien<?= $idDon ?>">
                                        <i class="fa-solid fa-receipt me-1"></i>Chi tiết
                                    </button>

                                    <?php if (in_array($d['trang_thai'], ['Chờ duyệt', 'Chờ thanh toán'], true)): ?>
                                        <form method="POST" action="don_thue.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="don_id" value="<?= $idDon ?>">
                                            <input type="hidden" name="action" value="duyet_giao_xe">
                                            <button type="submit" class="btn btn-sm btn-primary fw-semibold text-nowrap">
                                                <i class="fa-solid fa-key me-1"></i>Duyệt giao xe
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($d['trang_thai'] === 'Đang thuê'): ?>
                                        <button type="button" class="btn btn-sm btn-success fw-semibold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalTraXe<?= $idDon ?>">
                                            <i class="fa-solid fa-clipboard-check me-1"></i>Nhận trả xe
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($d['trang_thai'] === 'Hoàn thành'): ?>
                                        <?php if (($d['trang_thai_coc'] ?? '') === 'Giữ cọc phạt nguội 15 ngày'): ?>
                                            <form method="POST" action="don_thue.php" class="d-inline"
                                                  onsubmit="return confirm('Xác nhận đã tra cứu không có phạt nguội và hoàn nốt cọc cho đơn #DH<?= $idDon ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="don_id" value="<?= $idDon ?>">
                                                <input type="hidden" name="action" value="hoan_not_coc">
                                                <button type="submit" class="btn btn-sm btn-outline-success fw-semibold text-nowrap">
                                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>Hoàn nốt cọc
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPhatNguoi<?= $idDon ?>">
                                            <i class="fa-solid fa-gavel me-1"></i>Phạt nguội
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!in_array($d['trang_thai'], ['Hoàn thành', 'Đã hủy'], true)): ?>
                                        <form method="POST" action="don_thue.php" class="d-inline"
                                              onsubmit="return confirm('Xác nhận hủy đơn #DH<?= $idDon ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="don_id" value="<?= $idDon ?>">
                                            <input type="hidden" name="action" value="huy_don">
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">
                                                <i class="fa-solid fa-ban me-1"></i>Hủy
                                            </button>
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

<?php ob_start(); ?>
<?php foreach ($dsDon as $d): ?>
    <?php
        $idDon = (int) $d['id'];
        $tienCoc = (float) ($d['tien_coc'] ?? 0);
        $tienThue = max(0, (float) $d['tong_tien'] - $tienCoc);
        $donGiaXe = (float) ($d['gia_thue'] ?? ($d['so_ngay'] > 0 ? $tienThue / $d['so_ngay'] : 150000));

        $soNgayQuaHan = 0;
        if ($d['trang_thai'] === 'Đang thuê' && $todayDate > $d['ngay_tra']) {
            $dtTraDuKien = new DateTime($d['ngay_tra']);
            $dtHomNay    = new DateTime($todayDate);
            $soNgayQuaHan = (int) $dtTraDuKien->diff($dtHomNay)->days;
        }
        $goiYPhatQuaHan = $soNgayQuaHan * $donGiaXe * 1.5;

        $tienThueGoc   = $tienThue;
        $phiQuaHan     = (float) ($d['phi_qua_han'] ?? 0);
        $phiPhatSinh   = (float) ($d['phi_phat_sinh'] ?? 0);
        $tongKhauTru   = $phiQuaHan + $phiPhatSinh;
        $ttCoc         = $d['trang_thai_coc'] ?? 'Chưa thu';
        $dtChiuThueDon = ($d['trang_thai'] === 'Hoàn thành') ? ($tienThueGoc + $phiQuaHan) : 0;

        if ($ttCoc === 'Đang giữ cọc') {
            $cocDangGiu = $tienCoc;
            $cocDaHoan  = 0;
        } elseif ($ttCoc === 'Giữ cọc phạt nguội 15 ngày') {
            $cocDangGiu = min(500000, max(0, $tienCoc - $tongKhauTru));
            $cocDaHoan  = max(0, $tienCoc - $tongKhauTru - $cocDangGiu);
        } elseif (in_array($ttCoc, ['Đã hoàn đủ', 'Đã khấu trừ phạt'], true)) {
            $cocDangGiu = 0;
            $cocDaHoan  = max(0, $tienCoc - $tongKhauTru);
        } else {
            $cocDangGiu = 0;
            $cocDaHoan  = 0;
        }
        $khachDongBu = max(0, $tongKhauTru - $tienCoc);
    ?>

    <div class="modal fade hkt-modal" id="modalChiTietTien<?= $idDon ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold mb-0">Chi tiết đơn    #DH<?= $idDon ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3">
                        Khách hàng: <strong class="text-dark"><?= e($d['ten_khach_hang']) ?></strong> &bull; Xe: <strong class="text-dark"><?= e($d['ten_xe'] ?: 'Xe máy') ?></strong>
                    </div>

                    <div class="p-3 rounded mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <div class="fw-bold text-success small text-uppercase mb-2">1. Doanh thu dịch vụ chịu thuế</div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Tiền thuê xe gốc (<?= (int) $d['so_ngay'] ?> ngày):</span>
                            <strong><?= formatVnd($tienThueGoc) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Phụ thu trả xe quá hạn (150%):</span>
                            <strong><?= formatVnd($phiQuaHan) ?></strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold text-success">
                            <span>Tổng doanh thu tính thuế của đơn:</span>
                            <span><?= formatVnd($dtChiuThueDon) ?></span>
                        </div>
                    </div>

                    <div class="p-3 rounded mb-3" style="background:#fef2f2;border:1px solid #fecaca;">
                        <div class="fw-bold text-danger small text-uppercase mb-2">2. Thu bồi thường sửa chữa &amp; nộp hộ phạt nguội (Tách thuế)</div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Khấu trừ sửa chữa hư hỏng / phạt nguội CSGT:</span>
                            <strong class="text-danger"><?= formatVnd($phiPhatSinh) ?></strong>
                        </div>
                        <?php if (!empty($d['ghi_chu_su_co'])): ?>
                            <div class="small text-danger mt-1">
                                <em>Biên bản: <?= e($d['ghi_chu_su_co']) ?></em>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted">Không phát sinh hư hỏng hay vi phạm phạt nguội.</div>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 rounded" style="background:#fffbeb;border:1px solid #fde68a;">
                        <div class="fw-bold small text-uppercase mb-2" style="color:#b45309;">3. Đối soát tiền đặt cọc tài sản (<?= e($ttCoc) ?>)</div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Tiền cọc khách đã nộp ban đầu:</span>
                            <strong><?= formatVnd($tienCoc) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Đã khấu trừ (Quá hạn + Hư hỏng/Phạt nguội):</span>
                            <strong class="text-danger">-<?= formatVnd(min($tienCoc, $tongKhauTru)) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Đang tạm giữ chờ đối soát (15 ngày / đang thuê):</span>
                            <strong style="color:#b45309;"><?= formatVnd($cocDangGiu) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Đã hoàn trả lại cho khách hàng:</span>
                            <strong class="text-success"><?= formatVnd($cocDaHoan) ?></strong>
                        </div>
                        <?php if ($khachDongBu > 0): ?>
                            <div class="mt-2 pt-2 border-top small fw-bold text-danger">
                                Khách hàng phải đóng bù thêm ngoài cọc: <?= formatVnd($khachDongBu) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="../hop_dong.php?id=<?= $idDon ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fa-solid fa-file-contract me-1"></i>Xem Hợp đồng
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade hkt-modal" id="modalHoSo<?= $idDon ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold mb-0">Hồ sơ định danh VNeID &amp; BLX — Đơn #DH<?= $idDon ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-1"><strong>Khách hàng:</strong> <?= e($d['ten_khach_hang']) ?></div>
                        <div class="col-md-6 mb-1"><strong>SĐT:</strong> <?= e($d['so_dien_thoai']) ?></div>
                        <div class="col-md-6 mb-1"><strong>Số VNeID / CCCD:</strong> <code><?= e($d['so_cccd'] ?: 'Chưa có') ?></code></div>
                        <div class="col-md-6 mb-1"><strong>Số BLX:</strong> <code><?= e($d['so_blx'] ?: 'Miễn BLX') ?></code></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 text-center">
                            <div class="fw-semibold mb-2">Ảnh chụp VNeID / CCCD</div>
                            <?php if (!empty($d['anh_vneid'])): ?>
                                <a href="../<?= e($d['anh_vneid']) ?>" target="_blank">
                                    <img src="../<?= e($d['anh_vneid']) ?>" class="img-fluid rounded border" style="max-height:260px;" alt="Ảnh VNeID">
                                </a>
                            <?php else: ?>
                                <div class="p-4 border rounded bg-light text-muted">Không có ảnh VNeID</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="fw-semibold mb-2">Ảnh chụp Bằng lái xe (BLX)</div>
                            <?php if (!empty($d['anh_blx'])): ?>
                                <a href="../<?= e($d['anh_blx']) ?>" target="_blank">
                                    <img src="../<?= e($d['anh_blx']) ?>" class="img-fluid rounded border" style="max-height:260px;" alt="Ảnh BLX">
                                </a>
                            <?php else: ?>
                                <div class="p-4 border rounded bg-light text-muted">Không có ảnh BLX (hoặc xe &le;50cc)</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="../hop_dong.php?id=<?= $idDon ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fa-solid fa-file-contract me-1"></i>Hợp đồng
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($d['trang_thai'] === 'Đang thuê'): ?>
    <div class="modal fade hkt-modal" id="modalTraXe<?= $idDon ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="don_thue.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="don_id" value="<?= $idDon ?>">
                    <input type="hidden" name="action" value="nhan_tra_xe">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold mb-0">Nghiệm thu Nhận trả xe — #DH<?= $idDon ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border small mb-3">
                            <div>Tiền cọc đang giữ của khách: <strong class="text-primary"><?= formatVnd($tienCoc) ?></strong></div>
                            <?php if ($soNgayQuaHan > 0): ?>
                                <div class="text-danger fw-semibold mt-1">
                                    Cảnh báo: Khách trả trễ <?= $soNgayQuaHan ?> ngày (Gợi ý phạt 150%: <?= formatVnd($goiYPhatQuaHan) ?>).
                                </div>
                            <?php else: ?>
                                <div class="text-success mt-1">Khách trả xe đúng hạn quy định.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Phí phạt quá hạn (VNĐ)</label>
                            <input type="number" name="phi_qua_han" class="form-control" min="0" step="1000" value="<?= (int) $goiYPhatQuaHan ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Phí bồi thường hư hỏng / mất phụ kiện (VNĐ)</label>
                            <input type="number" name="phi_hu_hong" class="form-control" min="0" step="1000" value="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Ghi chú biên bản nghiệm thu (nếu có)</label>
                            <textarea name="ghi_chu_su_co" class="form-control" rows="2" placeholder="Ví dụ: Xe nguyên vẹn, đầy đủ 2 mũ bảo hiểm..."></textarea>
                        </div>

                        <div class="form-check p-3 rounded" style="background:#fffbeb;border:1px solid #fde68a;">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="giu_coc_phat_nguoi" id="chkGiuCoc<?= $idDon ?>" value="1" checked>
                            <label class="form-check-label small fw-semibold text-dark" for="chkGiuCoc<?= $idDon ?>">
                                Giữ lại 500.000 ₫ cọc phạt nguội trong 15 ngày (theo Điều khoản), hoàn trả ngay phần cọc còn lại tại quầy.
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fa-solid fa-circle-check me-1"></i>Xác nhận trả xe &amp; Hoàn cọc
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($d['trang_thai'] === 'Hoàn thành'): ?>
    <div class="modal fade hkt-modal" id="modalPhatNguoi<?= $idDon ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="don_thue.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="don_id" value="<?= $idDon ?>">
                    <input type="hidden" name="action" value="xu_ly_phat_nguoi">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-danger mb-0">Ghi nhận Phạt nguội / Sự cố — #DH<?= $idDon ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Số tiền phạt theo thông báo CSGT (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="tien_phat_nguoi" class="form-control" min="0" step="1000" required placeholder="Ví dụ: 500000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nội dung lỗi vi phạm &amp; Số biên bản <span class="text-danger">*</span></label>
                            <textarea name="noi_dung_phat" class="form-control" rows="2" required placeholder="Ví dụ: Vượt đèn đỏ ngã tư Cầu Giấy ngày 20/11 - Biên bản CSGT số..."></textarea>
                        </div>
                        <div class="form-check p-3 rounded bg-light border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="khoa_tai_khoan" id="chkKhoaTk<?= $idDon ?>" value="1">
                            <label class="form-check-label small fw-semibold text-danger" for="chkKhoaTk<?= $idDon ?>">
                                Khóa tài khoản khách hàng này (Áp dụng khi tiền phạt vượt quá cọc 500k hoặc khách trốn tránh nộp phạt).
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-danger fw-bold">
                            <i class="fa-solid fa-gavel me-1"></i>Lưu biên bản &amp; Khấu trừ cọc
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php $adminModalsHtml = ob_get_clean(); ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
