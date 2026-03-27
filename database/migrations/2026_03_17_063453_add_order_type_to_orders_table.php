<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cek dulu sebelum tambah, agar tidak error jika kolom sudah ada

            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status');
            }

            if (!Schema::hasColumn('orders', 'order_type')) {
                // 'pickup' = ambil di kasir, 'dine-in' = makan di tempat
                $table->string('order_type')->default('pickup')->after('payment_method');
            }

            if (!Schema::hasColumn('orders', 'table_number')) {
                // Nomor meja, hanya diisi jika order_type = 'dine-in'
                $table->string('table_number')->nullable()->after('order_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'order_type', 'table_number']);
        });
    }
};
