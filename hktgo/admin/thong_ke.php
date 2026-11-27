<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

$exprDoanhThuThuan = "GREATEST(0, tong_tien - COALESCE(tien_coc, 0)) + COALESCE(phi_qua_han, 0)";

$tongDoanhThuThuan = (float) $pdo->query("
    SELECT COALESCE(SUM($exprDoanhThuThuan), 0)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
")->fetchColumn();

$doanhThuThangNay = (float) $pdo->query("
    SELECT COALESCE(SUM($exprDoanhThuThuan), 0)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
      AND YEAR(ngay_nhan) = YEAR(CURDATE())
      AND MONTH(ngay_nhan) = MONTH(CURDATE())
")->fetchColumn();

$quyCocDangGiu = (float) $pdo->query("
    SELECT COALESCE(SUM(
        CASE
            WHEN trang_thai_coc = 'Đang giữ cọc' THEN COALESCE(tien_coc, 0)
            WHEN trang_thai_coc = 'Giữ cọc phạt nguội 15 ngày' THEN LEAST(500000, GREATEST(0, COALESCE(tien_coc, 0) - COALESCE(phi_qua_han, 0) - COALESCE(phi_phat_sinh, 0)))
            ELSE 0
        END
    ), 0)
    FROM don_thue
")->fetchColumn();

$quyBoiThuongPhatNguoi = (float) $pdo->query("
    SELECT COALESCE(SUM(COALESCE(phi_phat_sinh, 0)), 0)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
")->fetchColumn();

$doanhThuTamTinhDangThue = (float) $pdo->query("
    SELECT COALESCE(SUM(GREATEST(0, tong_tien - COALESCE(tien_coc, 0))), 0)
    FROM don_thue
    WHERE trang_thai IN ('Chờ duyệt', 'Đang thuê')
")->fetchColumn();

$soDonHoanThanh = (int) $pdo->query("
    SELECT COUNT(*)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
")->fetchColumn();

$theoNgay = $pdo->query("
    SELECT ngay_nhan AS ngay,
           COUNT(*) AS so_don,
           SUM(so_ngay) AS tong_ngay_thue,
           SUM($exprDoanhThuThuan) AS doanh_thu,
           SUM(COALESCE(phi_phat_sinh, 0)) AS thu_boi_thuong
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
    GROUP BY ngay_nhan
    ORDER BY ngay_nhan DESC
    LIMIT 10
")->fetchAll();

$theoThang = $pdo->query("
    SELECT DATE_FORMAT(ngay_nhan, '%m/%Y') AS thang_nam,
           YEAR(ngay_nhan) AS nam,
           MONTH(ngay_nhan) AS thang,
           COUNT(*) AS so_don,
           SUM(so_ngay) AS tong_ngay_thue,
           SUM($exprDoanhThuThuan) AS doanh_thu,
           SUM(COALESCE(phi_phat_sinh, 0)) AS thu_boi_thuong
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
    GROUP BY YEAR(ngay_nhan), MONTH(ngay_nhan), DATE_FORMAT(ngay_nhan, '%m/%Y')
    ORDER BY nam DESC, thang DESC
    LIMIT 12
")->fetchAll();

$theoMauXe = $pdo->query("
    SELECT x.ten_xe, x.hang_xe, x.so_luong,
           COUNT(d.id) AS so_luot_hoan_thanh,
           COALESCE(SUM(GREATEST(0, d.tong_tien - COALESCE(d.tien_coc, 0)) + COALESCE(d.phi_qua_han, 0)), 0) AS doanh_thu,
           COALESCE(SUM(COALESCE(d.phi_phat_sinh, 0)), 0) AS thu_boi_thuong
    FROM xe_may x
    LEFT JOIN don_thue d ON d.xe_may_id = x.id AND d.trang_thai = 'Hoàn thành'
    GROUP BY x.id
    ORDER BY doanh_thu DESC, x.id ASC
")->fetchAll();

$locDoiSoat = trim($_GET['loc'] ?? '');
$tuKhoaDon  = trim($_GET['q_don'] ?? '');
$perPage    = 10;
$page       = max(1, (int) ($_GET['page'] ?? 1));

$whereDoiSoat = "(d.trang_thai = 'Hoàn thành' OR d.trang_thai_coc IN ('Đang giữ cọc', 'Giữ cọc phạt nguội 15 ngày'))";
$paramsDon    = [];

if ($locDoiSoat === 'hoan_thanh') {
    $whereDoiSoat = "d.trang_thai = 'Hoàn thành'";
} elseif ($locDoiSoat === 'giu_coc') {
    $whereDoiSoat = "d.trang_thai_coc IN ('Đang giữ cọc', 'Giữ cọc phạt nguội 15 ngày')";
} elseif ($locDoiSoat === 'co_phat') {
    $whereDoiSoat = "(COALESCE(d.phi_qua_han, 0) > 0 OR COALESCE(d.phi_phat_sinh, 0) > 0)";
}

if ($tuKhoaDon !== '') {
    $maSo = (int) preg_replace('/[^0-9]/', '', $tuKhoaDon);
    if ($maSo > 0 && preg_match('/^#?(dh)?\d+$/i', $tuKhoaDon)) {
        $whereDoiSoat .= " AND d.id = ?";
        $paramsDon[] = $maSo;
    } else {
        $whereDoiSoat .= " AND (d.ten_khach_hang LIKE ? OR d.so_dien_thoai LIKE ? OR x.ten_xe LIKE ?)";
        $kw = '%' . $tuKhoaDon . '%';
        $paramsDon[] = $kw;
        $paramsDon[] = $kw;
        $paramsDon[] = $kw;
    }
}

$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
    WHERE $whereDoiSoat
");
$stmtCount->execute($paramsDon);
$totalDoiSoat = (int) $stmtCount->fetchColumn();
$totalPages   = max(1, (int) ceil($totalDoiSoat / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmtDs = $pdo->prepare("
    SELECT d.*, x.ten_xe, x.hang_xe, x.gia_thue
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
    WHERE $whereDoiSoat
    ORDER BY d.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmtDs->execute($paramsDon);
$dsDoiSoatDon = $stmtDs->fetchAll();

function buildThongKeUrl(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $k => $v) {
        if ($v === '' || $v === null) {
            unset($query[$k]);
        }
    }
    return 'thong_ke.php' . (!empty($query) ? '?' . http_build_query($query) : '') . '#bang-doi-soat';
}

$pageTitle = 'Thống kê Doanh thu';
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

<div class="mb-4">
    <h1 class="h3 mb-1 fw-bold text-dark">Thống kê Doanh thu</h1>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Doanh thu (<?= $soDonHoanThanh ?> đơn)</div>
                <h3 class="fw-bold text-success mb-1 price-nowrap"><?= formatVnd($tongDoanhThuThuan) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Quỹ tiền cọc đang tạm giữ</div>
                <h3 class="fw-bold mb-1 price-nowrap" style="color:#b45309;"><?= formatVnd($quyCocDangGiu) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Thu bồi thường sửa xe và phạt nguội</div>
                <h3 class="fw-bold text-danger mb-1 price-nowrap"><?= formatVnd($quyBoiThuongPhatNguoi) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Tiền thuê đơn đang chạy / chờ duyệt</div>
                <h3 class="fw-bold text-primary mb-1 price-nowrap"><?= formatVnd($doanhThuTamTinhDangThue) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-calendar-day text-primary me-2"></i>Doanh thu thuần theo Ngày (10 ngày gần nhất)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ngày nhận xe</th>
                                <th class="text-center">Số đơn</th>
                                <th class="text-end">Doanh thu thuế</th>
                                <th class="pe-3 text-end">Bồi thường/Phạt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($theoNgay)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Chưa có dữ liệu đơn hoàn thành.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($theoNgay as $row): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold"><?= date('d/m/Y', strtotime($row['ngay'])) ?></td>
                                    <td class="text-center"><?= (int) $row['so_don'] ?> đơn (<?= (int) $row['tong_ngay_thue'] ?>n)</td>
                                    <td class="text-end fw-bold text-success"><?= formatVnd($row['doanh_thu']) ?></td>
                                    <td class="pe-3 text-end text-danger small"><?= formatVnd($row['thu_boi_thuong']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-calendar-days text-danger me-2"></i>Doanh thu thuần theo Tháng
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tháng / Năm</th>
                                <th class="text-center">Số đơn</th>
                                <th class="text-end">Doanh thu thuế</th>
                                <th class="pe-3 text-end">Bồi thường/Phạt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($theoThang)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Chưa có dữ liệu đơn hoàn thành.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($theoThang as $row): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold">Tháng <?= e($row['thang_nam']) ?></td>
                                    <td class="text-center"><?= (int) $row['so_don'] ?> đơn (<?= (int) $row['tong_ngay_thue'] ?>n)</td>
                                    <td class="text-end fw-bold text-success"><?= formatVnd($row['doanh_thu']) ?></td>
                                    <td class="pe-3 text-end text-danger small"><?= formatVnd($row['thu_boi_thuong']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">
            <i class="fa-solid fa-motorcycle text-info me-2"></i>Tổng hợp Doanh thu thuần theo Mẫu xe
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mẫu xe</th>
                        <th>Hãng sản xuất</th>
                        <th class="text-center">Tổng đầu xe</th>
                        <th class="text-center">Lượt hoàn thành</th>
                        <th class="text-end">Doanh thu thuần</th>
                        <th class="pe-3 text-end">Thu bồi thường/phạt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($theoMauXe as $mx): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><?= e($mx['ten_xe']) ?></td>
                        <td><?= e($mx['hang_xe']) ?></td>
                        <td class="text-center"><?= (int) $mx['so_luong'] ?> xe</td>
                        <td class="text-center"><?= (int) $mx['so_luot_hoan_thanh'] ?> lượt</td>
                        <td class="text-end fw-bold text-primary"><?= formatVnd($mx['doanh_thu']) ?></td>
                        <td class="pe-3 text-end text-danger small"><?= formatVnd($mx['thu_boi_thuong']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm" id="bang-doi-soat">
    <div class="card-header bg-white py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="card-title mb-0 fw-bold">
                <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Chi tiết đơn
            </h5>
            <span class="small text-muted">Hiển thị tối đa <?= $perPage ?> đơn/trang</span>
        </div>

        <form method="GET" action="thong_ke.php#bang-doi-soat" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="q_don" class="form-control form-control-sm" value="<?= e($tuKhoaDon) ?>" placeholder="Tìm mã đơn">
            </div>
            <div class="col-md-4">
                <select name="loc" class="form-select form-select-sm">
                    <option value="" <?= $locDoiSoat === '' ? 'selected' : '' ?>>-- Tất cả đơn hoàn thành &amp; giữ cọc --</option>
                    <option value="hoan_thanh" <?= $locDoiSoat === 'hoan_thanh' ? 'selected' : '' ?>>Chỉ đơn đã Hoàn thành (Tính thuế)</option>
                    <option value="giu_coc" <?= $locDoiSoat === 'giu_coc' ? 'selected' : '' ?>>Đơn đang giữ cọc (Thuê / 15 ngày)</option>
                    <option value="co_phat" <?= $locDoiSoat === 'co_phat' ? 'selected' : '' ?>>Đơn có phát sinh Bồi thường / Phạt</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Tra cứu
                </button>
                <a href="thong_ke.php#bang-doi-soat" class="btn btn-sm btn-outline-secondary" title="Đặt lại bộ lọc">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã đơn</th>
                        <th>Khách hàng &amp; Xe thuê</th>
                        <th class="text-end">Doanh thu chịu thuế<br><small class="fw-normal text-muted">(Thuê + Quá hạn)</small></th>
                        <th class="text-end">Cọc ban đầu</th>
                        <th class="text-end">Bồi thường / Phạt nguội<br><small class="fw-normal text-muted">(Không tính thuế)</small></th>
                        <th>Tình trạng cọc</th>
                        <th class="pe-3 text-end">Đối soát</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dsDoiSoatDon)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Không tìm thấy đơn hàng nào phù hợp với điều kiện lọc.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dsDoiSoatDon as $d): ?>
                        <?php
                            $idDon       = (int) $d['id'];
                            $tienCoc     = (float) ($d['tien_coc'] ?? 0);
                            $tienThueGoc = max(0, (float) $d['tong_tien'] - $tienCoc);
                            $phiQuaHan   = (float) ($d['phi_qua_han'] ?? 0);
                            $phiPhatSinh = (float) ($d['phi_phat_sinh'] ?? 0);
                            $dtDon       = ($d['trang_thai'] === 'Hoàn thành') ? ($tienThueGoc + $phiQuaHan) : 0;
                            $ttCoc       = $d['trang_thai_coc'] ?? 'Chưa thu';
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary">#DH<?= $idDon ?></td>
                            <td class="small">
                                <div class="fw-semibold text-dark"><?= e($d['ten_khach_hang']) ?></div>
                                <div class="text-muted"><?= e($d['ten_xe'] ?: 'Xe máy HKTGO') ?> (<?= date('d/m/Y', strtotime($d['ngay_nhan'])) ?> &rarr; <?= date('d/m/Y', strtotime($d['ngay_tra'])) ?>)</div>
                            </td>
                            <td class="text-end fw-bold text-success">
                                <?= $d['trang_thai'] === 'Hoàn thành' ? formatVnd($dtDon) : '<span class="text-muted fw-normal">Chưa chốt</span>' ?>
                            </td>
                            <td class="text-end fw-semibold" style="color:#b45309;"><?= formatVnd($tienCoc) ?></td>
                            <td class="text-end fw-semibold <?= $phiPhatSinh > 0 ? 'text-danger' : 'text-muted' ?>">
                                <?= $phiPhatSinh > 0 ? formatVnd($phiPhatSinh) : '0 ₫' ?>
                            </td>
                            <td class="small">
                                <span class="badge bg-light text-dark border"><?= e($ttCoc) ?></span>
                                <?php if ($d['trang_thai'] !== 'Hoàn thành'): ?>
                                    <span class="badge bg-info text-dark ms-1"><?= e($d['trang_thai']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalChiTietTien<?= $idDon ?>">
                                    <i class="fa-solid fa-eye me-1"></i>Chi tiết
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-muted">
            Hiển thị <strong><?= count($dsDoiSoatDon) ?></strong> / <strong><?= $totalDoiSoat ?></strong> đơn
        </div>
        <nav aria-label="Phân trang đối soát">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(buildThongKeUrl(['page' => max(1, $page - 1)])) ?>">&laquo; Trước</a>
                </li>
                <?php
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(buildThongKeUrl(['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(buildThongKeUrl(['page' => min($totalPages, $page + 1)])) ?>">Sau &raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php ob_start(); ?>
<?php foreach ($dsDoiSoatDon as $d): ?>
    <?php
        $idDon          = (int) $d['id'];
        $tienCoc        = (float) ($d['tien_coc'] ?? 0);
        $tienThueGoc    = max(0, (float) $d['tong_tien'] - $tienCoc);
        $phiQuaHan      = (float) ($d['phi_qua_han'] ?? 0);
        $phiPhatSinh    = (float) ($d['phi_phat_sinh'] ?? 0);
        $tongKhauTru    = $phiQuaHan + $phiPhatSinh;
        $ttCoc          = $d['trang_thai_coc'] ?? 'Chưa thu';

        $dtChiuThueDon  = ($d['trang_thai'] === 'Hoàn thành') ? ($tienThueGoc + $phiQuaHan) : 0;

        if ($ttCoc === 'Đang giữ cọc') {
            $cocDangGiu  = $tienCoc;
            $cocDaHoan   = 0;
        } elseif ($ttCoc === 'Giữ cọc phạt nguội 15 ngày') {
            $cocDangGiu  = min(500000, max(0, $tienCoc - $tongKhauTru));
            $cocDaHoan   = max(0, $tienCoc - $tongKhauTru - $cocDangGiu);
        } elseif (in_array($ttCoc, ['Đã hoàn đủ', 'Đã khấu trừ phạt'], true)) {
            $cocDangGiu  = 0;
            $cocDaHoan   = max(0, $tienCoc - $tongKhauTru);
        } else {
            $cocDangGiu  = 0;
            $cocDaHoan   = 0;
        }
        $khachDongBu    = max(0, $tongKhauTru - $tienCoc);
    ?>
    <div class="modal fade hkt-modal" id="modalChiTietTien<?= $idDon ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold mb-0">Bóc tách Tài chính &amp; Thuế — Đơn #DH<?= $idDon ?></h5>
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
<?php endforeach; ?>
<?php $adminModalsHtml = ob_get_clean(); ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
