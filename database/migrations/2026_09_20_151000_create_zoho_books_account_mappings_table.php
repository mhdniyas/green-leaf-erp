<?php

declare(strict_types=1);

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
        Schema::create('zoho_books_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoho_books_connection_id')->constrained('zoho_books_connections')->cascadeOnDelete();
            $table->foreignId('ledger_entry_type_id')->constrained('ledger_entry_types')->cascadeOnDelete();
            $table->string('zoho_account_id');
            $table->string('zoho_account_name');
            $table->string('zoho_account_code')->nullable();
            $table->string('zoho_account_type');
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->unique(['zoho_books_connection_id', 'ledger_entry_type_id'], 'zoho_mapping_conn_entry_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_books_account_mappings');
    }
};
