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
        $table->foreignId('asset_category_id')->nullable()->after('category_id')
              ->constrained('asset_categories')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('fixed_assets', function (Blueprint $table) {
        $table->dropForeign(['asset_category_id']);
        $table->dropColumn('asset_category_id');
    });
}
};
