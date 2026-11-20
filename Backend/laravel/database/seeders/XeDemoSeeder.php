<?php

namespace Database\Seeders;

use App\Models\DanhMucXe;
use App\Models\Motorbike;
use Illuminate\Database\Seeder;

class XeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Motorbike::count() > 0) {
            return;
        }

        $dmMap = DanhMucXe::pluck('id', 'ten')->toArray();

        $vehicles = [
            [
                'ten_xe' => 'Honda Future 125 FI',
                'bien_so' => '59E-55555',
                'loai_xe' => 'Xe số',
                'danh_muc_xe_id' => $dmMap['Xe số'] ?? 2,
                'gia_thue' => 150000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Honda',
                'hinh_anh' => 'images/car-5.jpg',
            ],
            [
                'ten_xe' => 'Honda Wave',
                'bien_so' => '59A-11111',
                'loai_xe' => 'Xe số',
                'danh_muc_xe_id' => $dmMap['Xe số'] ?? 2,
                'gia_thue' => 200000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Honda',
                'hinh_anh' => 'images/car-1.jpg',
            ],
            [
                'ten_xe' => 'Honda Vision 2024',
                'bien_so' => '59B-22222',
                'loai_xe' => 'Xe tay ga',
                'danh_muc_xe_id' => $dmMap['Xe tay ga'] ?? 1,
                'gia_thue' => 150000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Honda',
                'hinh_anh' => 'images/car-2.jpg',
            ],
            [
                'ten_xe' => 'Honda Vario 160',
                'bien_so' => '59C-33333',
                'loai_xe' => 'Xe tay ga',
                'danh_muc_xe_id' => $dmMap['Xe tay ga'] ?? 1,
                'gia_thue' => 100000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Honda',
                'hinh_anh' => 'images/car-3.jpg',
            ],
            [
                'ten_xe' => 'Yamaha Exciter 155 VVA',
                'bien_so' => '59D-44444',
                'loai_xe' => 'Xe côn tay',
                'danh_muc_xe_id' => $dmMap['Xe côn tay'] ?? 3,
                'gia_thue' => 200000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Yamaha',
                'hinh_anh' => 'images/car-4.jpg',
            ],
            [
                'ten_xe' => 'Honda SH 150i',
                'bien_so' => '59F-66666',
                'loai_xe' => 'Xe tay ga',
                'danh_muc_xe_id' => $dmMap['Xe tay ga'] ?? 1,
                'gia_thue' => 350000,
                'so_luong' => 5,
                'trang_thai' => 'Đang rảnh',
                'hang_xe' => 'Honda',
                'hinh_anh' => 'images/car-6.jpg',
            ],
        ];

        foreach ($vehicles as $v) {
            Motorbike::create($v);
        }
    }
}
