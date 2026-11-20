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
        Schema::create('danh_muc_xe', function (Blueprint $table) {
            $table->id();
            $table->string('ten', 100)->unique();
            $table->string('mo_ta', 255)->nullable();
            $table->integer('thu_tu')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danh_muc_xe');
    }
};
