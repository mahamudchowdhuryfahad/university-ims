<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('in_store', 'available', 'in_room', 'assigned', 'pending_approval', 'under_maintenance', 'damaged', 'disposed') DEFAULT 'in_store'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('in_store', 'available', 'in_room', 'assigned', 'under_maintenance', 'damaged', 'disposed') DEFAULT 'in_store'");
    }
};