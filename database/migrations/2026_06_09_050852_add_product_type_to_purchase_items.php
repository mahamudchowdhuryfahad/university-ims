<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->enum('product_type', ['consumable', 'fixed_asset'])->default('consumable')->after('product_id');
            $table->string('asset_category_id')->nullable()->after('product_type');
            $table->string('asset_name')->nullable()->after('asset_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'asset_category_id', 'asset_name']);
        });
    }
};