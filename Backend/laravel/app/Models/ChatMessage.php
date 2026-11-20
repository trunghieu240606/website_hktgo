<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';

    protected $fillable = [
        'noi_dung',
        'nguoi_gui',
        'from_role',
        'kenh',
        'to_user',
        'read_by_admin',
        // camelCase
        'noiDung',
        'nguoiGui',
        'fromRole',
        'toUser',
        'readByAdmin',
    ];

    protected $appends = [
        'noiDung',
        'nguoiGui',
        'fromRole',
        'toUser',
        'readByAdmin',
        'createdAt',
    ];

    protected $casts = [
        'read_by_admin' => 'boolean',
    ];

    public function getNoiDungAttribute()
    {
        return $this->attributes['noi_dung'] ?? '';
    }

    public function getNguoiGuiAttribute()
    {
        return $this->attributes['nguoi_gui'] ?? 'Khách hàng';
    }

    public function getFromRoleAttribute()
    {
        return $this->attributes['from_role'] ?? 'USER';
    }

    public function getToUserAttribute()
    {
        return $this->attributes['to_user'] ?? '';
    }

    public function getReadByAdminAttribute()
    {
        return !empty($this->attributes['read_by_admin']);
    }

    public function getCreatedAtAttribute()
    {
        if (empty($this->attributes['created_at'])) {
            return now()->toISOString();
        }
        return date('c', strtotime($this->attributes['created_at']));
    }

    public function setNoiDungAttribute($value)
    {
        $this->attributes['noi_dung'] = $value;
    }

    public function setNguoiGuiAttribute($value)
    {
        $this->attributes['nguoi_gui'] = $value;
    }

    public function setFromRoleAttribute($value)
    {
        $this->attributes['from_role'] = $value;
    }

    public function setToUserAttribute($value)
    {
        $this->attributes['to_user'] = $value;
    }

    public function setReadByAdminAttribute($value)
    {
        $this->attributes['read_by_admin'] = (bool) $value;
    }
}
