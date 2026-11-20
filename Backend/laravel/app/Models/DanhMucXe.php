<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanhMucXe extends Model
{
    use HasFactory;

    protected $table = 'danh_muc_xe';

    protected $fillable = [
        'ten',
        'mo_ta',
        'thu_tu',
        // camelCase
        'moTa',
        'thuTu',
    ];

    protected $appends = [
        'moTa',
        'thuTu',
    ];

    protected $casts = [
        'thu_tu' => 'integer',
    ];

    public function getMoTaAttribute()
    {
        return $this->attributes['mo_ta'] ?? '';
    }

    public function getThuTuAttribute()
    {
        return isset($this->attributes['thu_tu']) ? (int) $this->attributes['thu_tu'] : 0;
    }

    public function setMoTaAttribute($value)
    {
        $this->attributes['mo_ta'] = $value;
    }

    public function setThuTuAttribute($value)
    {
        $this->attributes['thu_tu'] = $value;
    }
}
