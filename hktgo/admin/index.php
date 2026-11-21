<?php
/**
 * admin/index.php
 * Bảng điều khiển tổng quan (Dashboard) dành cho Quản trị viên (ADMIN)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Tổng quan Quản trị - HKTGO';
require_once __DIR__ . '/../includes/admin_header.php';

// 1. Thống kê các chỉ số KPI tổng quan
$tongDon       = (int) $pdo->query("SELECT COUNT(*) FROM don_thue")->fetchColumn();
$donChoDuyet   = (int) $pdo->query("SELECT COUNT(*) FROM don_thue WHERE trang_thai IN ('Chờ thanh toán', 'Chờ duyệt')")->fetchColumn();
$tongDoanhThu  = (float) $pdo->query("SELECT COALESCE(SUM(tong_tien), 0) FROM don_thue WHERE trang_thai = 'Hoàn thành'")->fetchColumn();
$tongMauXe     = (int) $pdo->query("SELECT COUNT(*) FROM xe_may")->fetchColumn();
$tongDauXe     = (int) $pdo->query("SELECT COALESCE(SUM(so_luong), 0) FROM xe_may")->fetchColumn();
$tongDangGiu   = (int) $pdo->query("SELECT COUNT(*) FROM don_thue WHERE trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')")->fetchColumn();
$tongKhaDung   = max(0, $tongDauXe - $tongDangGiu);
$tongTaiKhoan  = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Danh sách 6 đơn thuê gần nhất
$donGanDay = $pdo->query("
    SELECT d.*, x.ten_xe
    FROM don_thue d
    INNER JOIN xe_may x ON x.id = d.xe_may_id
    ORDER BY d.id DESC
    LIMIT 6
")->fetchAll();

// 3. Tồn kho theo mẫu xe
$khoXe = $pdo->query("
    SELECT x.id, x.ten_xe, x.hang_xe, x.gia_thue, x.so_luong, dm.ten AS ten_danh_muc,
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

function adminBadgeDon(string $tt): string
{
    switch ($tt) {
        case 'Chờ thanh toán':
            return '<span class="badge badge-status-cho">Chờ thanh toán</span>';
        case 'Chờ duyệt':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Chờ duyệt</span>';
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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark">Bảng điều khiển Quản trị</h1>
        <p class="text-muted mb-0">Theo dõi nhanh hoạt động đặt xe, tồn kho khả dụng và doanh thu hoàn thành</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <a href="xe.php" class="btn btn-primary btn-sm fw-semibold">
            <i class="fa-solid fa-plus me-1"></i>Quản lý Kho xe
        </a>
        <a href="don_thue.php" class="btn btn-outline-primary btn-sm fw-semibold">
            <i class="fa-solid fa-clipboard-check me-1"></i>Xử lý Đơn thuê
        </a>
    </div>
</div>

<!-- 4 Thẻ KPI -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Tổng đơn thuê</div>
                    <h3 class="fw-bold mb-1"><?= $tongDon ?></h3>
                    <span class="small text-warning fw-semibold">
                        <i class="fa-solid fa-clock me-1"></i><?= $donChoDuyet ?> đơn chờ xử lý
                    </span>
                </div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Doanh thu hoàn thành</div>
                    <h3 class="fw-bold text-success mb-1"><?= formatVnd($tongDoanhThu) ?></h3>
                    <a href="thong_ke.php" class="small text-decoration-none">Xem bảng thống kê &rarr;</a>
                </div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Tồn kho xe khả dụng</div>
                    <h3 class="fw-bold mb-1"><?= $tongKhaDung ?> / <?= $tongDauXe ?> xe</h3>
                    <span class="small text-muted"><?= $tongMauXe ?> mẫu xe &bull; <?= $tongDangGiu ?> xe đang giữ</span>
                </div>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Tài khoản hệ thống</div>
                    <h3 class="fw-bold mb-1"><?= $tongTaiKhoan ?></h3>
                    <a href="khach_hang.php" class="small text-decoration-none">Phân quyền Set Role &rarr;</a>
                </div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Bảng đơn thuê gần đây -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">Đơn thuê phát sinh gần đây</h5>
                <a href="don_thue.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mã</th>
                                <th>Khách hàng</th>
                                <th>Xe thuê</th>
                                <th>Tổng tiền</th>
                                <th class="pe-3">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donGanDay as $d): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">#DH<?= (int) $d['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($d['ten_khach_hang']) ?></div>
                                    <small class="text-muted"><?= e($d['so_dien_thoai']) ?></small>
                                </td>
                                <td>
                                    <div><?= e($d['ten_xe']) ?></div>
                                    <small class="text-muted"><?= date('d/m', strtotime($d['ngay_nhan'])) ?> &rarr; <?= date('d/m/Y', strtotime($d['ngay_tra'])) ?></small>
                                </td>
                                <td class="fw-semibold"><?= formatVnd($d['tong_tien']) ?></td>
                                <td class="pe-3"><?= adminBadgeDon($d['trang_thai']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng tình trạng tồn kho từng mẫu xe -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">Tồn kho xe theo thời gian thực</h5>
                <a href="xe.php" class="btn btn-sm btn-outline-secondary">Cập nhật kho</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mẫu xe</th>
                                <th>Giá/ngày</th>
                                <th class="pe-3 text-end">Còn lại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($khoXe as $x):
                                $cl = (int) $x['con_lai'];
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold"><?= e($x['ten_xe']) ?></div>
                                    <small class="text-muted"><?= e($x['ten_danh_muc'] ?? 'Chưa phân loại') ?> &bull; <?= e($x['hang_xe']) ?></small>
                                </td>
                                <td class="small fw-semibold"><?= formatVnd($x['gia_thue']) ?></td>
                                <td class="pe-3 text-end">
                                    <?php if ($cl > 0): ?>
                                        <span class="badge badge-status-ranh">Còn <?= $cl ?>/<?= (int) $x['so_luong'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-status-huy">Hết (0/<?= (int) $x['so_luong'] ?>)</span>
                                    <?php endif; ?>
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
