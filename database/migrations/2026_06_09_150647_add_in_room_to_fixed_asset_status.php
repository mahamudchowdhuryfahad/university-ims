<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // status column এ in_room add করো
        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('in_store', 'available', 'in_room', 'assigned', 'under_maintenance', 'damaged', 'disposed') DEFAULT 'in_store'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('in_store', 'available', 'assigned', 'under_maintenance', 'damaged', 'disposed') DEFAULT 'in_store'");
    }
};
