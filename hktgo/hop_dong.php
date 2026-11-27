<?php
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
    SELECT d.*, x.ten_xe, x.hang_xe, x.gia_thue, dm.ten AS ten_danh_muc
    FROM don_thue d
    LEFT JOIN xe_may x ON x.id = d.xe_may_id
    LEFT JOIN danh_muc_xe dm ON dm.id = x.danh_muc_xe_id
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hợp đồng cho thuê xe máy #DH<?= (int) $don['id'] ?> - HKTGO</title>
    <style>
        @page { size: A4; margin: 14mm 17mm; }
        body {
            background: #525659;
            margin: 0;
            padding: 20px 0;
            font-family: "Times New Roman", Times, serif;
            color: #000;
        }
        .toolbar {
            max-width: 794px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-pdf {
            background: #01d28e;
            color: #0b162c;
            border: none;
            padding: 8px 16px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 13px;
            text-decoration: none;
        }
        .btn-back {
            background: #ffffff;
            color: #1e293b;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: 600;
        }
        .page-a4 {
            background: #fff;
            width: 794px;
            min-height: 1123px;
            margin: 0 auto;
            padding: 50px 62px;
            box-sizing: border-box;
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            font-size: 15px;
            line-height: 1.48;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .divider { width: 165px; height: 1px; background: #000; margin: 4px auto 14px; }
        h4 { font-size: 15px; text-transform: uppercase; margin: 14px 0 5px; }
        p { margin: 4px 0; text-align: justify; }
        ul { margin: 4px 0 8px; padding-left: 22px; }
        li { margin-bottom: 4px; text-align: justify; }
        table.info { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        table.info th, table.info td { border: 1px solid #000; padding: 6px 10px; text-align: left; }
        table.info th { background: #f8fafc; width: 32%; }
        table.sign { width: 100%; margin-top: 26px; border-collapse: collapse; }
        table.sign td { width: 50%; text-align: center; vertical-align: top; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .page-a4 { width: 100%; min-height: auto; padding: 0; box-shadow: none; font-size: 11.5pt; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="<?= isAdmin() ? 'admin/don_thue.php' : 'don_cua_toi.php' ?>" class="btn-back">&larr; Quay lại danh sách đơn</a>
    <button type="button" onclick="window.print();" class="btn-pdf">Lưu file PDF / In hợp đồng</button>
</div>

<div class="page-a4">
    <div class="center">
        <div class="bold upper" style="font-size:16px;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
        <div class="bold" style="font-size:15.5px;">Độc lập – Tự do – Hạnh phúc</div>
        <div class="divider"></div>
        <div class="bold upper" style="font-size:20px;">HỢP ĐỒNG CHO THUÊ XE MÁY</div>
        <div>Số: <strong>#DH<?= (int) $don['id'] ?>/HĐTX-HKTGO</strong></div>
    </div>

    <p style="font-style:italic;font-size:14px;margin-top:10px;">
        Căn cứ Bộ luật Dân sự 2015; Căn cứ nhu cầu và thỏa thuận xác nhận điện tử giữa hai bên trên Hệ thống đặt xe trực tuyến HKTGO. Hôm nay, ngày <strong><?= date('d', $ngayLapTs) ?></strong> tháng <strong><?= date('m', $ngayLapTs) ?></strong> năm <strong><?= date('Y', $ngayLapTs) ?></strong>, tại Hà Nội, chúng tôi gồm:
    </p>

    <h4>I. BÊN CHO THUÊ (Sau đây gọi tắt là Bên A):</h4>
    <p><strong>Đơn vị kinh doanh:</strong> HỆ THỐNG CHO THUÊ XE MÁY HKTGO</p>
    <p><strong>Địa chỉ giao nhận chính:</strong> Cơ sở HKTGO Cầu Giấy, Thành phố Hà Nội &nbsp;|&nbsp; <strong>Điện thoại:</strong> 0365.145.525</p>

    <h4>II. BÊN THUÊ XE (Sau đây gọi tắt là Bên B):</h4>
    <p><strong>Ông/Bà:</strong> <?= e($don['ten_khach_hang']) ?> &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Số điện thoại:</strong> <?= e($don['so_dien_thoai']) ?></p>
    <p><strong>Số định danh / CCCD (VNeID):</strong> <?= e($don['so_cccd'] ?: 'Chưa cập nhật') ?> &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Giấy phép lái xe số:</strong> <?= e($don['so_blx'] ?: 'Miễn BLX (xe <=50cc)') ?></p>

    <p style="margin-top:8px;">Hai bên thống nhất ký kết Hợp đồng cho thuê xe máy với các điều khoản cụ thể sau:</p>

    <h4>Điều 1. Đặc điểm phương tiện và thời hạn thuê</h4>
    <table class="info">
        <tr>
            <th>Mẫu xe máy thuê</th>
            <td><strong><?= e($don['ten_xe'] ?: 'Xe máy HKTGO') ?></strong> (Hãng xe: <?= e($don['hang_xe'] ?: 'N/A') ?> - Loại: <?= e($don['ten_danh_muc'] ?: 'Xe máy') ?>)</td>
        </tr>
        <tr>
            <th>Thời hạn thuê</th>
            <td>Từ ngày <strong><?= date('d/m/Y', strtotime($don['ngay_nhan'])) ?></strong> đến hết ngày <strong><?= date('d/m/Y', strtotime($don['ngay_tra'])) ?></strong> (Tổng: <strong><?= (int) $don['so_ngay'] ?> ngày</strong>)</td>
        </tr>
        <tr>
            <th>Địa điểm giao / trả xe</th>
            <td>Nhận tại: <strong><?= e($don['diem_nhan']) ?></strong> &nbsp;|&nbsp; Trả tại: <strong><?= e($don['diem_tra']) ?></strong></td>
        </tr>
    </table>
    <p>Bên A cam đoan xe giao cho Bên B thuộc quyền sở hữu/quản lý hợp pháp, hoạt động an toàn. Bên B sử dụng xe đúng mục đích đi lại dân sự hợp pháp và đủ điều kiện điều khiển phương tiện theo quy định.</p>

    <h4>Điều 2. Giá thuê, tiền đặt cọc và phương thức thanh toán</h4>
    <ul>
        <li><strong>Đơn giá thuê:</strong> <?= formatVnd($don['gia_thue'] ?? 0) ?>/ngày. &nbsp;&nbsp; <strong>Tổng tiền thuê (<?= (int) $don['so_ngay'] ?> ngày):</strong> <?= formatVnd($tienThueXe) ?>.</li>
        <li><strong>Tiền đặt cọc tài sản:</strong> <?= formatVnd($tienCoc) ?>. &nbsp;&nbsp; <strong>Tổng thanh toán ban đầu:</strong> <strong><?= formatVnd($don['tong_tien']) ?></strong>.</li>
        <li><strong>Phương thức thanh toán:</strong> Chuyển khoản qua mã VietQR trên hệ thống HKTGO (giữ chỗ 15 phút) hoặc thanh toán trực tiếp tại quầy khi nhận xe.</li>
        <li><strong>Cơ chế hoàn cọc &amp; giữ cọc phạt nguội 15 ngày:</strong> Khi Bên B trả xe đúng hạn, nguyên vẹn, Bên A hoàn trả ngay phần tiền cọc tài sản và <strong>giữ lại 500.000 VNĐ trong vòng 15 ngày</strong> để đối chiếu dữ liệu phạt nguội giao thông; sau 15 ngày nếu không có vi phạm sẽ hoàn trả đủ cho Bên B.</li>
    </ul>

    <h4>Điều 3. Quyền, nghĩa vụ của các bên &amp; Xử lý vi phạm</h4>
    <ul>
        <li><strong>Bên A:</strong> Giao xe đúng chủng loại, thời gian thỏa thuận kèm giấy tờ xe hợp lệ; nhận lại xe khi hết hạn hợp đồng; được khấu trừ tiền cọc hoặc yêu cầu bồi thường nếu Bên B trả xe quá hạn (phạt 150% đơn giá ngày), làm hư hỏng, mất mát phụ kiện hoặc phát sinh phạt nguội.</li>
        <li><strong>Bên B:</strong> Thanh toán đầy đủ tiền thuê và tiền cọc; tự chi trả chi phí xăng dầu trong quá trình sử dụng; bảo quản xe nguyên vẹn và trả xe đúng thời hạn tại địa điểm đã đăng ký.</li>
    </ul>

    <h4>Điều 4. Hiệu lực hợp đồng</h4>
    <p>
        Hợp đồng có hiệu lực kể từ thời điểm Bên B xác nhận đồng ý điều khoản đặt xe trên hệ thống HKTGO (lúc <strong><?= date('H:i:s d/m/Y', $ngayLapTs) ?></strong>) và được thanh lý sau khi hai bên hoàn tất nghĩa vụ bàn giao xe, tất toán tiền cọc và xử lý vi phạm (nếu có). Hợp đồng được lập thành 02 (hai) bản có giá trị pháp lý như nhau, mỗi bên giữ 01 bản.
    </p>

    <table class="sign">
        <tr>
            <td>
                <div class="bold upper">ĐẠI DIỆN BÊN A</div>
                <div style="font-style:italic;font-size:13.5px;">(Ký, ghi rõ họ tên hoặc xác nhận điện tử)</div>
                <div class="bold" style="margin-top:34px;">HỆ THỐNG CHO THUÊ XE HKTGO</div>
            </td>
            <td>
                <div class="bold upper">ĐẠI DIỆN BÊN B</div>
                <div style="font-style:italic;font-size:13.5px;">(Ký, ghi rõ họ tên hoặc xác nhận điện tử)</div>
                <div class="bold" style="margin-top:34px;"><?= e(mb_strtoupper($don['ten_khach_hang'], 'UTF-8')) ?></div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
