<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Kita cek dulu, jika kolom belum ada, maka tambahkan
            if (!Schema::hasColumn('settings', 'cafe_name')) {
                $table->string('cafe_name')->nullable();
            }
            if (!Schema::hasColumn('settings', 'cafe_description')) {
                $table->text('cafe_description')->nullable();
            }
            if (!Schema::hasColumn('settings', 'cafe_operation_hours')) {
                $table->string('cafe_operation_hours')->nullable();
            }
            if (!Schema::hasColumn('settings', 'cafe_phone')) {
                $table->string('cafe_phone')->nullable();
            }
            if (!Schema::hasColumn('settings', 'cafe_address')) {
                $table->string('cafe_address')->nullable();
            }
            // Kolom cafe_image biasanya sudah ada dari migration sebelumnya,
            // tapi kita tambahkan pengecekan agar aman.
            if (!Schema::hasColumn('settings', 'cafe_image')) {
                $table->string('cafe_image')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['cafe_name', 'cafe_description', 'cafe_operation_hours', 'cafe_phone', 'cafe_address', 'cafe_image']);
        });
    }
};
