<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->string('location')->nullable()->after('room_id'); // Store location
            $table->integer('quantity_in_store')->default(0)->after('location'); // Store quantity
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['location', 'quantity_in_store']);
        });
    }
};