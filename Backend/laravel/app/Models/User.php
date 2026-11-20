<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'sdt',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'primaryLoginDisplay',
    ];

    public function getPrimaryLoginDisplayAttribute(): string
    {
        if (!empty($this->email)) {
            return $this->email;
        }
        return !empty($this->sdt) ? $this->sdt : '';
    }

    public function getPrimaryLoginDisplay(): string
    {
        return $this->getPrimaryLoginDisplayAttribute();
    }
}
