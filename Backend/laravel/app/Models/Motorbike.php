<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Motorbike extends Model
{
    use HasFactory;

    protected $table = 'xe_may';

    protected $fillable = [
        'danh_muc_xe_id',
        'ten_xe',
        'bien_so',
        'loai_xe',
        'gia_thue',
        'so_luong',
        'trang_thai',
        'hang_xe',
        'hinh_anh',
        // Support camelCase mass assignment from requests
        'danhMucXeId',
        'tenXe',
        'bienSo',
        'loaiXe',
        'giaThue',
        'soLuong',
        'trangThai',
        'hangXe',
        'hinhAnh',
    ];

    protected $appends = [
        'danhMucXeId',
        'tenXe',
        'bienSo',
        'loaiXe',
        'giaThue',
        'soLuong',
        'trangThai',
        'hangXe',
        'hinhAnh',
    ];

    protected $casts = [
        'danh_muc_xe_id' => 'integer',
        'gia_thue' => 'float',
        'so_luong' => 'integer',
    ];

    // Accessors
    public function getDanhMucXeIdAttribute()
    {
        return isset($this->attributes['danh_muc_xe_id']) ? (int) $this->attributes['danh_muc_xe_id'] : null;
    }

    public function getTenXeAttribute()
    {
        return $this->attributes['ten_xe'] ?? '';
    }

    public function getBienSoAttribute()
    {
        return $this->attributes['bien_so'] ?? '';
    }

    public function getLoaiXeAttribute()
    {
        return $this->attributes['loai_xe'] ?? '';
    }

    public function getGiaThueAttribute()
    {
        return isset($this->attributes['gia_thue']) ? (float) $this->attributes['gia_thue'] : 0.0;
    }

    public function getSoLuongAttribute()
    {
        return isset($this->attributes['so_luong']) ? (int) $this->attributes['so_luong'] : 5;
    }

    public function getTrangThaiAttribute()
    {
        return $this->attributes['trang_thai'] ?? 'Đang rảnh';
    }

    public function getHangXeAttribute()
    {
        return $this->attributes['hang_xe'] ?? 'Honda';
    }

    public function getHinhAnhAttribute()
    {
        return $this->attributes['hinh_anh'] ?? '';
    }

    // Mutators
    public function setDanhMucXeIdAttribute($value)
    {
        $this->attributes['danh_muc_xe_id'] = $value !== null && $value !== '' ? (int) $value : null;
    }

    public function setTenXeAttribute($value)
    {
        $this->attributes['ten_xe'] = $value;
    }

    public function setBienSoAttribute($value)
    {
        $this->attributes['bien_so'] = $value;
    }

    public function setLoaiXeAttribute($value)
    {
        $this->attributes['loai_xe'] = $value;
    }

    public function setGiaThueAttribute($value)
    {
        $this->attributes['gia_thue'] = $value;
    }

    public function setSoLuongAttribute($value)
    {
        $this->attributes['so_luong'] = (int) $value;
    }

    public function setTrangThaiAttribute($value)
    {
        $this->attributes['trang_thai'] = $value;
    }

    public function setHangXeAttribute($value)
    {
        $this->attributes['hang_xe'] = $value;
    }

    public function setHinhAnhAttribute($value)
    {
        $this->attributes['hinh_anh'] = $value;
    }
}
