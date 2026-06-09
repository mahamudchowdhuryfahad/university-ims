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
    Schema::table('fixed_assets', function (Blueprint $table) {
        $table->text('serial_number')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('fixed_assets', function (Blueprint $table) {
        $table->string('serial_number')->nullable()->change();
    });
}
};
