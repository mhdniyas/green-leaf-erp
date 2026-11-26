<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_credit_note_items', function (Blueprint $table): void {
            $table->string('unit', 30)->nullable()->after('grade');
            $table->decimal('conversion_to_base', 12, 4)->nullable()->after('unit');
            $table->decimal('base_qty', 15, 3)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_credit_note_items', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'conversion_to_base', 'base_qty']);
        });
    }
};
