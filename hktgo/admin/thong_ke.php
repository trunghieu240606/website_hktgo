<?php
/**
 * admin/thong_ke.php
 * Báo cáo & Thống kê Doanh thu theo Ngày và theo Tháng (Truy vấn SQL GROUP BY đơn giản, minh bạch)
 * Chỉ tổng hợp các đơn có trạng thái 'Hoàn thành'
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$adminUser = requireAdmin($pdo);

// 1. Các chỉ số tổng hợp nhanh
$tongDoanhThu = (float) $pdo->query("
    SELECT COALESCE(SUM(tong_tien), 0)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
")->fetchColumn();

$soDonHoanThanh = (int) $pdo->query("
    SELECT COUNT(*)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
")->fetchColumn();

$doanhThuThangNay = (float) $pdo->query("
    SELECT COALESCE(SUM(tong_tien), 0)
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
      AND YEAR(ngay_nhan) = YEAR(CURDATE())
      AND MONTH(ngay_nhan) = MONTH(CURDATE())
")->fetchColumn();

$doanhThuTamTinhDangThue = (float) $pdo->query("
    SELECT COALESCE(SUM(tong_tien), 0)
    FROM don_thue
    WHERE trang_thai IN ('Chờ duyệt', 'Đang thuê')
")->fetchColumn();

// 2. Bảng thống kê doanh thu theo Ngày (14 ngày phát sinh gần nhất)
$theoNgay = $pdo->query("
    SELECT ngay_nhan AS ngay,
           COUNT(*) AS so_don,
           SUM(so_ngay) AS tong_ngay_thue,
           SUM(tong_tien) AS doanh_thu
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
    GROUP BY ngay_nhan
    ORDER BY ngay_nhan DESC
    LIMIT 14
")->fetchAll();

// 3. Bảng thống kê doanh thu theo Tháng (12 tháng gần nhất)
$theoThang = $pdo->query("
    SELECT DATE_FORMAT(ngay_nhan, '%m/%Y') AS thang_nam,
           YEAR(ngay_nhan) AS nam,
           MONTH(ngay_nhan) AS thang,
           COUNT(*) AS so_don,
           SUM(so_ngay) AS tong_ngay_thue,
           SUM(tong_tien) AS doanh_thu
    FROM don_thue
    WHERE trang_thai = 'Hoàn thành'
    GROUP BY YEAR(ngay_nhan), MONTH(ngay_nhan), DATE_FORMAT(ngay_nhan, '%m/%Y')
    ORDER BY nam DESC, thang DESC
    LIMIT 12
")->fetchAll();

// 4. Bảng thống kê hiệu suất theo từng mẫu xe
$theoMauXe = $pdo->query("
    SELECT x.ten_xe, x.hang_xe, x.so_luong,
           COUNT(d.id) AS so_luot_hoan_thanh,
           COALESCE(SUM(d.tong_tien), 0) AS doanh_thu
    FROM xe_may x
    LEFT JOIN don_thue d ON d.xe_may_id = x.id AND d.trang_thai = 'Hoàn thành'
    GROUP BY x.id
    ORDER BY doanh_thu DESC, x.id ASC
")->fetchAll();

$pageTitle = 'Thống kê Doanh thu - HKTGO Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1 fw-bold text-dark">Thống kê Doanh thu theo Ngày &amp; Tháng</h1>
    <p class="text-muted mb-0">Số liệu doanh thu thực thu được tổng hợp trực tiếp từ các đơn thuê ở trạng thái <strong>Hoàn thành</strong></p>
</div>

<!-- 4 Thẻ chỉ số tài chính -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Tổng doanh thu hoàn thành</div>
                <h3 class="fw-bold text-success mb-0"><?= formatVnd($tongDoanhThu) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Số đơn đã hoàn thành</div>
                <h3 class="fw-bold text-primary mb-0"><?= $soDonHoanThanh ?> đơn</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Doanh thu trong tháng <?= date('m/Y') ?></div>
                <h3 class="fw-bold text-dark mb-0"><?= formatVnd($doanhThuThangNay) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold mb-1">Giá trị đơn đang thuê / chờ duyệt</div>
                <h3 class="fw-bold text-warning mb-0"><?= formatVnd($doanhThuTamTinhDangThue) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Bảng 1: Doanh thu theo Ngày -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-calendar-day text-primary me-2"></i>Doanh thu theo Ngày
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ngày nhận xe</th>
                                <th class="text-center">Số đơn hoàn thành</th>
                                <th class="text-center">Tổng ngày thuê</th>
                                <th class="pe-3 text-end">Doanh thu</th>
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
                                    <td class="text-center"><?= (int) $row['so_don'] ?> đơn</td>
                                    <td class="text-center"><?= (int) $row['tong_ngay_thue'] ?> ngày</td>
                                    <td class="pe-3 text-end fw-bold text-success"><?= formatVnd($row['doanh_thu']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng 2: Doanh thu theo Tháng -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-calendar-days text-danger me-2"></i>Doanh thu theo Tháng
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tháng / Năm</th>
                                <th class="text-center">Số đơn hoàn thành</th>
                                <th class="text-center">Tổng ngày thuê</th>
                                <th class="pe-3 text-end">Doanh thu</th>
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
                                    <td class="text-center"><?= (int) $row['so_don'] ?> đơn</td>
                                    <td class="text-center"><?= (int) $row['tong_ngay_thue'] ?> ngày</td>
                                    <td class="pe-3 text-end fw-bold text-success"><?= formatVnd($row['doanh_thu']) ?></td>
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

<!-- Bảng 3: Doanh thu theo từng Mẫu xe -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">
            <i class="fa-solid fa-motorcycle text-info me-2"></i>Tổng hợp Doanh thu theo Mẫu xe
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
                        <th class="text-center">Số lượt thuê hoàn thành</th>
                        <th class="pe-3 text-end">Doanh thu đóng góp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($theoMauXe as $mx): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><?= e($mx['ten_xe']) ?></td>
                        <td><?= e($mx['hang_xe']) ?></td>
                        <td class="text-center"><?= (int) $mx['so_luong'] ?> xe</td>
                        <td class="text-center"><?= (int) $mx['so_luot_hoan_thanh'] ?> lượt</td>
                        <td class="pe-3 text-end fw-bold text-primary"><?= formatVnd($mx['doanh_thu']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
