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
        Schema::create('xe_may', function (Blueprint $table) {
            $table->id();
            $table->foreignId('danh_muc_xe_id')
                ->nullable()
                ->constrained('danh_muc_xe')
                ->nullOnDelete();
            $table->string('ten_xe');
            $table->string('bien_so')->unique();
            $table->string('loai_xe');
            $table->double('gia_thue', 12, 2)->default(0);
            $table->integer('so_luong')->default(5);
            $table->string('trang_thai')->default('Đang rảnh');
            $table->string('hang_xe', 100)->default('Honda');
            $table->text('hinh_anh')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xe_may');
    }
};
