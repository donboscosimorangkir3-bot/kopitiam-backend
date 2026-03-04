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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Judul pengumuman
            $table->text('content'); // Isi pengumuman
            $table->string('image_url')->nullable(); // Gambar pengumuman (opsional)
            $table->boolean('is_active')->default(true); // Status aktif/tidak
            $table->timestamp('published_at')->nullable(); // Tanggal publish
            $table->timestamp('expired_at')->nullable();   // Tanggal kadaluarsa (opsional)
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
