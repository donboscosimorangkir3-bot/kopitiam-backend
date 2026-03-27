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
        Schema::table('orders', function (Blueprint $table) {
            // Tambahkan kolom 'order_type' setelah kolom 'user_id'
            $table->enum('order_type', ['dine-in', 'pickup'])->default('pickup')->after('user_id');

            // Tambahkan kolom 'table_number' setelah 'order_type', boleh kosong (nullable)
            $table->string('table_number')->nullable()->after('order_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Hapus kolom jika migrasi di-rollback
            $table->dropColumn(['order_type', 'table_number']);
        });
    }
};
