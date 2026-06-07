<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->date('disposal_date');
            $table->string('method')->default('written_off');
            $table->decimal('disposal_value', 12, 2)->default(0);
            $table->string('disposed_to')->nullable();
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('disposed_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_records');
    }
};
