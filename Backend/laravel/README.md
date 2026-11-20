# Backend Website Thuê Xe (Laravel 10 Framework)

Dự án backend đã được chuyển đổi toàn diện từ **Java Spring Boot** sang **PHP Laravel 10 Framework**, tương thích 100% với giao diện Frontend hiện có (`Frontend/ManagerVehicle`).

---

## 1. Cấu Trúc Đã Chuyển Đổi

| Chức năng | Java Spring Boot cũ | PHP Laravel 10 mới |
| :--- | :--- | :--- |
| **Bảng & Model** | `User.java`, `Motorbike.java`, `DanhMucXe.java`, `DonThue.java` | `app/Models/` (`User.php`, `Motorbike.php`, `DanhMucXe.php`, `DonThue.php`, `ChatMessage.php`) |
| **Migrations** | Hibernate auto-update (`ddl-auto=update`) | `database/migrations/` (4 migrations chuẩn Laravel) |
| **SQL Dump** | Không có | `database/sql/db_vehicle_mysql.sql` & `db_vehicle_postgres.sql` |
| **Seed Data** | `XeDemoDataLoader.java`, `DanhMucXeDataLoader.java` | `database/seeders/` (`UserSeeder`, `DanhMucXeSeeder`, `XeDemoSeeder`) |
| **Xác thực Auth** | `AuthController.java`, `AuthService.java` | `AuthController.php`, `UserController.php` (hỗ trợ Email + SĐT) |
| **Quản lý Xe** | `MotorbikeController.java` | `MotorbikeController.php` (CRUD đầy đủ, tương thích mọi alias endpoint) |
| **Danh mục xe** | `DanhMucXeController.java` | `DanhMucXeController.php` (CRUD + đồng bộ loại xe tự động) |
| **Đơn đặt xe** | `DonThueController.java`, `DonThueService.java` | `DonThueController.php`, `DonThueStatusService.php` |
| **Hủy đơn quá hạn** | `@Scheduled(fixedDelay = 60000)` | `php artisan orders:cancel-expired` + tự động dọn dẹp khi gọi API |
| **Chat hỗ trợ** | `ChatController.java` (lưu RAM) | `ChatController.php` (lưu DB bền vững qua `chat_messages`) |

---

## 2. Hướng Dẫn Cấu Hình Cơ Sở Dữ Liệu (.env)

Mở file `.env` tại thư mục `Backend/laravel`:

### Lựa chọn A: Dùng MySQL (Mặc định XAMPP / Laragon)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_vehicle
DB_USERNAME=root
DB_PASSWORD=
```

### Lựa chọn B: Dùng PostgreSQL (Khớp với cấu hình Java cũ)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_vehicle
DB_USERNAME=postgres
DB_PASSWORD=trunghieu
```

---

## 3. Khởi Tạo Bảng & Dữ Liệu Mẫu

### Cách 1: Dùng lệnh Artisan (Khuyên dùng)
Tạo database `db_vehicle` trong phpMyAdmin hoặc pgAdmin, sau đó chạy:
```bash
cd "d:\Code\website thue xe\Backend\laravel"
php artisan migrate --seed
```

### Cách 2: Import file SQL trực tiếp
Nếu không muốn chạy lệnh migration, có sẵn file SQL để import trong 1 click:
- Với MySQL: import file `database/sql/db_vehicle_mysql.sql`
- Với PostgreSQL: import file `database/sql/db_vehicle_postgres.sql`

---

## 4. Chạy Server Backend

Chạy server tại cổng **8081** để kết nối hoàn hảo với toàn bộ Frontend mà không cần sửa cấu hình:
```bash
cd "d:\Code\website thue xe\Backend\laravel"
php artisan serve --port=8081
```

---

## 5. Tài Khoản Mặc Định
- **Admin**:
  - Tài khoản: `admin@example.com` (hoặc SĐT: `0901234567`)
  - Mật khẩu: `123456`
  - Quyền: `ADMIN`
- **Khách hàng**:
  - Tài khoản: `khach@example.com` (hoặc SĐT: `0912345678`)
  - Mật khẩu: `123456`
  - Quyền: `USER`
