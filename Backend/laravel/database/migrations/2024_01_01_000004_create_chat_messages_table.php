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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->text('noi_dung');
            $table->string('nguoi_gui', 100)->default('Khách hàng')->index();
            $table->string('from_role', 20)->default('USER')->index();
            $table->string('kenh', 30)->default('DIRECT');
            $table->string('to_user', 100)->nullable()->index();
            $table->boolean('read_by_admin')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
