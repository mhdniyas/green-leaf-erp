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
        if (! Schema::hasColumn('tray_types', 'total_owned')) {
            Schema::table('tray_types', function (Blueprint $table) {
                $table->unsignedInteger('total_owned')->default(0)->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tray_types', 'total_owned')) {
            Schema::table('tray_types', function (Blueprint $table) {
                $table->dropColumn('total_owned');
            });
        }
    }
};
