-- =====================================================================
-- CƠ SỞ DỮ LIỆU HỆ THỐNG CHO THUÊ XE MÁY TRỰC TUYẾN HKT GO (PHP & MySQL)
-- Tên CSDL: hktgo_db (4 bảng quan hệ: users, danh_muc_xe, xe_may, don_thue)
-- Tương thích Laragon / XAMPP / phpMyAdmin (MySQL 8.0 / MariaDB 10.4+)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `hktgo_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `hktgo_db`;

-- 1. Bảng users: Quản lý tài khoản và phân quyền 2 vai trò (ADMIN, USER)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ho_ten` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `sdt` VARCHAR(20) NOT NULL UNIQUE,
  `mat_khau` VARCHAR(255) NOT NULL,
  `vai_tro` ENUM('ADMIN', 'USER') NOT NULL DEFAULT 'USER',
  `ngay_tao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng danh_muc_xe: Phân loại dòng xe máy
CREATE TABLE IF NOT EXISTS `danh_muc_xe` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ten` VARCHAR(100) NOT NULL UNIQUE,
  `mo_ta` VARCHAR(255) NULL,
  `thu_tu` INT NOT NULL DEFAULT 0,
  `ngay_tao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng xe_may: Thông tin mẫu xe (ten_xe duy nhất) và tổng số lượng tồn kho (so_luong)
CREATE TABLE IF NOT EXISTS `xe_may` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `danh_muc_xe_id` INT UNSIGNED NULL,
  `ten_xe` VARCHAR(150) NOT NULL UNIQUE,
  `hang_xe` VARCHAR(100) NOT NULL DEFAULT 'Honda',
  `gia_thue` DECIMAL(12,0) NOT NULL DEFAULT 150000,
  `so_luong` INT NOT NULL DEFAULT 5,
  `trang_thai` VARCHAR(50) NOT NULL DEFAULT 'Đang rảnh',
  `hinh_anh` VARCHAR(255) NULL,
  `ngay_tao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_xe_danh_muc` (`danh_muc_xe_id`),
  CONSTRAINT `fk_xe_danh_muc`
    FOREIGN KEY (`danh_muc_xe_id`) REFERENCES `danh_muc_xe` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng don_thue: Đơn thuê xe, giữ chỗ 15 phút & vòng đời trạng thái
-- (Lưu cố định so_ngay và tong_tien để giữ nguyên mức giá tại thời điểm khách đặt)
CREATE TABLE IF NOT EXISTS `don_thue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `xe_may_id` INT UNSIGNED NOT NULL,
  `ten_khach_hang` VARCHAR(100) NOT NULL,
  `so_dien_thoai` VARCHAR(20) NOT NULL,
  `ngay_nhan` DATE NOT NULL,
  `ngay_tra` DATE NOT NULL,
  `diem_nhan` VARCHAR(255) NOT NULL,
  `diem_tra` VARCHAR(255) NOT NULL,
  `so_ngay` INT NOT NULL DEFAULT 1,
  `tong_tien` DECIMAL(14,0) NOT NULL DEFAULT 0,
  `trang_thai` ENUM('Chờ thanh toán', 'Chờ duyệt', 'Đang thuê', 'Hoàn thành', 'Đã hủy') NOT NULL DEFAULT 'Chờ thanh toán',
  `han_thanh_toan` DATETIME NULL,
  `thoi_diem_thanh_toan` DATETIME NULL,
  `ngay_tao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_don_user` (`user_id`),
  INDEX `idx_don_xe` (`xe_may_id`),
  INDEX `idx_don_trang_thai` (`trang_thai`),
  INDEX `idx_don_ngay_nhan` (`ngay_nhan`),
  INDEX `idx_don_ngay_tra` (`ngay_tra`),
  INDEX `idx_don_han_tt` (`han_thanh_toan`),
  CONSTRAINT `fk_don_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_don_xe_may`
    FOREIGN KEY (`xe_may_id`) REFERENCES `xe_may` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DỮ LIỆU MẪU KHỞI TẠO (Mật khẩu mặc định của các tài khoản mẫu: 123456)
-- =====================================================================

