<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entry_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. 'cash_purchase', 'vehicle', 'rent_expense'
            $table->string('name');           // display name, e.g. 'Cash Purchase'
            $table->string('category');       // income | expense | transfer | settlement
            $table->string('system_type')->nullable(); // optional internal grouping
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entry_types');
    }
};
