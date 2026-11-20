-- =======================================================
-- Database SQL Schema & Seed Data for PostgreSQL
-- Cơ sở dữ liệu: db_vehicle (Hệ thống thuê xe HKTGO)
-- =======================================================

-- 1. Bảng users (Người dùng & Phân quyền RBAC 4 cấp)
CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL UNIQUE,
  sdt VARCHAR(20) NULL UNIQUE,
  role VARCHAR(30) NOT NULL DEFAULT 'USER',
  password VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng danh_muc_xe (Tạo trước xe_may để nối khóa ngoại)
CREATE TABLE IF NOT EXISTS danh_muc_xe (
  id BIGSERIAL PRIMARY KEY,
  ten VARCHAR(100) NOT NULL UNIQUE,
  mo_ta VARCHAR(255) NULL,
  thu_tu INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng xe_may (Kho xe máy & Quản lý tồn kho theo số lượng)
CREATE TABLE IF NOT EXISTS xe_may (
  id BIGSERIAL PRIMARY KEY,
  danh_muc_xe_id BIGINT NULL REFERENCES danh_muc_xe(id) ON DELETE SET NULL,
  ten_xe VARCHAR(255) NOT NULL,
  bien_so VARCHAR(255) NOT NULL UNIQUE,
  loai_xe VARCHAR(255) NOT NULL,
  gia_thue NUMERIC(12,2) NOT NULL DEFAULT 0.00,
  so_luong INT NOT NULL DEFAULT 5,
  trang_thai VARCHAR(255) NOT NULL DEFAULT 'Đang rảnh',
  hang_xe VARCHAR(100) NOT NULL DEFAULT 'Honda',
  hinh_anh TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_xe_may_danh_muc_id ON xe_may (danh_muc_xe_id);

-- 4. Bảng don_thue (Đơn thuê xe & Thanh toán QR giữ chỗ 15 phút)
CREATE TABLE IF NOT EXISTS don_thue (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  xe_id BIGINT NULL,
  xe_may_id BIGINT NULL REFERENCES xe_may(id) ON DELETE SET NULL,
  ten_khach_hang VARCHAR(255) NULL,
  so_dien_thoai VARCHAR(30) NULL,
  ten_xe VARCHAR(255) NULL,
  bien_so VARCHAR(50) NULL,
  tong_tien NUMERIC(14,2) NULL,
  ngay_nhan VARCHAR(50) NULL,
  ngay_tra VARCHAR(50) NULL,
  gio_nhan VARCHAR(20) NULL,
  gio_tra VARCHAR(20) NULL,
  diem_nhan VARCHAR(255) NULL,
  diem_tra VARCHAR(255) NULL,
  trang_thai VARCHAR(50) NOT NULL DEFAULT 'Chờ thanh toán',
  email_khach VARCHAR(255) NULL,
  han_thanh_toan TIMESTAMP NULL,
  thoi_diem_thanh_toan TIMESTAMP NULL,
  kenh_thanh_toan VARCHAR(50) NULL,
  ma_nhom_don BIGINT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_don_thue_user_id ON don_thue (user_id);
CREATE INDEX IF NOT EXISTS idx_don_thue_xe_id ON don_thue (xe_id);
CREATE INDEX IF NOT EXISTS idx_don_thue_xe_may_id ON don_thue (xe_may_id);
CREATE INDEX IF NOT EXISTS idx_don_thue_bien_so ON don_thue (bien_so);
CREATE INDEX IF NOT EXISTS idx_don_thue_sdt ON don_thue (so_dien_thoai);
CREATE INDEX IF NOT EXISTS idx_don_thue_trang_thai ON don_thue (trang_thai);
CREATE INDEX IF NOT EXISTS idx_don_thue_email ON don_thue (email_khach);
CREATE INDEX IF NOT EXISTS idx_don_thue_han_tt ON don_thue (han_thanh_toan);
CREATE INDEX IF NOT EXISTS idx_don_thue_ma_nhom ON don_thue (ma_nhom_don);

-- 5. Bảng chat_messages (Trợ lí AI HKTGO & Chat trực tiếp nhân viên)
CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGSERIAL PRIMARY KEY,
  noi_dung TEXT NOT NULL,
  nguoi_gui VARCHAR(100) NOT NULL DEFAULT 'Khách hàng',
  from_role VARCHAR(20) NOT NULL DEFAULT 'USER',
  kenh VARCHAR(30) NOT NULL DEFAULT 'DIRECT',
  to_user VARCHAR(100) NULL,
  read_by_admin BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_chat_nguoi_gui ON chat_messages (nguoi_gui);
CREATE INDEX IF NOT EXISTS idx_chat_from_role ON chat_messages (from_role);
CREATE INDEX IF NOT EXISTS idx_chat_to_user ON chat_messages (to_user);

-- =======================================================
-- Dữ liệu mẫu (Seed Data)
-- Mật khẩu mặc định cho cả 4 tài khoản mẫu: 123456
-- =======================================================

INSERT INTO users (name, email, sdt, role, password, created_at, updated_at) VALUES
('Quản trị viên', 'admin@example.com', '0901234567', 'ADMIN', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
('Quản lý Cửa hàng', 'quanly@example.com', '0902222333', 'MANAGER', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
('Nhân viên Tư vấn', 'nhanvien@example.com', '0903333444', 'STAFF', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW()),
('Khách hàng Demo', 'khach@example.com', '0912345678', 'USER', '$2y$10$J0sASxUnmuSif4hbE2UgO.l5TfN05tsQqxWo15YirkUtfFKV1HlWy', NOW(), NOW())
ON CONFLICT (email) DO UPDATE SET
  name = EXCLUDED.name,
  role = EXCLUDED.role,
  password = EXCLUDED.password;

INSERT INTO danh_muc_xe (id, ten, mo_ta, thu_tu, created_at, updated_at) VALUES
(1, 'Xe tay ga', 'Xe scooter, không cần sang số', 1, NOW(), NOW()),
(2, 'Xe số', 'Xe số phổ thông tiết kiệm nhiên liệu', 2, NOW(), NOW()),
(3, 'Xe côn tay', 'Xe côn tay / xe thể thao đường dài', 3, NOW(), NOW()),
(4, 'Xe điện', 'Xe máy điện thân thiện môi trường', 4, NOW(), NOW())
ON CONFLICT (ten) DO UPDATE SET
  mo_ta = EXCLUDED.mo_ta,
  thu_tu = EXCLUDED.thu_tu;

SELECT setval(pg_get_serial_sequence('danh_muc_xe', 'id'), COALESCE((SELECT MAX(id) FROM danh_muc_xe), 1));

INSERT INTO xe_may (danh_muc_xe_id, ten_xe, bien_so, loai_xe, gia_thue, so_luong, trang_thai, hang_xe, hinh_anh, created_at, updated_at) VALUES
(2, 'Honda Future 125 FI', '59E-55555', 'Xe số', 150000.00, 5, 'Đang rảnh', 'Honda', 'images/car-5.jpg', NOW(), NOW()),
(2, 'Honda Wave', '59A-11111', 'Xe số', 200000.00, 5, 'Đang rảnh', 'Honda', 'images/car-1.jpg', NOW(), NOW()),
(1, 'Honda Vision 2024', '59B-22222', 'Xe tay ga', 150000.00, 5, 'Đang rảnh', 'Honda', 'images/car-2.jpg', NOW(), NOW()),
(1, 'Honda Vario 160', '59C-33333', 'Xe tay ga', 100000.00, 5, 'Đang rảnh', 'Honda', 'images/car-3.jpg', NOW(), NOW()),
(3, 'Yamaha Exciter 155 VVA', '59D-44444', 'Xe côn tay', 200000.00, 5, 'Đang rảnh', 'Yamaha', 'images/car-4.jpg', NOW(), NOW()),
(1, 'Honda SH 150i', '59F-66666', 'Xe tay ga', 350000.00, 5, 'Đang rảnh', 'Honda', 'images/car-6.jpg', NOW(), NOW())
ON CONFLICT (bien_so) DO UPDATE SET
  danh_muc_xe_id = EXCLUDED.danh_muc_xe_id,
  so_luong = EXCLUDED.so_luong,
  hang_xe = EXCLUDED.hang_xe,
  gia_thue = EXCLUDED.gia_thue;