INSERT INTO `users` (`id`, `ho_ten`, `email`, `sdt`, `mat_khau`, `vai_tro`) VALUES
(1, 'Quản trị viên HKTGO', 'admin@example.com', '0901234567', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', 'ADMIN'),
(2, 'Nguyễn Văn Minh', 'khach@example.com', '0912345678', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', 'USER'),
(3, 'Trần Thị Lan Anh', 'lananh@gmail.com', '0987654321', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', 'USER')
ON DUPLICATE KEY UPDATE
  `ho_ten` = VALUES(`ho_ten`),
  `vai_tro` = VALUES(`vai_tro`);

INSERT INTO `danh_muc_xe` (`id`, `ten`, `mo_ta`, `thu_tu`) VALUES
(1, 'Xe tay ga', 'Xe tay ga tự động tiện lợi di chuyển nội đô', 1),
(2, 'Xe số', 'Xe số bền bỉ, tiết kiệm nhiên liệu', 2),
(3, 'Xe côn tay', 'Xe côn tay thể thao mạnh mẽ đi đường dài', 3),
(4, 'Xe điện', 'Xe máy điện êm ái, thân thiện môi trường', 4)
ON DUPLICATE KEY UPDATE
  `mo_ta` = VALUES(`mo_ta`),
  `thu_tu` = VALUES(`thu_tu`);

INSERT INTO `xe_may` (`id`, `danh_muc_xe_id`, `ten_xe`, `hang_xe`, `gia_thue`, `so_luong`, `trang_thai`, `hinh_anh`) VALUES
(1, 1, 'Honda Vision 2024', 'Honda', 150000, 5, 'Đang rảnh', 'images/car-2.jpg'),
(2, 2, 'Honda Future 125 FI', 'Honda', 140000, 5, 'Đang rảnh', 'images/car-5.jpg'),
(3, 2, 'Honda Wave Alpha', 'Honda', 120000, 6, 'Đang rảnh', 'images/car-1.jpg'),
(4, 1, 'Honda Vario 160', 'Honda', 180000, 4, 'Đang rảnh', 'images/car-3.jpg'),
(5, 3, 'Yamaha Exciter 155 VVA', 'Yamaha', 200000, 4, 'Đang rảnh', 'images/car-4.jpg'),
(6, 1, 'Honda SH 150i ABS', 'Honda', 350000, 3, 'Đang rảnh', 'images/car-7.jpg')
ON DUPLICATE KEY UPDATE
  `gia_thue` = VALUES(`gia_thue`),
  `so_luong` = VALUES(`so_luong`),
  `hinh_anh` = VALUES(`hinh_anh`);

INSERT INTO `don_thue` (`id`, `user_id`, `xe_may_id`, `ten_khach_hang`, `so_dien_thoai`, `ngay_nhan`, `ngay_tra`, `diem_nhan`, `diem_tra`, `so_ngay`, `tong_tien`, `trang_thai`, `han_thanh_toan`, `thoi_diem_thanh_toan`, `ngay_tao`) VALUES
(1, 2, 1, 'Nguyễn Văn Minh', '0912345678', '2026-10-12', '2026-10-14', 'Cơ sở HKTGO Cầu Giấy', 'Cơ sở HKTGO Cầu Giấy', 3, 450000, 'Hoàn thành', '2026-10-12 08:15:00', '2026-10-12 08:05:00', '2026-10-12 08:00:00'),
(2, 3, 6, 'Trần Thị Lan Anh', '0987654321', '2026-11-10', '2026-11-11', 'Cơ sở HKTGO Bắc Từ Liêm', 'Cơ sở HKTGO Bắc Từ Liêm', 2, 700000, 'Hoàn thành', '2026-11-10 09:15:00', '2026-11-10 09:04:00', '2026-11-10 09:00:00'),
(3, 2, 5, 'Nguyễn Văn Minh', '0912345678', '2026-11-20', '2026-11-22', 'Cơ sở HKTGO Cầu Giấy', 'Bến xe Mỹ Đình', 3, 600000, 'Đang thuê', '2026-11-20 08:45:00', '2026-11-20 08:33:00', '2026-11-20 08:30:00'),
(4, 3, 4, 'Trần Thị Lan Anh', '0987654321', '2026-11-21', '2026-11-22', 'Cơ sở HKTGO Bắc Từ Liêm', 'Cơ sở HKTGO Bắc Từ Liêm', 2, 360000, 'Chờ duyệt', '2026-11-21 10:15:00', '2026-11-21 10:03:00', '2026-11-21 10:00:00')
ON DUPLICATE KEY UPDATE
  `trang_thai` = VALUES(`trang_thai`);
