<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DanhMucXeController;
use App\Http\Controllers\DonThueController;
use App\Http\Controllers\MotorbikeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth & Users
Route::post('/login', [AuthController::class, 'login']);
Route::post('/users/dang-ky', [UserController::class, 'dangKyTaiKhoan']);
Route::get('/users', [UserController::class, 'layDanhSachKhachHang']);
Route::post('/users/set-role', [UserController::class, 'setRole']);
Route::post('/users/{id}/set-role', [UserController::class, 'setRole']);
Route::put('/users/{id}/set-role', [UserController::class, 'setRole']);
Route::get('/khach-hang/danh-sach', [UserController::class, 'layDanhSachKhachHang']);
Route::get('/khach-hang', [UserController::class, 'layDanhSachKhachHang']);

// Motorbike (Xe máy)
Route::prefix('xe')->group(function () {
    Route::get('/danh-sach', [MotorbikeController::class, 'layDanhSachXe']);
    Route::post('/upload-anh', [MotorbikeController::class, 'uploadAnh']);
    Route::delete('/xoa-tat-ca', [MotorbikeController::class, 'xoaTatCa']);
    Route::post('/xoa-tat-ca', [MotorbikeController::class, 'xoaTatCa']);
    Route::post('/them', [MotorbikeController::class, 'themXeMoi']);
    Route::match(['put', 'post'], '/sua/{id}', [MotorbikeController::class, 'capNhatXe']);
    Route::match(['put', 'post'], '/cap-nhat/{id}', [MotorbikeController::class, 'capNhatXe']);
    Route::match(['put', 'post'], '/chinh-sua/{id}', [MotorbikeController::class, 'capNhatXe']);
    Route::post('/cap-nhat', [MotorbikeController::class, 'capNhatXe']);
    Route::post('/sua', [MotorbikeController::class, 'capNhatXe']);
    Route::delete('/xoa/{id}', [MotorbikeController::class, 'xoaXe']);
    Route::delete('/delete/{id}', [MotorbikeController::class, 'xoaXe']);
    Route::delete('/{id}', [MotorbikeController::class, 'xoaXe']);
    Route::post('/xoa/{id}', [MotorbikeController::class, 'xoaXe']);
    Route::post('/xoa', [MotorbikeController::class, 'xoaXe']);
});

// Category (Danh mục xe)
Route::prefix('danh-muc-xe')->group(function () {
    Route::get('/danh-sach', [DanhMucXeController::class, 'layDanhSach']);
    Route::post('/them', [DanhMucXeController::class, 'them']);
    Route::put('/sua/{id}', [DanhMucXeController::class, 'sua']);
    Route::delete('/xoa/{id}', [DanhMucXeController::class, 'xoa']);
});

// Booking (Đơn thuê)
Route::prefix('don-thue')->group(function () {
    Route::get('/danh-sach', [DonThueController::class, 'layDanhSachDon']);
    Route::post('/dat-xe', [DonThueController::class, 'datXeMoi']);
    Route::put('/xac-nhan-thanh-toan/{id}', [DonThueController::class, 'xacNhanThanhToan']);
    Route::match(['put', 'post'], '/cap-nhat-trang-thai/{id}', [DonThueController::class, 'capNhatTrangThai']);
    Route::get('/cua-toi', [DonThueController::class, 'donCuaToi']);
    Route::put('/khach-thao-tac/{id}', [DonThueController::class, 'khachThaoTac']);
    Route::get('/{id}/xem', [DonThueController::class, 'xemDonNhom']);
    Route::post('/{id}/them-xe', [DonThueController::class, 'themXeVaoDon']);
    Route::put('/{id}/cap-nhat', [DonThueController::class, 'capNhatDongDon']);
    Route::delete('/{id}/dong', [DonThueController::class, 'xoaDongDon']);
});

// Chat
Route::prefix('chat')->group(function () {
    Route::post('/gui', [ChatController::class, 'guiTinNhan']);
    Route::get('/danh-sach', [ChatController::class, 'layDanhSach']);
    Route::get('/chua-doc/count', [ChatController::class, 'demChuaDoc']);
    Route::put('/danh-dau-da-doc', [ChatController::class, 'danhDauDaDoc']);
    Route::get('/che-do', [ChatController::class, 'layCheDo']);
    Route::post('/doi-che-do', [ChatController::class, 'doiCheDo']);
});
