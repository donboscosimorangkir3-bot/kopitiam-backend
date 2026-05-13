<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah 'cancelled' ke ENUM payment_status
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Rollback: hapus 'cancelled' dari ENUM
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
