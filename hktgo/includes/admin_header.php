<?php
/**
 * includes/admin_header.php
 * Khung giao diện Quản trị dùng chung (Chỉ dành cho vai trò ADMIN)
 */
$adminUser = requireAdmin($pdo);
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$flash = getFlash();

$navItems = [
    ['file' => 'index.php',      'icon' => 'fa-gauge text-primary',          'label' => 'Tổng quan'],
    ['file' => 'xe.php',         'icon' => 'fa-motorcycle text-primary',     'label' => 'Quản lý Kho xe'],
    ['file' => 'danh_muc.php',   'icon' => 'fa-tags text-info',              'label' => 'Danh mục loại xe'],
    ['file' => 'don_thue.php',   'icon' => 'fa-clipboard-list text-warning', 'label' => 'Quản lý Đơn thuê'],
    ['file' => 'khach_hang.php', 'icon' => 'fa-users-gear text-success',     'label' => 'Tài khoản & Set Role'],
    ['file' => 'thong_ke.php',   'icon' => 'fa-chart-column text-danger',    'label' => 'Thống kê Doanh thu'],
];
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Quản trị hệ thống HKTGO') ?></title>
    <link rel="icon" type="image/svg+xml" href="./assets/favicon-B1-NXkMJ.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/main-Kuf3cpUe.css">
    <style>
        .vehicle-thumb {
            width: 58px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .badge-status-ranh { background-color: #d1e7dd; color: #0f5132; }
        .badge-status-thue { background-color: #cfe2ff; color: #084298; }
        .badge-status-cho  { background-color: #fff3cd; color: #664d03; }
        .badge-status-huy  { background-color: #f8d7da; color: #842029; }
        .kpi-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 21px;
        }
        select.form-select {
            padding-top: 6px;
            padding-bottom: 6px;
            line-height: 1.4;
        }
    </style>
</head>
<body class="admin-layout">
<div class="admin-app">
    <div class="admin-wrapper" id="admin-wrapper">
        <header class="admin-header">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3">
                <div class="container-fluid p-0">
                    <a class="navbar-brand d-flex align-items-center text-decoration-none me-3" href="index.php">
                        <i class="fa-solid fa-motorcycle text-primary fs-3 me-2"></i>
                        <span class="h4 mb-0 fw-bold text-dark">HKT<span class="text-primary">GO</span></span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-2 fs-7">
                            <i class="fa-solid fa-shield-halved me-1"></i>ADMIN
                        </span>
                    </a>

                    <ul class="navbar-nav me-auto d-none d-md-flex flex-row gap-2">
                        <li class="nav-item"><a class="nav-link fw-semibold" href="xe.php">Kho xe</a></li>
                        <li class="nav-item"><a class="nav-link fw-semibold" href="don_thue.php">Đơn thuê</a></li>
                        <li class="nav-item"><a class="nav-link fw-semibold" href="thong_ke.php">Doanh thu</a></li>
                        <li class="nav-item">
                            <a class="nav-link text-primary fw-semibold" href="../index.php">
                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Trang khách
                            </a>
                        </li>
                    </ul>

                    <div class="navbar-nav flex-row align-items-center gap-3">
                        <span class="small text-muted d-none d-sm-inline">
                            <i class="fa-solid fa-user-shield text-primary me-1"></i>
                            <strong><?= e($adminUser['ho_ten']) ?></strong> (<?= e($adminUser['email']) ?>)
                        </span>
                        <a href="../dang_xuat.php" class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </nav>
        </header>

        <aside class="admin-sidebar" style="width: 250px; border-right: 1px solid #e9ecef; background: #fff;">
            <div class="sidebar-content p-3">
                <div class="text-uppercase text-muted fw-bold fs-7 mb-2 px-2">Quản trị hệ thống</div>
                <nav class="sidebar-nav">
                    <ul class="nav flex-column gap-1">
                        <?php foreach ($navItems as $item):
                            $active = ($currentAdminPage === $item['file']);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link rounded px-3 py-2 <?= $active ? 'active bg-primary text-white fw-semibold' : 'text-dark' ?>"
                               href="<?= e($item['file']) ?>">
                                <i class="fa-solid <?= $active ? str_replace(['text-primary','text-info','text-warning','text-success','text-danger'], 'text-white', $item['icon']) : $item['icon'] ?> me-2"></i>
                                <?= e($item['label']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>

                        <li class="nav-item mt-3 pt-3 border-top">
                            <div class="text-uppercase text-muted fw-bold fs-7 mb-2 px-2">Lối tắt</div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-secondary rounded px-3 py-2" href="../index.php">
                                <i class="fa-solid fa-house me-2"></i>Về trang chủ Khách
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger rounded px-3 py-2" href="../dang_xuat.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Đăng xuất
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <main id="main-content" class="admin-main p-4" style="background-color: #f8f9fa; min-height: calc(100vh - 60px);">
            <div class="container-fluid">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
