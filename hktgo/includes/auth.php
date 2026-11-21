<?php
/**
 * includes/auth.php
 * Quản lý phiên đăng nhập PHP Session ($_SESSION) và kiểm tra quyền (ADMIN / USER)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Lấy đường dẫn gốc tương đối để liên kết giữa thư mục gốc và thư mục /admin/ luôn đúng
 * dù đặt thư mục trong htdocs/hktgo hay chạy trực tiếp ở cổng local.
 */
function baseUrl(string $path = ''): string
{
    $isInAdmin = (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin');
    $prefix = $isInAdmin ? '../' : '';
    return $prefix . ltrim($path, '/');
}

/**
 * Trả về mảng thông tin người dùng đang đăng nhập trong Session (hoặc null nếu khách vãng lai).
 * Luôn đối chiếu lại vai_tro mới nhất từ CSDL nếu có $pdo để phản ánh tức thì khi Admin đổi quyền (Set Role).
 */
function currentUser(?PDO $pdo = null): ?array
{
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        return null;
    }

    if ($pdo !== null) {
        $stmt = $pdo->prepare("SELECT id, ho_ten, email, sdt, vai_tro FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user']['id']]);
        $fresh = $stmt->fetch();
        if (!$fresh) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user'] = $fresh;
    }

    return $_SESSION['user'];
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']['id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && strtoupper($_SESSION['user']['vai_tro'] ?? '') === 'ADMIN';
}

/**
 * Bắt buộc người dùng phải đăng nhập mới được truy cập trang (VD: dat_xe.php, thanh_toan.php, don_cua_toi.php).
 */
function requireLogin(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) {
        setFlash('warning', 'Vui lòng đăng nhập tài khoản để thực hiện chức năng này!');
        header('Location: ' . baseUrl('dang_nhap.php'));
        exit;
    }
    return $user;
}

/**
 * Bắt buộc tài khoản có vai trò ADMIN mới được vào khu vực /admin/*.php.
 */
function requireAdmin(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) {
        setFlash('warning', 'Vui lòng đăng nhập bằng tài khoản Quản trị viên (ADMIN)!');
        header('Location: ' . baseUrl('dang_nhap.php'));
        exit;
    }
    if (strtoupper($user['vai_tro'] ?? '') !== 'ADMIN') {
        setFlash('danger', 'Truy cập bị từ chối: Khu vực Quản trị chỉ dành cho tài khoản ADMIN!');
        header('Location: ' . baseUrl('index.php'));
        exit;
    }
    return $user;
}

/**
 * Sinh và kiểm tra CSRF Token chống giả mạo biểu mẫu POST.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Lưu và hiển thị thông báo Flash Message qua Session.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/**
 * Chuẩn hóa số điện thoại Việt Nam về 10 chữ số bắt đầu bằng 0.
 */
function normalizePhone(string $raw): string
{
    $d = preg_replace('/[\s.\-]/', '', trim($raw));
    if (str_starts_with($d, '+84')) {
        $d = '0' . substr($d, 3);
    } elseif (str_starts_with($d, '84') && strlen($d) >= 11) {
        $d = '0' . substr($d, 2);
    }
    return $d;
}
