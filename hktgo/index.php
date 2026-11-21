<?php
/**
 * index.php
 * Trang chủ HKTGO:
 * - Hero Banner + Quy trình 3 bước
 * - Bộ sưu tập xe kiểu Accordion Showcase tương tác (y hệt mẫu thiết kế: thẻ dọc thu gọn + thẻ chi tiết mở rộng
 *   với chữ chìm nền, 4 ô tiện ích 02 Mũ BH / 02 Áo mưa / Giá đỡ ĐT / Baga & dây chằng đồ, giá & xe lấy từ CSDL HKTGO)
 * - Giới thiệu cam kết, Đánh giá khách hàng & Thủ tục thuê xe
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$danhMucId = isset($_GET['danh_muc']) ? (int) $_GET['danh_muc'] : 0;
$tuKhoa    = trim($_GET['q'] ?? '');
$trangThai = trim($_GET['trang_thai'] ?? '');

// Lấy danh sách danh mục xe cho bộ lọc
$dsDanhMuc = $pdo->query("SELECT id, ten FROM danh_muc_xe ORDER BY thu_tu ASC, ten ASC")->fetchAll();

// Số liệu thực tế từ CSDL HKTGO
$tongDauXeKho = (int) $pdo->query("SELECT COALESCE(SUM(so_luong), 0) FROM xe_may")->fetchColumn();
$tongMauXeKho = (int) $pdo->query("SELECT COUNT(*) FROM xe_may")->fetchColumn();

// Truy vấn danh sách xe kết hợp đếm số đơn đang giữ xe (Chờ thanh toán, Chờ duyệt, Đang thuê)
$sql = "
    SELECT x.*, dm.ten AS ten_danh_muc,
           COALESCE(d.dang_giu, 0) AS so_dang_giu,
           GREATEST(0, x.so_luong - COALESCE(d.dang_giu, 0)) AS con_lai
    FROM xe_may x
    LEFT JOIN danh_muc_xe dm ON dm.id = x.danh_muc_xe_id
    LEFT JOIN (
        SELECT xe_may_id, COUNT(*) AS dang_giu
        FROM don_thue
        WHERE trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
        GROUP BY xe_may_id
    ) d ON d.xe_may_id = x.id
    WHERE 1=1
";
$params = [];

if ($danhMucId > 0) {
    $sql .= " AND x.danh_muc_xe_id = ?";
    $params[] = $danhMucId;
}
if ($tuKhoa !== '') {
    $sql .= " AND (x.ten_xe LIKE ? OR x.hang_xe LIKE ?)";
    $kw = '%' . $tuKhoa . '%';
    $params[] = $kw;
    $params[] = $kw;
}
if ($trangThai === 'con_xe') {
    $sql .= " AND (x.so_luong - COALESCE(d.dang_giu, 0)) > 0";
} elseif ($trangThai === 'het_xe') {
    $sql .= " AND (x.so_luong - COALESCE(d.dang_giu, 0)) <= 0";
}

$sql .= " ORDER BY x.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dsXe = $stmt->fetchAll();

$dangLoc = ($danhMucId > 0 || $tuKhoa !== '' || $trangThai !== '');

/**
 * Sinh nhãn phân loại, mô tả đặc trưng và chữ watermark nền cho từng dòng xe trong kho HKTGO
 */
