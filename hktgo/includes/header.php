<?php
$currentUser = currentUser($pdo ?? null);
$currentScript = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($pageTitle ?? 'HKTGO - Hệ thống cho thuê xe máy trực tuyến') ?></title>

    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('css/open-iconic-bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/animate.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/ionicons.min.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/flaticon.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/icomoon.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/style.css') ?>">
    <link rel="stylesheet" href="<?= baseUrl('css/home-professional.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            background-color: #f5f7fb;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hkt-main-content {
            flex: 1 0 auto;
        }
        .ftco-footer {
            flex-shrink: 0;
            margin-top: auto !important;
        }
        .hkt-topbar {
            background: rgba(11, 22, 44, 0.96) !important;
            box-shadow: 0 6px 24px rgba(10, 22, 46, 0.22);
            padding: 12px 0;
        }
        .hkt-topbar .navbar-brand {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #fff !important;
        }
        .hkt-topbar .navbar-brand span {
            color: #01d28e !important;
        }
        .hkt-topbar .nav-link {
            color: rgba(255, 255, 255, 0.88) !important;
            font-weight: 600;
            font-size: 14.5px;
            padding: 8px 16px !important;
        }
        .hkt-topbar .nav-item.active .nav-link,
        .hkt-topbar .nav-link:hover {
            color: #01d28e !important;
        }
        .hkt-hero-banner {
            background: linear-gradient(125deg, rgba(10, 24, 50, 0.84), rgba(15, 52, 96, 0.78)),
                        url('<?= baseUrl('images/bg_1.jpg') ?>') center center / cover no-repeat;
            padding: 68px 0 56px;
            color: #fff;
        }
        .hkt-sub-banner {
            background: linear-gradient(125deg, rgba(10, 24, 50, 0.90), rgba(20, 62, 110, 0.86)),
                        url('<?= baseUrl('images/bg_2.jpg') ?>') center center / cover no-repeat;
            padding: 40px 0 34px;
            color: #fff;
        }
        .vehicle-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e6ecf5;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .vehicle-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
        }
        .vehicle-card-img {
            height: 205px;
            width: 100%;
            object-fit: cover;
            background: #eef2f7;
        }
        .vehicle-card-body {
            padding: 18px 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .price-tag {
            font-size: 19px;
            font-weight: 700;
            color: #1089ff;
        }
        .badge-stock-ok,
        .badge-status-ranh {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1.3;
            white-space: nowrap;
        }
        .badge-stock-out,
        .badge-status-het,
        .badge-status-huy {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1.3;
            white-space: nowrap;
        }
        .badge-status-cho {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1.3;
            white-space: nowrap;
        }
        .badge-status-thue {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1.3;
            white-space: nowrap;
        }
        .price-tag,
        .price-nowrap {
            white-space: nowrap;
        }
        .content-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e6ecf5;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            padding: 26px;
        }
        /* Ghi đè quy tắc .table { min-width: 1000px !important; padding: 30px 0 } của template Carbook */
        .table {
            min-width: 0 !important;
            width: 100% !important;
        }
        .table th,
        .table td,
        .table tbody tr td {
            padding: 0.75rem 0.85rem !important;
            vertical-align: middle !important;
        }
        .table-sm th,
        .table-sm td,
        .table.table-sm tbody tr td {
            padding: 0.5rem 0.75rem !important;
        }
        select.form-control {
            height: 44px !important;
            padding: 8px 34px 8px 12px !important;
            line-height: 1.4 !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23334155' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 11px center !important;
            background-size: 14px 10px !important;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }
        @media (max-width: 991.98px) {
            #hktNav .navbar-nav {
                padding-top: 10px;
                gap: 6px;
            }
            .content-card {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark hkt-topbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= baseUrl('index.php') ?>">
            <i class="fa-solid fa-motorcycle mr-2" style="color:#01d28e;"></i>HKT<span>GO</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#hktNav">
            <span class="oi oi-menu"></span> Menu
        </button>

        <div class="collapse navbar-collapse" id="hktNav">
            <ul class="navbar-nav ml-auto align-items-lg-center">
                <?php if ($currentUser): ?>
                    <li class="nav-item <?= $currentScript === 'don_cua_toi.php' ? 'active' : '' ?>">
                        <a href="<?= baseUrl('don_cua_toi.php') ?>" class="nav-link" title="Đơn thuê của tôi">
                            <i class="fa-solid fa-cart-shopping" style="font-size: 18px;"></i>
                        </a>
                    </li>
                    <?php if (strtoupper($currentUser['vai_tro']) === 'ADMIN'): ?>
                        <li class="nav-item ml-lg-2">
                            <a href="<?= baseUrl('admin/index.php') ?>" class="btn btn-sm btn-warning font-weight-bold px-3 py-2">
                                <i class="fa-solid fa-shield-halved mr-1"></i>Vào Quản trị
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown ml-lg-2">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <i class="fa-solid fa-circle-user mr-1" style="color:#01d28e;"></i><?= e($currentUser['ho_ten']) ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
                            <div class="dropdown-header">
                                <strong><?= e($currentUser['email']) ?></strong><br>
                                <span class="badge badge-info mt-1"><?= e($currentUser['vai_tro']) ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?= baseUrl('don_cua_toi.php') ?>">
                                <i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i>Lịch sử đơn thuê
                            </a>
                            <a class="dropdown-item text-danger font-weight-bold" href="<?= baseUrl('dang_xuat.php') ?>">
                                <i class="fa-solid fa-right-from-bracket mr-2"></i>Đăng xuất
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item <?= $currentScript === 'dang_nhap.php' ? 'active' : '' ?>">
                        <a href="<?= baseUrl('dang_nhap.php') ?>" class="nav-link">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i>Đăng nhập
                        </a>
                    </li>
                    <li class="nav-item ml-lg-2">
                        <a href="<?= baseUrl('dang_ky.php') ?>" class="btn btn-sm btn-success font-weight-bold px-3 py-2" style="background:#01d28e;border-color:#01d28e;">
                            <i class="fa-solid fa-user-plus mr-1"></i>Đăng ký
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="container mt-3">
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm mb-0" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
</div>
<?php endif; ?>

<main class="hkt-main-content">
