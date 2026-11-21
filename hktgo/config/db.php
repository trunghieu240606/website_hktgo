<?php
/**
 * config/db.php
 * Kết nối CSDL MySQL bằng PDO (Chạy trực tiếp trên XAMPP / Laragon)
 * Tích hợp tự động hủy đơn giữ chỗ quá hạn 15 phút & đồng bộ tồn kho xe
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hktgo_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Cấu hình thông tin tài khoản nhận chuyển khoản VietQR của cửa hàng
define('VIETQR_BANK_ID', 'MB');
define('VIETQR_ACCOUNT_NO', '0365145525');
define('VIETQR_ACCOUNT_NAME', 'TRAN TRUNG HIEU');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px;">'
        . '<h3>Không thể kết nối Cơ sở dữ liệu MySQL (hktgo_db)</h3>'
        . '<p>Vui lòng bật <strong>MySQL</strong> trong XAMPP và import tệp <code>database/hktgo_db.sql</code> vào phpMyAdmin.</p>'
        . '<p><small>Chi tiết kỹ thuật: ' . htmlspecialchars($e->getMessage()) . '</small></p>'
        . '</div>');
}

/**
 * Tự động hủy các đơn ở trạng thái 'Chờ thanh toán' đã quá hạn 15 phút
 * và đồng bộ lại cột trang_thai ('Đang rảnh' / 'Hết xe') của bảng xe_may.
 * Hàm chạy tự động mỗi khi có người mở trang, không cần cài đặt Cronjob.
 */
function tuDongHuyDonQuaHanVaDongBoKho(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        UPDATE don_thue
        SET trang_thai = 'Đã hủy'
        WHERE trang_thai = 'Chờ thanh toán'
          AND han_thanh_toan IS NOT NULL
          AND han_thanh_toan < NOW()
    ");
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        dongBoTrangThaiTatCaXe($pdo);
    }
}

/**
 * Đồng bộ cột trang_thai ('Đang rảnh' hoặc 'Hết xe') cho 1 mẫu xe hoặc toàn bộ kho xe
 * dựa trên công thức: so_luong_con_lai = so_luong - số đơn đang giữ xe.
 */
function dongBoTrangThaiTatCaXe(PDO $pdo, ?int $xeId = null): void
{
    $sql = "
        UPDATE xe_may x
        LEFT JOIN (
            SELECT xe_may_id, COUNT(*) AS dang_giu
            FROM don_thue
            WHERE trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
            GROUP BY xe_may_id
        ) d ON d.xe_may_id = x.id
        SET x.trang_thai = CASE
            WHEN (x.so_luong - COALESCE(d.dang_giu, 0)) > 0 THEN 'Đang rảnh'
            ELSE 'Hết xe'
        END
    ";

    if ($xeId !== null) {
        $stmt = $pdo->prepare($sql . " WHERE x.id = ?");
        $stmt->execute([$xeId]);
    } else {
        $pdo->exec($sql);
    }
}

/**
 * Tính số lượng còn lại khả dụng của một mẫu xe.
 * Khi $lockForUpdate = true (bên trong Transaction đặt xe), khóa các dòng đơn liên quan để chống Race Condition.
 */
function tinhSoLuongConLai(PDO $pdo, int $xeId, int $tongSoLuong, bool $lockForUpdate = false): int
{
    $sql = "
        SELECT COUNT(*) FROM don_thue
        WHERE xe_may_id = ?
          AND trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
    ";
    if ($lockForUpdate) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$xeId]);
    $dangGiu = (int) $stmt->fetchColumn();

    return max(0, $tongSoLuong - $dangGiu);
}

/**
 * Định dạng số tiền VNĐ hiển thị trên giao diện.
 */
function formatVnd($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' ₫';
}

/**
 * Escape chuỗi an toàn chống XSS khi in ra HTML.
 */
function e(?string $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

// Tự động giải phóng các đơn giữ chỗ quá hạn 15 phút ngay khi nạp trang
tuDongHuyDonQuaHanVaDongBoKho($pdo);
