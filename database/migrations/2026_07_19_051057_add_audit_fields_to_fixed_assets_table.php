<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->date('last_audit_date')->nullable()->after('depreciation_rate');
            $table->decimal('last_audited_accumulated_depreciation', 12, 2)->nullable()->after('last_audit_date');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['last_audit_date', 'last_audited_accumulated_depreciation']);
        });
    }
};
