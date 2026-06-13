<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `asset_approvals` MODIFY `action` ENUM('assign', 'transfer', 'distribute', 'dispose') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `asset_approvals` MODIFY `action` ENUM('assign', 'transfer', 'distribute') NOT NULL");
    }
};
