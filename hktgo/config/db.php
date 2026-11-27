<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hktgo_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('VIETQR_BANK_ID', 'VCB');
define('VIETQR_ACCOUNT_NO', '1051351814');
define('VIETQR_ACCOUNT_NAME', 'TRAN TRUNG HIEU');

function tenNganHangVietQr(string $bankId): string
{
    $map = [
        'VCB'  => 'Vietcombank (TMCP Ngoại thương VN)',
        'MB'   => 'MB Bank (Quân Đội)',
        'TCB'  => 'Techcombank (Kỹ thương VN)',
        'ICB'  => 'VietinBank (Công thương VN)',
        'BIDV' => 'BIDV (Đầu tư & Phát triển VN)',
        'ACB'  => 'ACB (Á Châu)',
        'VPB'  => 'VPBank (Việt Nam Thịnh Vượng)',
        'TPB'  => 'TPBank (Tiên Phong)',
    ];
    $key = strtoupper(trim($bankId));
    return $map[$key] ?? $key;
}

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

function tinhSoLuongConLai(
    PDO $pdo,
    int $xeId,
    int $tongSoLuong,
    bool $lockForUpdate = false,
    ?string $ngayNhan = null,
    ?string $ngayTra = null
): int {
    $sql = "
        SELECT COUNT(*) FROM don_thue
        WHERE xe_may_id = ?
          AND trang_thai IN ('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê')
    ";
    $params = [$xeId];

    if ($ngayNhan !== null && $ngayTra !== null) {
        $sql .= " AND ngay_nhan <= ? AND ngay_tra >= ?";
        $params[] = $ngayTra;
        $params[] = $ngayNhan;
    }

    if ($lockForUpdate) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dangGiu = (int) $stmt->fetchColumn();

    return max(0, $tongSoLuong - $dangGiu);
}

function formatVnd($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . "\u{00A0}₫";
}

function e(?string $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

tuDongHuyDonQuaHanVaDongBoKho($pdo);
