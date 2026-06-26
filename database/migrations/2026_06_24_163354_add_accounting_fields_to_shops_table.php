<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('accounting_mode', 30)->default('standard')->after('status');
            $table->boolean('accounting_enabled')->default(false)->after('accounting_mode');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['accounting_mode', 'accounting_enabled']);
        });
    }
};
