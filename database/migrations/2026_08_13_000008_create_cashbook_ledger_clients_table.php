<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clients are businesses (like Aiswarya Veg) that own groups of shops.
        // Green Leaf (the main company) manages billing and settlement with clients.
        Schema::create('cashbook_ledger_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. "Aiswarya Veg"
            $table->string('slug')->unique();              // e.g. "aiswarya-veg"
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Assign each shop to a client
        Schema::table('shop_ledger_profiles', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('id')
                ->constrained('cashbook_ledger_clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_ledger_profiles', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::dropIfExists('cashbook_ledger_clients');
    }
};
