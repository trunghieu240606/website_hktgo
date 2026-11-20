<?php

namespace Database\Seeders;

use App\Models\DanhMucXe;
use Illuminate\Database\Seeder;

class DanhMucXeSeeder extends Seeder
{
    public function run(): void
    {
        if (DanhMucXe::count() > 0) {
            return;
        }

        $categories = [
            ['ten' => 'Xe tay ga', 'mo_ta' => 'Xe scooter, không cần sang số', 'thu_tu' => 1],
            ['ten' => 'Xe số', 'mo_ta' => 'Xe số phổ thông', 'thu_tu' => 2],
            ['ten' => 'Xe côn tay', 'mo_ta' => 'Xe côn tay / xe thể thao', 'thu_tu' => 3],
            ['ten' => 'Xe điện', 'mo_ta' => 'Xe máy điện', 'thu_tu' => 4],
        ];

        foreach ($categories as $cat) {
            DanhMucXe::create($cat);
        }
    }
}
