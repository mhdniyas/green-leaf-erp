<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->boolean('is_extra')->default(false)->after('notes')
                ->comment('True for ad-hoc purchases not in the daily purchase order');
        });
    }

    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropColumn('is_extra');
        });
    }
};
