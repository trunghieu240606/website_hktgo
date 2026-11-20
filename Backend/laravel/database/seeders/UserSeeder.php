<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::create([
                'name' => 'Quản trị viên',
                'email' => 'admin@example.com',
                'sdt' => '0901234567',
                'role' => 'ADMIN',
                'password' => Hash::make('123456'),
            ]);
        }

        if (User::where('email', 'quanly@example.com')->doesntExist()) {
            User::create([
                'name' => 'Quản lý Cửa hàng',
                'email' => 'quanly@example.com',
                'sdt' => '0902222333',
                'role' => 'MANAGER',
                'password' => Hash::make('123456'),
            ]);
        }

        if (User::where('email', 'nhanvien@example.com')->doesntExist()) {
            User::create([
                'name' => 'Nhân viên Tư vấn',
                'email' => 'nhanvien@example.com',
                'sdt' => '0903333444',
                'role' => 'STAFF',
                'password' => Hash::make('123456'),
            ]);
        }

        if (User::where('email', 'khach@example.com')->doesntExist()) {
            User::create([
                'name' => 'Khách hàng Demo',
                'email' => 'khach@example.com',
                'sdt' => '0912345678',
                'role' => 'USER',
                'password' => Hash::make('123456'),
            ]);
        }
    }
}
