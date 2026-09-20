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
        Schema::create('zoho_books_connections', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('accounts_domain')->nullable();
            $table->string('api_domain')->nullable();
            $table->string('data_center')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('disconnected');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_books_connections');
    }
};
