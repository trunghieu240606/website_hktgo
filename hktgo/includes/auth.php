<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function baseUrl(string $path = ''): string
{
    $isInAdmin = (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin');
    $prefix = $isInAdmin ? '../' : '';
    return $prefix . ltrim($path, '/');
}

function currentUser(?PDO $pdo = null): ?array
{
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        return null;
    }

    if ($pdo !== null) {
        $stmt = $pdo->prepare("SELECT id, ho_ten, email, sdt, vai_tro, trang_thai FROM users WHERE id = ? LIMIT 1");
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

function requireLogin(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_SERVER['REQUEST_URI'])) {
            $reqFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
            $query   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
            if ($reqFile !== '' && $reqFile !== 'dang_nhap.php' && $reqFile !== 'dang_ky.php') {
                $_SESSION['redirect_after_login'] = $reqFile . ($query ? '?' . $query : '');
            }
        }
        setFlash('warning', 'Vui lòng đăng nhập tài khoản để thực hiện chức năng này!');
        header('Location: ' . baseUrl('dang_nhap.php'));
        exit;
    }
    return $user;
}

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
