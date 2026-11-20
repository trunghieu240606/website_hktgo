-- =======================================================
-- Database SQL Schema & Seed Data for MySQL / MariaDB
-- Cơ sở dữ liệu: db_vehicle (Hệ thống thuê xe HKTGO)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `db_vehicle` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_vehicle`;

-- 1. Bảng users (Người dùng & Phân quyền RBAC 4 cấp)
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL UNIQUE,
  `sdt` VARCHAR(20) NULL UNIQUE,
  `role` VARCHAR(30) NOT NULL DEFAULT 'USER',
  `password` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng danh_muc_xe (Phải tạo trước bảng xe_may để nối khóa ngoại)
CREATE TABLE IF NOT EXISTS `danh_muc_xe` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ten` VARCHAR(100) NOT NULL UNIQUE,
  `mo_ta` VARCHAR(255) NULL,
  `thu_tu` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng xe_may (Kho xe máy & Quản lý tồn kho theo số lượng)
CREATE TABLE IF NOT EXISTS `xe_may` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `danh_muc_xe_id` BIGINT UNSIGNED NULL,
  `ten_xe` VARCHAR(255) NOT NULL,
  `bien_so` VARCHAR(255) NOT NULL UNIQUE,
  `loai_xe` VARCHAR(255) NOT NULL,
  `gia_thue` DOUBLE(12,2) NOT NULL DEFAULT 0.00,
  `so_luong` INT NOT NULL DEFAULT 5,
  `trang_thai` VARCHAR(255) NOT NULL DEFAULT 'Đang rảnh',
  `hang_xe` VARCHAR(100) NOT NULL DEFAULT 'Honda',
  `hinh_anh` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `xe_may_danh_muc_xe_id_foreign`
    FOREIGN KEY (`danh_muc_xe_id`) REFERENCES `danh_muc_xe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng don_thue (Đơn thuê xe & Thanh toán QR giữ chỗ 15 phút)
CREATE TABLE IF NOT EXISTS `don_thue` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NULL,
  `xe_id` BIGINT UNSIGNED NULL,
  `xe_may_id` BIGINT UNSIGNED NULL,
  `ten_khach_hang` VARCHAR(255) NULL,
  `so_dien_thoai` VARCHAR(30) NULL,
  `ten_xe` VARCHAR(255) NULL,
  `bien_so` VARCHAR(50) NULL,
  `tong_tien` DOUBLE(14,2) NULL,
  `ngay_nhan` VARCHAR(50) NULL,
  `ngay_tra` VARCHAR(50) NULL,
  `gio_nhan` VARCHAR(20) NULL,
  `gio_tra` VARCHAR(20) NULL,
  `diem_nhan` VARCHAR(255) NULL,
  `diem_tra` VARCHAR(255) NULL,
  `trang_thai` VARCHAR(50) NOT NULL DEFAULT 'Chờ thanh toán',
  `email_khach` VARCHAR(255) NULL,
  `han_thanh_toan` DATETIME NULL,
  `thoi_diem_thanh_toan` DATETIME NULL,
  `kenh_thanh_toan` VARCHAR(50) NULL,
  `ma_nhom_don` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `don_thue_xe_id_index` (`xe_id`),
  INDEX `don_thue_so_dien_thoai_index` (`so_dien_thoai`),
  INDEX `don_thue_bien_so_index` (`bien_so`),
  INDEX `don_thue_trang_thai_index` (`trang_thai`),
  INDEX `don_thue_email_khach_index` (`email_khach`),
  INDEX `don_thue_han_thanh_toan_index` (`han_thanh_toan`),
  INDEX `don_thue_ma_nhom_don_index` (`ma_nhom_don`),
  CONSTRAINT `don_thue_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `don_thue_xe_may_id_foreign`
    FOREIGN KEY (`xe_may_id`) REFERENCES `xe_may` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng chat_messages (Trợ lí AI HKTGO & Chat trực tiếp nhân viên)
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `noi_dung` TEXT NOT NULL,
  `nguoi_gui` VARCHAR(100) NOT NULL DEFAULT 'Khách hàng',
  `from_role` VARCHAR(20) NOT NULL DEFAULT 'USER',
  `kenh` VARCHAR(30) NOT NULL DEFAULT 'DIRECT',
  `to_user` VARCHAR(100) NULL,
  `read_by_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `chat_messages_nguoi_gui_index` (`nguoi_gui`),
  INDEX `chat_messages_from_role_index` (`from_role`),
  INDEX `chat_messages_to_user_index` (`to_user`),
  INDEX `chat_messages_read_by_admin_index` (`read_by_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Các bảng hạ tầng chuẩn của Laravel Framework
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) PRIMARY KEY,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(255) NOT NULL UNIQUE,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- Dữ liệu mẫu (Seed Data)
-- Mật khẩu mặc định cho cả 4 tài khoản mẫu: 123456
-- =======================================================

