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
        Schema::table('users', function (Blueprint $table) {
            // Ubah tipe kolom 'role' menjadi enum dengan pilihan baru
            // Pastikan Anda menyertakan role lama ('admin', 'customer') agar data tidak hilang
            $table->enum('role', ['owner', 'admin', 'cashier', 'customer'])->default('customer')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Jika di-rollback, kembalikan ke definisi sebelumnya (atau ke default)
            $table->enum('role', ['admin', 'customer'])->default('customer')->change();
        });
    }
};
