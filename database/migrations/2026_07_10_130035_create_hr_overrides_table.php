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
        Schema::create('hr_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('override_type');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('old_values');
            $table->json('new_values');
            $table->text('reason');
            $table->foreignId('overridden_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('overridden_at');

            $table->index(['override_type', 'related_type', 'related_id'], 'hr_overrides_related_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_overrides');
    }
};
