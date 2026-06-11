<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            // action type: assign, transfer, distribute
            $table->enum('action', ['assign', 'transfer', 'distribute']);

            // status: pending, approved, rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // payload — store the action data (employee_id, department_id, room_id etc.)
            $table->json('payload');

            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_approvals');
    }
};