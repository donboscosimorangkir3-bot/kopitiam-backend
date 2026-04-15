<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    // Ganti 'tables' menjadi 'cafe_tables'
    Schema::table('cafe_tables', function (Blueprint $table) {
        $table->boolean('is_available')->default(true)->after('number');
    });
}

public function down()
{
    Schema::table('cafe_tables', function (Blueprint $table) {
        $table->dropColumn('is_available');
    });
}
};