function thongTinShowcaseXe(array $xe): array
{
    $dm  = mb_strtolower((string) ($xe['ten_danh_muc'] ?? ''), 'UTF-8');
    $ten = (string) ($xe['ten_xe'] ?? '');
    $hang = trim((string) ($xe['hang_xe'] ?? 'Honda'));

    if (mb_strpos($dm, 'điện') !== false) {
        $tag = 'XE ĐIỆN · ÊM ÁI';
        $desc = 'Vận hành êm ái không khói bụi, cốp rộng tiện lợi di chuyển dạo phố nội đô Hà Nội.';
    } elseif (mb_strpos($dm, 'côn') !== false) {
        $tag = 'XE XĂNG · CÔN TAY';
        $desc = 'Côn tay thể thao 155cc mạnh mẽ — bốc, đầm chắc, cực kỳ phù hợp đi phượt và cung đường dài.';
    } elseif (mb_strpos($dm, 'số') !== false) {
        $tag = 'XE XĂNG · XE SỐ';
        $desc = 'Bền bỉ, gọn nhẹ và siêu tiết kiệm nhiên liệu — linh hoạt luồn lách phố cổ lẫn ngoại thành.';
    } else {
        $tag = 'XE XĂNG · TAY GA';
        $desc = 'Tay ga tự động thời trang, sàn để chân thoáng và cốp rộng — lựa chọn số 1 đi phố & công tác.';
    }

    // Tách tên dòng xe ngắn để hiển thị trên cột thu gọn và làm chữ chìm nền (VD: VISION 2024, EXCITER 155...)
    $shortName = trim(preg_replace('/^' . preg_quote($hang, '/') . '\s+/iu', '', $ten));
    if ($shortName === '') {
        $shortName = $ten;
    }

    $words = preg_split('/\s+/', strtoupper($shortName));
    $watermark = $words[0] ?? 'HKTGO';
    if (strlen($watermark) <= 2 && isset($words[1])) {
        $watermark .= ' ' . $words[1];
    }

    return [
        'tag'        => $tag,
        'short_name' => mb_strtoupper($shortName, 'UTF-8'),
        'full_title' => mb_strtoupper($ten, 'UTF-8'),
        'hang_upper' => mb_strtoupper($hang, 'UTF-8'),
        'desc'       => $desc,
        'watermark'  => $watermark,
    ];
}

// Mặc định mở rộng mẫu xe côn tay nổi bật (hoặc xe đầu tiên còn hàng) giống bố cục mẫu
$defaultActiveIndex = 0;
foreach ($dsXe as $idx => $itemXe) {
    if (mb_stripos((string) ($itemXe['ten_danh_muc'] ?? ''), 'côn') !== false && (int) $itemXe['con_lai'] > 0) {
        $defaultActiveIndex = $idx;
        break;
    }
}

