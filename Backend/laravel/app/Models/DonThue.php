<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonThue extends Model
{
    use HasFactory;

    protected $table = 'don_thue';

    protected $fillable = [
        'user_id',
        'xe_id',
        'xe_may_id',
        'ten_khach_hang',
        'so_dien_thoai',
        'ten_xe',
        'bien_so',
        'tong_tien',
        'trang_thai',
        'email_khach',
        'han_thanh_toan',
        'thoi_diem_thanh_toan',
        'kenh_thanh_toan',
        'ma_nhom_don',
        'ngay_nhan',
        'ngay_tra',
        'gio_nhan',
        'gio_tra',
        'diem_nhan',
        'diem_tra',
        // camelCase
        'userId',
        'xeId',
        'xeMayId',
        'tenKhachHang',
        'soDienThoai',
        'tenXe',
        'bienSo',
        'tongTien',
        'trangThai',
        'emailKhach',
        'hanThanhToan',
        'thoiDiemThanhToan',
        'kenhThanhToan',
        'maNhomDon',
        'ngayNhan',
        'ngayTra',
        'gioNhan',
        'gioTra',
        'diemNhan',
        'diemTra',
    ];

    protected $appends = [
        'userId',
        'xeId',
        'xeMayId',
        'tenKhachHang',
        'soDienThoai',
        'tenXe',
        'bienSo',
        'tongTien',
        'trangThai',
        'emailKhach',
        'hanThanhToan',
        'thoiDiemThanhToan',
        'kenhThanhToan',
        'maNhomDon',
        'ngayNhan',
        'ngayTra',
        'gioNhan',
        'gioTra',
        'diemNhan',
        'diemTra',
    ];

    protected $casts = [
        'tong_tien' => 'float',
        'han_thanh_toan' => 'datetime:Y-m-d\TH:i:s',
        'thoi_diem_thanh_toan' => 'datetime:Y-m-d\TH:i:s',
    ];

    // Accessors
    public function getUserIdAttribute()
    {
        return isset($this->attributes['user_id']) ? (int) $this->attributes['user_id'] : null;
    }

    public function getXeIdAttribute()
    {
        return isset($this->attributes['xe_id']) ? (int) $this->attributes['xe_id'] : (isset($this->attributes['xe_may_id']) ? (int) $this->attributes['xe_may_id'] : null);
    }

    public function getXeMayIdAttribute()
    {
        return $this->getXeIdAttribute();
    }

    public function getNgayNhanAttribute()
    {
        return $this->attributes['ngay_nhan'] ?? null;
    }

    public function getNgayTraAttribute()
    {
        return $this->attributes['ngay_tra'] ?? null;
    }

    public function getGioNhanAttribute()
    {
        return $this->attributes['gio_nhan'] ?? null;
    }

    public function getGioTraAttribute()
    {
        return $this->attributes['gio_tra'] ?? null;
    }

    public function getDiemNhanAttribute()
    {
        return $this->attributes['diem_nhan'] ?? null;
    }

    public function getDiemTraAttribute()
    {
        return $this->attributes['diem_tra'] ?? null;
    }

    public function getTenKhachHangAttribute()
    {
        return $this->attributes['ten_khach_hang'] ?? '';
    }

    public function getSoDienThoaiAttribute()
    {
        return $this->attributes['so_dien_thoai'] ?? '';
    }

    public function getTenXeAttribute()
    {
        return $this->attributes['ten_xe'] ?? '';
    }

    public function getBienSoAttribute()
    {
        return $this->attributes['bien_so'] ?? '';
    }

    public function getTongTienAttribute()
    {
        return isset($this->attributes['tong_tien']) ? (float) $this->attributes['tong_tien'] : 0.0;
    }

    public function getTrangThaiAttribute()
    {
        return $this->attributes['trang_thai'] ?? 'Chờ thanh toán';
    }

    public function getEmailKhachAttribute()
    {
        return $this->attributes['email_khach'] ?? '';
    }

    public function getHanThanhToanAttribute()
    {
        if (empty($this->attributes['han_thanh_toan'])) {
            return null;
        }
        return date('Y-m-d\TH:i:s', strtotime($this->attributes['han_thanh_toan']));
    }

    public function getThoiDiemThanhToanAttribute()
    {
        if (empty($this->attributes['thoi_diem_thanh_toan'])) {
            return null;
        }
        return date('Y-m-d\TH:i:s', strtotime($this->attributes['thoi_diem_thanh_toan']));
    }

    public function getKenhThanhToanAttribute()
    {
        return $this->attributes['kenh_thanh_toan'] ?? null;
    }

    public function getMaNhomDonAttribute()
    {
        return isset($this->attributes['ma_nhom_don']) ? (int) $this->attributes['ma_nhom_don'] : (isset($this->attributes['id']) ? (int) $this->attributes['id'] : null);
    }

    // Mutators
    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = $value;
    }

    public function setXeIdAttribute($value)
    {
        $this->attributes['xe_id'] = $value;
        $this->attributes['xe_may_id'] = $value;
    }

    public function setXeMayIdAttribute($value)
    {
        $this->attributes['xe_id'] = $value;
        $this->attributes['xe_may_id'] = $value;
    }

    public function setNgayNhanAttribute($value)
    {
        $this->attributes['ngay_nhan'] = $value;
    }

    public function setNgayTraAttribute($value)
    {
        $this->attributes['ngay_tra'] = $value;
    }

    public function setGioNhanAttribute($value)
    {
        $this->attributes['gio_nhan'] = $value;
    }

    public function setGioTraAttribute($value)
    {
        $this->attributes['gio_tra'] = $value;
    }

    public function setDiemNhanAttribute($value)
    {
        $this->attributes['diem_nhan'] = $value;
    }

    public function setDiemTraAttribute($value)
    {
        $this->attributes['diem_tra'] = $value;
    }

    public function setTenKhachHangAttribute($value)
    {
        $this->attributes['ten_khach_hang'] = $value;
    }

    public function setSoDienThoaiAttribute($value)
    {
        $this->attributes['so_dien_thoai'] = $value;
    }

    public function setTenXeAttribute($value)
    {
        $this->attributes['ten_xe'] = $value;
    }

    public function setBienSoAttribute($value)
    {
        $this->attributes['bien_so'] = $value;
    }

    public function setTongTienAttribute($value)
    {
        $this->attributes['tong_tien'] = $value;
    }

    public function setTrangThaiAttribute($value)
    {
        $this->attributes['trang_thai'] = $value;
    }

    public function setEmailKhachAttribute($value)
    {
        $this->attributes['email_khach'] = $value;
    }

    public function setHanThanhToanAttribute($value)
    {
        $this->attributes['han_thanh_toan'] = $value;
    }

    public function setThoiDiemThanhToanAttribute($value)
    {
        $this->attributes['thoi_diem_thanh_toan'] = $value;
    }

    public function setKenhThanhToanAttribute($value)
    {
        $this->attributes['kenh_thanh_toan'] = $value;
    }

    public function setMaNhomDonAttribute($value)
    {
        $this->attributes['ma_nhom_don'] = $value;
    }
}
