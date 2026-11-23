<?php
/**
 * hop_dong.php
 * Hợp đồng thuê xe máy số (Hợp đồng điện tử không chữ ký số CA,
 * tự động đổ dữ liệu từ đơn thuê, VNeID/BLX và hỗ trợ in/xuất PDF).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user  = requireLogin($pdo);
$donId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($donId <= 0) {
    setFlash('warning', 'Không tìm thấy mã hợp đồng cần xem.');
    header('Location: don_cua_toi.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.*, x.ten_xe, x.hang_xe, x.gia_thue, dm.ten AS ten_danh_muc, u.email AS user_email
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
    LEFT JOIN danh_muc_xe dm ON dm.id = x.danh_muc_xe_id
    LEFT JOIN users u ON u.id = d.user_id
    WHERE d.id = ?
    LIMIT 1
");
$stmt->execute([$donId]);
$don = $stmt->fetch();

if (!$don || ((int) $don['user_id'] !== (int) $user['id'] && !isAdmin())) {
    setFlash('danger', 'Bạn không có quyền xem hợp đồng này.');
    header('Location: don_cua_toi.php');
    exit;
}

$tienCoc    = (float) ($don['tien_coc'] ?? 0);
$tienThueXe = max(0, (float) $don['tong_tien'] - $tienCoc);
$ngayLapTs  = strtotime($don['ngay_tao'] ?? 'now');

$pageTitle = 'Hợp đồng thuê xe số #HD-HKTGO-' . $don['id'];
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .contract-paper {
        background: #ffffff;
        max-width: 880px;
        margin: 0 auto;
        padding: 46px 54px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        color: #1e293b;
        line-height: 1.7;
        font-size: 14.5px;
        overflow-x: hidden;
    }
    .contract-paper .table {
        min-width: 0 !important;
        width: 100% !important;
        table-layout: auto;
    }
    .contract-paper .table th,
    .contract-paper .table td,
    .contract-paper .table tbody tr td {
        padding: 8px 12px !important;
        vertical-align: middle !important;
        word-break: break-word;
    }
    .contract-section-title {
        font-weight: 700;
        color: #0f172a;
        text-transform: uppercase;
        font-size: 15px;
        margin-top: 18px;
        margin-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
    }
    @media (max-width: 767.98px) {
        .contract-paper {
            padding: 24px 18px;
            font-size: 13.5px;
        }
    }
    @media print {
        body * { visibility: hidden; }
        .contract-paper, .contract-paper * { visibility: visible; }
        .contract-paper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 100%;
            box-shadow: none;
            border: none;
            padding: 12px 20px;
        }
        .no-print { display: none !important; }
    }
</style>

<section class="py-5" style="background:#f1f5f9;">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mx-auto mb-3 no-print" style="max-width: 880px; gap: 8px;">
            <a href="<?= isAdmin() ? 'admin/don_thue.php' : 'don_cua_toi.php' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i>Quay lại danh sách đơn
            </a>
            <div class="d-flex flex-wrap" style="gap: 8px;">
                <a href="dieu_khoan.php" target="_blank" class="btn btn-outline-info btn-sm">
                    <i class="fa-solid fa-scale-balanced mr-1"></i>Xem quy định chung
                </a>
                <button type="button" onclick="window.print();" class="btn btn-primary btn-sm font-weight-bold">
                    <i class="fa-solid fa-print mr-1"></i>In Hợp đồng / Lưu PDF
                </button>
            </div>
        </div>

        <div class="contract-paper rounded">
            <!-- Quốc hiệu & Tiêu ngữ -->
            <div class="text-center mb-4">
                <div class="font-weight-bold" style="font-size:15px;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="font-weight-bold" style="font-size:15px;">Độc lập - Tự do - Hạnh phúc</div>
                <div style="width:180px;height:1px;background:#0f172a;margin:6px auto 18px;"></div>

                <h3 class="font-weight-bold text-uppercase mb-1" style="color:#0f172a;font-size:21px;">
                    HỢP ĐỒNG THUÊ XE MÁY ĐIỆN TỬ
                </h3>
                <div class="text-muted">
                    Số hợp đồng: <strong class="text-dark">HĐ-HKTGO-#DH<?= (int) $don['id'] ?></strong>
                    &bull; Ngày lập: <strong><?= date('d/m/Y H:i', $ngayLapTs) ?></strong>
                </div>
            </div>

            <p class="font-italic text-muted small mb-3">
                Căn cứ Bộ luật Dân sự nước Cộng hòa Xã hội Chủ nghĩa Việt Nam; Căn cứ nhu cầu và thỏa thuận xác nhận điện tử của hai bên trên Hệ thống cho thuê xe máy HKTGO, hôm nay ngày <?= date('d', $ngayLapTs) ?> tháng <?= date('m', $ngayLapTs) ?> năm <?= date('Y', $ngayLapTs) ?>, chúng tôi gồm có:
            </p>

            <!-- BÊN A -->
            <div class="contract-section-title">I. BÊN CHO THUÊ (BÊN A)</div>
            <div class="row mb-2">
                <div class="col-md-7"><strong>Đơn vị kinh doanh:</strong> HỆ THỐNG CHO THUÊ XE MÁY HKTGO</div>
                <div class="col-md-5"><strong>Hotline hỗ trợ:</strong> 0365.145.525</div>
                <div class="col-12"><strong>Địa chỉ giao nhận chính:</strong> Cơ sở HKTGO Cầu Giấy, Thành phố Hà Nội</div>
            </div>

            <!-- BÊN B -->
            <div class="contract-section-title">II. BÊN THUÊ XE (BÊN B)</div>
            <div class="row mb-2">
                <div class="col-md-6"><strong>Họ và tên:</strong> <?= e($don['ten_khach_hang']) ?></div>
                <div class="col-md-6"><strong>Số điện thoại:</strong> <?= e($don['so_dien_thoai']) ?></div>
                <div class="col-md-6"><strong>Số định danh / CCCD (VNeID):</strong> <?= e($don['so_cccd'] ?: 'Chưa cập nhật') ?></div>
                <div class="col-md-6"><strong>Số Giấy phép lái xe (BLX):</strong> <?= e($don['so_blx'] ?: 'Miễn BLX (xe <=50cc)') ?></div>
                <div class="col-12"><strong>Email tài khoản đặt đơn:</strong> <?= e($don['user_email'] ?: 'Không có') ?></div>
            </div>

            <!-- ĐIỀU 1: PHƯƠNG TIỆN & THỜI GIAN -->
            <div class="contract-section-title">III. THÔNG TIN PHƯƠNG TIỆN &amp; THỜI HẠN THUÊ</div>
            <table class="table table-bordered table-sm mt-2 mb-3">
                <tbody>
                    <tr>
                        <th style="width:32%;background:#f8fafc;">Mẫu xe thuê</th>
                        <td><strong><?= e($don['ten_xe'] ?: 'Mẫu xe đã ngừng kinh doanh') ?></strong> (Hãng: <?= e($don['hang_xe'] ?: 'N/A') ?> - Loại: <?= e($don['ten_danh_muc'] ?: 'Xe máy') ?>)</td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">Thời gian nhận xe</th>
                        <td>Từ ngày <strong><?= date('d/m/Y', strtotime($don['ngay_nhan'])) ?></strong> tại: <?= e($don['diem_nhan']) ?></td>
                    </tr>
                    <tr>
                        <th style="background:#f8fafc;">Thời gian trả xe dự kiến</th>
                        <td>Đến hết ngày <strong><?= date('d/m/Y', strtotime($don['ngay_tra'])) ?></strong> tại: <?= e($don['diem_tra']) ?> (Tổng: <strong><?= (int) $don['so_ngay'] ?> ngày</strong>)</td>
                    </tr>
                </tbody>
            </table>

            <!-- ĐIỀU 2: GIÁ THUÊ, ĐẶT CỌC & THANH TOÁN -->
            <div class="contract-section-title">IV. GIÁ THUÊ, TIỀN ĐẶT CỌC &amp; CƠ CHẾ HOÀN CỌC PHẠT NGUỘI</div>
            <table class="table table-bordered table-sm mt-2 mb-2">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th>Khoản mục chi phí</th>
                        <th class="text-right" style="width:28%;">Thành tiền (VNĐ)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1. Tiền thuê xe (<?= (int) $don['so_ngay'] ?> ngày &times; <?= formatVnd($don['gia_thue'] ?? 0) ?>/ngày)</td>
                        <td class="text-right font-weight-bold"><?= formatVnd($tienThueXe) ?></td>
                    </tr>
                    <tr>
                        <td>2. Tiền đặt cọc tài sản bảo đảm</td>
                        <td class="text-right font-weight-bold"><?= formatVnd($tienCoc) ?></td>
                    </tr>
                    <tr style="background:#f1f5f9;">
                        <td><strong>TỔNG GIÁ TRỊ THANH TOÁN BAN ĐẦU</strong></td>
                        <td class="text-right font-weight-bold text-primary" style="font-size:16px;"><?= formatVnd($don['tong_tien']) ?></td>
                    </tr>
                </tbody>
            </table>
            <ul class="small pl-3 mb-3">
                <li><strong>Hoàn cọc tài sản &amp; Giữ cọc phạt nguội 15 ngày:</strong> Khi Bên B trả xe đúng hạn, nguyên vẹn và không hư hỏng, Bên A hoàn trả ngay phần tiền cọc tài sản và <strong>giữ lại 500.000 ₫ trong vòng 15 ngày</strong> để đối chiếu dữ liệu phạt nguội giao thông. Sau 15 ngày nếu không phát sinh vi phạm, Bên A hoàn trả đủ 500.000 ₫ còn lại cho Bên B.</li>
                <li><strong>Trạng thái đơn hiện tại:</strong> <strong><?= e($don['trang_thai']) ?></strong> &bull; Trạng thái tiền cọc: <strong><?= e($don['trang_thai_coc'] ?? 'Chưa thu') ?></strong>.</li>
            </ul>

            <!-- ĐIỀU 3: CAM KẾT PHÁP LÝ -->
            <div class="contract-section-title">V. CAM KẾT CHUNG &amp; XÁC NHẬN ĐIỆN TỬ</div>
            <p class="small mb-2">
                1. Bên B cam kết thông tin định danh VNeID, Giấy phép lái xe đã cung cấp là trung thực, chính chủ; sử dụng xe đúng mục đích, tuân thủ Luật Giao thông đường bộ và chịu hoàn toàn trách nhiệm bồi thường nếu để xảy ra mất mát, hư hỏng hoặc vi phạm phạt nguội trong thời gian thuê.
            </p>
            <p class="small mb-3">
                2. Hai bên thống nhất sử dụng hình thức Hợp đồng điện tử được lập tự động trên hệ thống HKTGO. Bên B đã xác nhận đồng ý toàn bộ <a href="dieu_khoan.php" target="_blank">Điều khoản &amp; Chính sách thuê xe</a> vào lúc <strong><?= date('H:i:s d/m/Y', $ngayLapTs) ?></strong>.
            </p>

            <!-- XÁC NHẬN HAI BÊN -->
            <div class="row mt-4 pt-2 text-center">
                <div class="col-6">
                    <div class="font-weight-bold text-uppercase">ĐẠI DIỆN BÊN A</div>
                    <div class="small text-muted font-italic">(Xác nhận điện tử trên hệ thống)</div>
                    <div class="mt-3 p-2 border border-primary rounded d-inline-block text-primary font-weight-bold">
                        HỆ THỐNG HKTGO<br>
                        <small class="font-weight-normal">Đã xác thực giao dịch #DH<?= (int) $don['id'] ?></small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="font-weight-bold text-uppercase">ĐẠI DIỆN BÊN B</div>
                    <div class="small text-muted font-italic">(Đã xác nhận đồng ý điều khoản điện tử)</div>
                    <div class="mt-3 p-2 border border-success rounded d-inline-block text-success font-weight-bold">
                        <?= e(mb_strtoupper($don['ten_khach_hang'], 'UTF-8')) ?><br>
                        <small class="font-weight-normal">VNeID: <?= e($don['so_cccd'] ?: 'N/A') ?> &bull; Lúc <?= date('H:i d/m/Y', $ngayLapTs) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>