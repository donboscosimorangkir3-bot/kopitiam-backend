<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Update role agar lengkap (owner, admin, cashier, customer)
            // Pakai ->change() untuk memodifikasi kolom yang sudah ada
            $table->enum('role', ['owner', 'admin', 'cashier', 'customer'])
                  ->default('customer')
                  ->change();

            // 2. Tambah kolom untuk fitur OTP
            $table->string('otp')->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp');
            $table->boolean('is_verified')->default(false)->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Balikin ke awal jika rollback
            $table->enum('role', ['admin', 'customer'])->default('customer')->change();
            $table->dropColumn(['otp', 'otp_expires_at', 'is_verified']);
        });
    }
};
