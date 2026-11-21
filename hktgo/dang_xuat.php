<?php
/**
 * dang_xuat.php
 * Xóa toàn bộ phiên đăng nhập PHP Session và đưa về trang chủ index.php
 */
require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
setFlash('info', 'Bạn đã đăng xuất khỏi hệ thống.');
header('Location: index.php');
exit;