INSERT INTO `users` (`id`, `name`, `email`, `sdt`, `role`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Quản trị viên', 'admin@example.com', '0901234567', 'ADMIN', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
(2, 'Quản lý Cửa hàng', 'quanly@example.com', '0902222333', 'MANAGER', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
(3, 'Nhân viên Tư vấn', 'nhanvien@example.com', '0903333444', 'STAFF', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
(4, 'Khách hàng Demo', 'khach@example.com', '0912345678', 'USER', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `password` = VALUES(`password`);

INSERT INTO `danh_muc_xe` (`id`, `ten`, `mo_ta`, `thu_tu`, `created_at`, `updated_at`) VALUES
(1, 'Xe tay ga', 'Xe scooter, không cần sang số', 1, NOW(), NOW()),
(2, 'Xe số', 'Xe số phổ thông tiết kiệm nhiên liệu', 2, NOW(), NOW()),
(3, 'Xe côn tay', 'Xe côn tay / xe thể thao đường dài', 3, NOW(), NOW()),
(4, 'Xe điện', 'Xe máy điện thân thiện môi trường', 4, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `ten` = VALUES(`ten`),
  `mo_ta` = VALUES(`mo_ta`),
  `thu_tu` = VALUES(`thu_tu`);

INSERT INTO `xe_may` (`id`, `danh_muc_xe_id`, `ten_xe`, `bien_so`, `loai_xe`, `gia_thue`, `so_luong`, `trang_thai`, `hang_xe`, `hinh_anh`, `created_at`, `updated_at`) VALUES
(1, 2, 'Honda Future 125 FI', '59E-55555', 'Xe số', 150000.00, 5, 'Đang rảnh', 'Honda', 'images/car-5.jpg', NOW(), NOW()),
(2, 2, 'Honda Wave', '59A-11111', 'Xe số', 200000.00, 5, 'Đang rảnh', 'Honda', 'images/car-1.jpg', NOW(), NOW()),
(3, 1, 'Honda Vision 2024', '59B-22222', 'Xe tay ga', 150000.00, 5, 'Đang rảnh', 'Honda', 'images/car-2.jpg', NOW(), NOW()),
(4, 1, 'Honda Vario 160', '59C-33333', 'Xe tay ga', 100000.00, 5, 'Đang rảnh', 'Honda', 'images/car-3.jpg', NOW(), NOW()),
(5, 3, 'Yamaha Exciter 155 VVA', '59D-44444', 'Xe côn tay', 200000.00, 5, 'Đang rảnh', 'Yamaha', 'images/car-4.jpg', NOW(), NOW()),
(6, 1, 'Honda SH 150i', '59F-66666', 'Xe tay ga', 350000.00, 5, 'Đang rảnh', 'Honda', 'images/car-6.jpg', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `danh_muc_xe_id` = VALUES(`danh_muc_xe_id`),
  `so_luong` = VALUES(`so_luong`),
  `hang_xe` = VALUES(`hang_xe`),
  `gia_thue` = VALUES(`gia_thue`);
