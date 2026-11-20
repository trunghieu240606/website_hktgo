<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('don_thue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('xe_id')->nullable()->index();
            $table->foreignId('xe_may_id')
                ->nullable()
                ->constrained('xe_may')
                ->nullOnDelete();
            $table->string('ten_khach_hang')->nullable();
            $table->string('so_dien_thoai', 30)->nullable()->index();
            $table->string('ten_xe')->nullable();
            $table->string('bien_so', 50)->nullable()->index();
            $table->double('tong_tien', 14, 2)->nullable();
            $table->string('ngay_nhan', 50)->nullable();
            $table->string('ngay_tra', 50)->nullable();
            $table->string('gio_nhan', 20)->nullable();
            $table->string('gio_tra', 20)->nullable();
            $table->string('diem_nhan', 255)->nullable();
            $table->string('diem_tra', 255)->nullable();
            $table->string('trang_thai', 50)->default('Chờ thanh toán')->index();
            $table->string('email_khach')->nullable()->index();
            $table->dateTime('han_thanh_toan')->nullable()->index();
            $table->dateTime('thoi_diem_thanh_toan')->nullable();
            $table->string('kenh_thanh_toan', 50)->nullable();
            $table->unsignedBigInteger('ma_nhom_don')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('don_thue');
    }
};