$pageTitle = 'HKTGO - Hệ thống cho thuê xe máy tự lái trực tuyến';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    html {
        scroll-behavior: smooth;
    }
    .hero-stat-pill {
        background: rgba(255, 255, 255, 0.11);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        padding: 12px 16px;
        backdrop-filter: blur(4px);
    }
    .step-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e6ecf5;
        padding: 24px 22px;
        height: 100%;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .step-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.11);
    }
    .step-icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 14px;
    }

    /* =====================================================================
       SHOWCASE ACCORDION XE MÁY (ĐÚNG PHONG CÁCH ẢNH MẪU, DỮ LIỆU HKTGO)
       ===================================================================== */
    .hkt-showcase-wrapper {
        display: flex;
        gap: 10px;
        align-items: stretch;
        min-height: 505px;
        overflow-x: auto;
        padding: 6px 2px 10px;
    }
    .hkt-showcase-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1.5px solid #ece8dc;
        overflow: hidden;
        position: relative;
        cursor: pointer;
        flex: 1 1 95px;
        min-width: 92px;
        display: flex;
        flex-direction: column;
        transition: flex 0.42s cubic-bezier(0.25, 0.8, 0.25, 1),
                    border-color 0.3s ease,
                    box-shadow 0.3s ease;
        box-shadow: 0 8px 24px rgba(20, 20, 20, 0.04);
    }
    .hkt-showcase-card:hover:not(.is-active) {
        border-color: #dfbe4c;
        box-shadow: 0 12px 28px rgba(168, 132, 20, 0.12);
    }
    .hkt-showcase-card.is-active {
        flex: 4.8 1 480px;
        min-width: 460px;
        border: 2px solid #e2c044;
        cursor: default;
        box-shadow: 0 16px 38px rgba(166, 134, 24, 0.14);
    }

    /* Trạng thái thu gọn (thẻ dọc bên trái) */
    .sc-compact {
        display: flex;
        flex-direction: column;
        height: 100%;
        width: 100%;
    }
    .hkt-showcase-card.is-active .sc-compact {
        display: none;
    }
    .sc-compact-img-wrap {
        flex: 1;
        min-height: 315px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        padding: 14px 6px 6px;
    }
    .sc-compact-img-wrap img {
        width: 165%;
        max-width: none;
        height: 290px;
        object-fit: contain;
        object-position: left center;
        transition: transform 0.35s ease;
    }
    .hkt-showcase-card:hover .sc-compact-img-wrap img {
        transform: scale(1.05);
    }
    .sc-compact-stock-dot {
        position: absolute;
        top: 12px;
        left: 12px;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid #e5e7eb;
        color: #065f46;
    }
    .sc-compact-stock-dot.out {
        color: #991b1b;
        background: #fef2f2;
        border-color: #fecaca;
    }
    .sc-compact-info {
        background: #f9f5eb;
        padding: 16px 14px 18px;
        border-top: 1px solid #f0ead8;
    }
    .sc-compact-cat {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.4px;
        color: #9a7b1c;
        text-transform: uppercase;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sc-compact-brand {
        font-size: 10.5px;
        font-weight: 600;
        color: #8c8c8c;
        text-transform: uppercase;
        line-height: 1.2;
    }
    .sc-compact-name {
        font-size: 14.5px;
        font-weight: 800;
        color: #18181b;
        line-height: 1.25;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sc-compact-bar {
        width: 24px;
        height: 2.5px;
        background: #e5b80b;
        margin: 10px 0 8px;
        border-radius: 2px;
    }
    .sc-compact-price {
        font-size: 17px;
        font-weight: 800;
        color: #8d6e11;
        line-height: 1.2;
    }
    .sc-compact-price small {
        font-size: 11.5px;
        font-weight: 500;
        color: #9ca3af;
    }

    /* Trạng thái mở rộng (thẻ lớn bên phải) */
    .sc-expanded {
        display: none;
        height: 100%;
        width: 100%;
        position: relative;
        padding: 30px 30px 28px 34px;
        background: #ffffff;
        z-index: 1;
    }
    .hkt-showcase-card.is-active .sc-expanded {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sc-watermark {
        position: absolute;
        left: 24px;
        bottom: -14px;
        font-size: 96px;
        font-weight: 900;
        letter-spacing: 3px;
        color: rgba(214, 203, 178, 0.24);
        line-height: 1;
        pointer-events: none;
        user-select: none;
        z-index: 0;
        white-space: nowrap;
    }
    .sc-exp-left {
        width: 56%;
        position: relative;
        z-index: 2;
        padding-right: 14px;
    }
    .sc-exp-right {
        width: 44%;
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .sc-exp-right img {
        width: 100%;
        max-height: 330px;
        object-fit: contain;
        filter: drop-shadow(0 16px 22px rgba(0, 0, 0, 0.12));
    }
    .sc-pill-badge {
        display: inline-block;
        padding: 4px 13px;
        border-radius: 999px;
        border: 1px solid #d6c79c;
        background: #fdfbf5;
        color: #57481c;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        margin-bottom: 12px;
    }
    .sc-exp-title {
        font-size: 29px;
        font-weight: 800;
        color: #141414;
        line-height: 1.18;
        margin-bottom: 10px;
        letter-spacing: -0.3px;
    }
    .sc-exp-desc {
        font-size: 13.8px;
        color: #6b7280;
        line-height: 1.55;
        margin-bottom: 18px;
    }
    .sc-perks-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .sc-perk-box {
        background: #f7f2e4;
        border: 1px solid #ede4cc;
        border-radius: 10px;
        padding: 9px 14px;
    }
    .sc-perk-box strong {
        display: block;
        font-size: 14.5px;
        font-weight: 800;
        color: #856404;
        line-height: 1.2;
    }
    .sc-perk-box span {
        display: block;
        font-size: 10.5px;
        font-weight: 600;
        color: #78716c;
        text-transform: uppercase;
        margin-top: 2px;
    }
    .sc-exp-price-row {
        margin-bottom: 4px;
    }
    .sc-exp-price {
        font-size: 31px;
        font-weight: 800;
        color: #111827;
        letter-spacing: -0.5px;
    }
    .sc-exp-price small {
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
    }
    .sc-exp-note {
        font-size: 12px;
        color: #9ca3af;
        margin-bottom: 18px;
    }
    .sc-exp-actions {
        display: flex;
        align-items: center;
    }
    .sc-btn-book {
        height: 46px;
        padding: 0 28px;
        border-radius: 12px;
        background: #f5ce1b;
        border: none;
        color: #18181b !important;
        font-weight: 800;
        font-size: 14px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
        box-shadow: 0 8px 20px rgba(245, 206, 27, 0.35);
        transition: transform 0.2s ease, background 0.2s ease;
    }
    .sc-btn-book:hover {
        background: #eab308;
        transform: translateY(-2px);
    }

    @media (max-width: 991.98px) {
        .hkt-showcase-wrapper {
            flex-direction: column;
            min-height: 0;
        }
        .hkt-showcase-card,
        .hkt-showcase-card.is-active {
            flex: none;
            min-width: 100%;
            width: 100%;
        }
        .sc-compact {
            flex-direction: row;
            align-items: center;
        }
        .sc-compact-img-wrap {
            min-height: 120px;
            width: 40%;
            padding: 8px;
        }
        .sc-compact-img-wrap img {
            width: 100%;
            height: 105px;
            object-position: center;
        }
        .sc-compact-info {
            width: 60%;
            border-top: none;
            border-left: 1px solid #f0ead8;
        }
        .hkt-showcase-card.is-active .sc-expanded {
            flex-direction: column-reverse;
            padding: 22px;
        }
        .sc-exp-left, .sc-exp-right {
            width: 100%;
            padding-right: 0;
        }
        .sc-exp-right {
            margin-bottom: 16px;
        }
        .sc-watermark {
            font-size: 56px;
        }
    }

    .about-band {
        background: linear-gradient(135deg, #0b1936 0%, #153268 100%);
        color: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 18px 42px rgba(11, 25, 54, 0.22);
    }
    .feature-box-item {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e6ecf5;
        padding: 22px 18px;
        height: 100%;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }
    .review-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e6ecf5;
        padding: 26px 24px;
        height: 100%;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
    }
    .review-avatar {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e0f2fe;
    }
    .counter-box {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e6ecf5;
        padding: 24px 18px;
        text-align: center;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        height: 100%;
    }
</style>

<!-- 1. HERO BANNER NỔI BẬT -->
<section class="hkt-hero-banner" style="padding: 78px 0 92px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <h1 class="font-weight-bold text-white mb-3" style="font-size: 40px; line-height: 1.22;">
                    Hành Trình Chủ Động Cùng Dịch Vụ Thuê Xe Máy HKT<span style="color:#01d28e;">GO</span>
                </h1>
                <div class="d-flex flex-wrap" style="gap: 12px;">
                    <a href="#dan-xe" class="btn btn-warning btn-lg font-weight-bold px-4" style="background:#f5ce1b;border-color:#f5ce1b;color:#18181b;">
                        <i class="fa-solid fa-motorcycle mr-2"></i>Chọn xe ngay
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. QUY TRÌNH ĐẶT THUÊ XE 3 BƯỚC NHANH GỌN -->
<section id="quy-trinh" style="margin-top: -46px; position: relative; z-index: 3;" class="pb-3">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="step-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="step-icon-circle" style="background:#fef9c3;color:#a16207;">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <span class="badge badge-light border text-muted">Bước 01</span>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-2">Chọn xe trên dàn xe</h5>
                    <p class="text-muted small mb-0">
                        Chọn dòng xe muốn trải nghiệm với đầy đủ loại xe và mẫu mã
                    </p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="step-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="step-icon-circle" style="background:#d1fae5;color:#059669;">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <span class="badge badge-light border text-muted">Bước 02</span>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-2">Điền thông tin & Quét mã QR</h5>
                    <p class="text-muted small mb-0">
                        Điền đầy đủ thông tin chính xác và thanh toán.
                    </p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="step-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="step-icon-circle" style="background:#e0f2fe;color:#0284c7;">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <span class="badge badge-light border text-muted">Bước 03</span>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-2">Nhận bàn giao xe &amp; Khởi hành</h5>
                    <p class="text-muted small mb-0">
                        Duyệt đơn nhanh chóng, bàn giao xe kèm đủ mũ bảo hiểm, áo mưa và giấy tờ tại điểm hẹn.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. BỘ SƯU TẬP DÀN XE ACCORDION SHOWCASE -->
<section id="dan-xe" class="py-5">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="font-weight-bold text-uppercase small" style="color:#b48a14;letter-spacing:1px;">DỊCH VỤ THUÊ XE MÁY HKTGO</span>
                <h2 class="font-weight-bold text-dark mt-1 mb-1">Chọn Mẫu Xe Đồng Hành Cùng Bạn</h2>
            </div>
        </div>

        <!-- Thanh lọc nhanh theo danh mục / từ khóa -->
        <div class="content-card mb-4 py-3 px-4">
            <form method="GET" action="index.php#dan-xe" class="row align-items-end">
                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="font-weight-bold small text-dark mb-1">Tìm theo tên xe hoặc hãng xe</label>
                    <input type="text" name="q" class="form-control" value="<?= e($tuKhoa) ?>" placeholder="VD: Vision, Future, Wave, Exciter, SH...">
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="font-weight-bold small text-dark mb-1">Danh mục loại xe</label>
                    <select name="danh_muc" class="form-control">
                        <option value="0">-- Tất cả danh mục --</option>
                        <?php foreach ($dsDanhMuc as $dm): ?>
                            <option value="<?= (int) $dm['id'] ?>" <?= $danhMucId === (int) $dm['id'] ? 'selected' : '' ?>>
                                <?= e($dm['ten']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="font-weight-bold small text-dark mb-1">Tình trạng xe</label>
                    <select name="trang_thai" class="form-control">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="con_xe" <?= $trangThai === 'con_xe' ? 'selected' : '' ?>>Còn xe sẵn sàng</option>
                        <option value="het_xe" <?= $trangThai === 'het_xe' ? 'selected' : '' ?>>Tạm hết xe</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-dark btn-block font-weight-bold mr-1" style="height:44px;">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i>
                    </button>
                    <?php if ($dangLoc): ?>
                        <a href="index.php#dan-xe" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="height:44px;" title="Xóa bộ lọc">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (empty($dsXe)): ?>
            <div class="content-card text-center py-5">
                <i class="fa-solid fa-motorcycle fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Không tìm thấy mẫu xe nào phù hợp với điều kiện lọc.</h5>
                <a href="index.php#dan-xe" class="btn btn-outline-primary btn-sm mt-2">Xem toàn bộ các dòng xe</a>
            </div>
        <?php else: ?>
            <!-- Dàn thẻ xe kiểu Horizontal Accordion Showcase -->
            <div class="hkt-showcase-wrapper" id="hktShowcase">
                <?php foreach ($dsXe as $idx => $xe):
                    $conLai  = (int) $xe['con_lai'];
                    $soLuong = (int) $xe['so_luong'];
                    $conXe   = ($conLai > 0);
                    $anh     = !empty($xe['hinh_anh']) ? $xe['hinh_anh'] : 'images/car-1.jpg';
                    // Phòng trường hợp DB cũ còn lưu car-6.jpg là ảnh Yamaha Sirius trong khi tên xe là SH 150i
                    if ($anh === 'images/car-6.jpg' && stripos($xe['ten_xe'], 'SH') !== false) {
                        $anh = 'images/car-7.jpg';
                    }
                    $meta      = thongTinShowcaseXe($xe);
                    $giaFormat = number_format((float) $xe['gia_thue'], 0, ',', '.') . 'đ';
                    $isActive  = ($idx === $defaultActiveIndex);
                ?>
                <div class="hkt-showcase-card <?= $isActive ? 'is-active' : '' ?>" data-showcase-card>
                    <!-- Giao diện khi thu gọn (cột dọc bên trái) -->
                    <div class="sc-compact">
                        <div class="sc-compact-img-wrap">
                            <span class="sc-compact-stock-dot <?= $conXe ? '' : 'out' ?>">
                                <?= $conXe ? ('Còn ' . $conLai . '/' . $soLuong) : 'Hết xe' ?>
                            </span>
                            <img src="<?= e($anh) ?>" alt="<?= e($xe['ten_xe']) ?>" onerror="this.src='images/car-1.jpg'">
                        </div>
                        <div class="sc-compact-info">
                            <div class="sc-compact-cat"><?= e($meta['tag']) ?></div>
                            <div class="sc-compact-brand"><?= e($meta['hang_upper']) ?></div>
                            <div class="sc-compact-name" title="<?= e($xe['ten_xe']) ?>"><?= e($meta['short_name']) ?></div>
                            <div class="sc-compact-bar"></div>
                            <div class="sc-compact-price"><?= $giaFormat ?> <small>/ngày</small></div>
                        </div>
                    </div>

                    <!-- Giao diện khi mở rộng (khung lớn bên phải) -->
                    <div class="sc-expanded">
                        <div class="sc-watermark"><?= e($meta['watermark']) ?></div>

                        <div class="sc-exp-left">
                            <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;">
                                <span class="sc-pill-badge mb-0"><?= e($meta['tag']) ?></span>
                                <?php if ($conXe): ?>
                                    <span class="badge-stock-ok">
                                        <i class="fa-solid fa-circle-check mr-1"></i>Sẵn sàng: Còn <?= $conLai ?>/<?= $soLuong ?> xe
                                    </span>
                                <?php else: ?>
                                    <span class="badge-stock-out">
                                        <i class="fa-solid fa-ban mr-1"></i>Tạm hết xe (0/<?= $soLuong ?>)
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 class="sc-exp-title"><?= e($meta['full_title']) ?></h3>
                            <p class="sc-exp-desc"><?= e($meta['desc']) ?></p>

                            <!-- 4 Ô trang bị tiêu chuẩn đi kèm (y hệt bố cục mẫu) -->
                            <div class="sc-perks-grid">
                                <div class="sc-perk-box">
                                    <strong>02</strong>
                                    <span>Mũ bảo hiểm đạt chuẩn</span>
                                </div>
                                <div class="sc-perk-box">
                                    <strong>02</strong>
                                    <span>Áo mưa 1 lần trong cốp</span>
                                </div>
                                <div class="sc-perk-box">
                                    <strong>Có</strong>
                                    <span>Giá đỡ điện thoại</span>
                                </div>
                                <div class="sc-perk-box">
                                    <strong>Có</strong>
                                    <span>Baga &amp; dây chằng đồ</span>
                                </div>
                            </div>

                            <div class="sc-exp-price-row">
                                <span class="sc-exp-price"><?= $giaFormat ?></span>
                                <small>/ngày</small>
                            </div>
                            <div class="sc-exp-note">
                                Đơn giá niêm yết trên HKTGO &bull; Giữ chỗ tự động 15 phút khi thanh toán mã QR
                            </div>

                            <div class="sc-exp-actions">
                                <?php if ($conXe): ?>
                                    <a href="dat_xe.php?xe_id=<?= (int) $xe['id'] ?>" class="sc-btn-book" onclick="event.stopPropagation();">
                                        Đặt thuê xe
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary font-weight-bold px-4" style="height:46px;border-radius:12px;" disabled>
                                        Tạm hết xe
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sc-exp-right">
                            <img src="<?= e($anh) ?>" alt="<?= e($xe['ten_xe']) ?>" onerror="this.src='images/car-1.jpg'">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- 4. GIỚI THIỆU VỀ HKTGO & CAM KẾT DỊCH VỤ -->
<section class="py-4">
    <div class="container">
        <div class="about-band mb-5">
            <div class="row no-gutters align-items-center">
                <div class="col-lg-5">
                    <img src="images/bg.jpg" alt="Dịch vụ cho thuê xe máy HKTGO"
                         style="width:100%;height:360px;object-fit:cover;display:block;"
                         onerror="this.src='images/bg_2.jpg'">
                </div>
                <div class="col-lg-7 p-4 p-lg-5">
                    <span class="badge px-3 py-1 mb-2" style="background:rgba(1,210,142,0.2);color:#01d28e;font-weight:600;">
                        VỀ CỬA HÀNG HKTGO
                    </span>
                    <h3 class="font-weight-bold text-white mb-3">Đồng Hành Tin Cậy Trên Mọi Cung Đường Nội Đô &amp; Phượt</h3>
                    <p class="text-white-50 mb-4" style="line-height: 1.75;">
                        Tại HKTGO, mỗi chiếc xe trước khi bàn giao đều được kiểm tra kỹ phanh, lốp, đèn tín hiệu và thay dầu định kỳ. Thủ tục thuê xe đơn giản chỉ cần CCCD gắn chip/VNeID, hợp đồng rõ ràng và hỗ trợ kỹ thuật xuyên suốt hành trình.
                    </p>
                    <div class="row text-white small">
                        <div class="col-sm-6 mb-2"><i class="fa-solid fa-check-circle mr-2" style="color:#01d28e;"></i>Xe đời mới tiết kiệm xăng</div>
                        <div class="col-sm-6 mb-2"><i class="fa-solid fa-check-circle mr-2" style="color:#01d28e;"></i>Không thu phụ phí ẩn ngoài đơn giá niêm yết</div>
                        <div class="col-sm-6 mb-2"><i class="fa-solid fa-check-circle mr-2" style="color:#01d28e;"></i>Giao nhận linh hoạt tại Cầu Giấy, Mỹ Đình, Bắc Từ Liêm</div>
                        <div class="col-sm-6 mb-2"><i class="fa-solid fa-check-circle mr-2" style="color:#01d28e;"></i>Hỗ trợ đổi xe nhanh nếu phát sinh sự cố kỹ thuật</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Cam kết nổi bật -->
        <div class="row">
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="feature-box-item text-center">
                    <div class="step-icon-circle mx-auto" style="background:#eff6ff;color:#1d4ed8;">
                        <i class="fa-solid fa-shield-heart"></i>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-2">Xe Bảo Dưỡng Định Kỳ</h6>
                    <p class="text-muted small mb-0">100% xe máy được kiểm tra an toàn kỹ thuật trước mỗi lượt giao xe cho khách.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="feature-box-item text-center">
                    <div class="step-icon-circle mx-auto" style="background:#ecfdf5;color:#059669;">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-2">Giá Thuê Minh Bạch</h6>
                    <p class="text-muted small mb-0">Tính đúng theo số ngày thuê theo lịch thực tế, cam kết không tăng giá đột xuất.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="feature-box-item text-center">
                    <div class="step-icon-circle mx-auto" style="background:#fef3c7;color:#d97706;">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-2">Giao Xe Đúng Hẹn</h6>
                    <p class="text-muted small mb-0">Nhận xe trực tiếp tại cửa hàng hoặc đặt giao tận bến xe, khách sạn nội thành Hà Nội.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="feature-box-item text-center">
                    <div class="step-icon-circle mx-auto" style="background:#f3e8ff;color:#7c3aed;">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <h6 class="font-weight-bold text-dark mb-2">Hỗ Trợ Tận Tâm 24/7</h6>
                    <p class="text-muted small mb-0">Đội ngũ cửa hàng luôn sẵn sàng hướng dẫn lộ trình và hỗ trợ khách trong suốt chuyến đi.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. ĐÁNH GIÁ THỰC TẾ TỪ KHÁCH HÀNG -->
<section id="danh-gia" class="py-5" style="background: #eef3fa; border-top: 1px solid #dde6f3; border-bottom: 1px solid #dde6f3;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="font-weight-bold text-dark mt-1 mb-2">Khách Hàng Nói Gì Về HKTGO?</h2>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="review-card">
                    <div class="mb-3 text-warning">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <p class="text-secondary mb-4" style="font-style: italic; line-height: 1.7;">
                        “Mình đặt chiếc Honda Vision 2024 đi công tác 3 ngày ở Cầu Giấy. Web báo còn bao nhiêu xe rất chuẩn, quét mã VietQR giữ chỗ 15 phút xong qua cửa hàng là nhận xe ngay, máy êm và cốp rộng.”
                    </p>
                    <div class="d-flex align-items-center mt-auto pt-3 border-top">
                        <img src="images/person_1.jpg" alt="Anh Nguyễn Hoàng Nam" class="review-avatar mr-3" onerror="this.src='images/car-1.jpg'">
                        <div>
                            <h6 class="font-weight-bold text-dark mb-0">Nguyễn Hoàng Nam</h6>
                            <small class="text-muted">Khách từ TP. Đà Nẵng</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="review-card">
                    <div class="mb-3 text-warning">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <p class="text-secondary mb-4" style="font-style: italic; line-height: 1.7;">
                        “Nhóm mình thuê 2 chiếc Exciter 155 đi Ba Vì cuối tuần. Xe có sẵn giá đỡ điện thoại với baga chằng đồ rất tiện, cửa hàng chuẩn bị đủ 2 mũ bảo hiểm sạch thơm và áo mưa trong cốp.”
                    </p>
                    <div class="d-flex align-items-center mt-auto pt-3 border-top">
                        <img src="images/person_2.jpg" alt="Bạn Lê Minh Tuấn" class="review-avatar mr-3" onerror="this.src='images/car-2.jpg'">
                        <div>
                            <h6 class="font-weight-bold text-dark mb-0">Lê Minh Tuấn</h6>
                            <small class="text-muted">Sinh viên Hunre</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="review-card">
                    <div class="mb-3 text-warning">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star-half-stroke"></i>
                    </div>
                    <p class="text-secondary mb-4" style="font-style: italic; line-height: 1.7;">
                        “Ấn tượng nhất là giá thuê hiển thị rõ ràng từng ngày, không lo phát sinh chi phí mập mờ. Mình bận đổi lịch cũng vào mục Đơn của tôi theo dõi trạng thái rất tiện, chắc chắn sẽ quay lại ủng hộ!”
                    </p>
                    <div class="d-flex align-items-center mt-auto pt-3 border-top">
                        <img src="images/person_3.jpg" alt="Chị Vũ Thu Hà" class="review-avatar mr-3" onerror="this.src='images/car-3.jpg'">
                        <div>
                            <h6 class="font-weight-bold text-dark mb-0">Vũ Thu Hà</h6>
                            <small class="text-muted">Khách du lịch Hà Nội</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dải con số thống kê uy tín -->
        <div class="row mt-3">
            <div class="col-6 col-md-3 mb-3 mb-md-0">
                <div class="counter-box">
                    <h3 class="font-weight-bold text-primary mb-1">5+ Năm</h3>
                    <span class="text-muted small">Kinh nghiệm cho thuê xe</span>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3 mb-md-0">
                <div class="counter-box">
                    <h3 class="font-weight-bold text-primary mb-1"><?= $tongDauXeKho ?>+ Xe</h3>
                    <span class="text-muted small">Đa dạng tay ga, xe số, côn tay</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="counter-box">
                    <h3 class="font-weight-bold text-primary mb-1">1.500+</h3>
                    <span class="text-muted small">Chuyến thuê hoàn thành an toàn</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="counter-box">
                    <h3 class="font-weight-bold text-success mb-1">98%</h3>
                    <span class="text-muted small">Khách hàng hài lòng quay lại</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 6. CÂU HỎI THƯỜNG GẶP VỀ THỦ TỤC THUÊ XE -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-5 mb-4 mb-lg-0">
                <span class="text-primary font-weight-bold text-uppercase small" style="letter-spacing:1px;">THÔNG TIN CẦN BIẾT</span>
                <h3 class="font-weight-bold text-dark mt-1 mb-3">Thủ Tục &amp; Chính Sách Thuê Xe Tại HKTGO</h3>
                <p class="text-muted mb-4">
                    Để quá trình nhận xe diễn ra nhanh nhất trong vòng 5 phút, quý khách vui lòng chuẩn bị trước giấy tờ tùy thân cơ bản dưới đây.
                </p>
                <a href="#dan-xe" class="btn btn-warning font-weight-bold px-4" style="background:#f5ce1b;border-color:#f5ce1b;color:#18181b;">
                    <i class="fa-solid fa-arrow-up mr-2"></i>Chọn mẫu xe ngay
                </a>
            </div>
            <div class="col-lg-7">
                <div class="content-card mb-3 py-3">
                    <h6 class="font-weight-bold text-dark mb-1">
                        <i class="fa-solid fa-id-card text-primary mr-2"></i>1. Khi đến nhận xe cần mang theo giấy tờ gì?
                    </h6>
                    <p class="text-muted small mb-0">
                        Khách thuê chỉ cần xuất trình <strong>CCCD gắn chip</strong> (hoặc Hộ chiếu đối với khách nước ngoài) và <strong>Giấy phép lái xe</strong> hợp lệ để đối chiếu khi ký biên bản bàn giao xe.
                    </p>
                </div>
                <div class="content-card mb-3 py-3">
                    <h6 class="font-weight-bold text-dark mb-1">
                        <i class="fa-solid fa-clock text-success mr-2"></i>2. Đặt xe và thanh toán như thế nào?
                    </h6>
                    <p class="text-muted small mb-0">
                        Ngay khi bạn chọn được chiếc xe ưng ý, hãy điền thông tin của bạn và thanh toán là đã có thể sở hữu một chiếc xe để chinh phục hàng vạn con đường.
                    </p>
                </div>
                <div class="content-card py-3">
                    <h6 class="font-weight-bold text-dark mb-1">
                        <i class="fa-solid fa-helmet-safety text-warning mr-2"></i>3. Trang bị đi kèm theo xe gồm những gì?
                    </h6>
                    <p class="text-muted small mb-0">
                        Mỗi xe khi giao đều trang bị sẵn <strong>02 mũ bảo hiểm</strong>, <strong>02 áo mưa</strong>, <strong>giá đỡ điện thoại</strong>, <strong>baga &amp; dây chằng đồ</strong> cùng giấy tờ xe hợp lệ.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var cards = document.querySelectorAll('[data-showcase-card]');
    if (!cards.length) return;
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.classList.contains('is-active')) return;
            cards.forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
