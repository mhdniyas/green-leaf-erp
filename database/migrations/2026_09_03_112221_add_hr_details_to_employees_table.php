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
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('email');
            $table->string('id_type', 30)->nullable()->after('photo_path');
            $table->string('other_id_type', 50)->nullable()->after('id_type');
            $table->string('id_number', 50)->nullable()->after('other_id_type');
            $table->string('id_front_path')->nullable()->after('id_number');
            $table->string('id_back_path')->nullable()->after('id_front_path');
            $table->string('alternate_phone', 30)->nullable()->after('phone');
            $table->text('address')->nullable()->after('alternate_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'photo_path',
                'id_type',
                'other_id_type',
                'id_number',
                'id_front_path',
                'id_back_path',
                'alternate_phone',
                'address',
            ]);
        });
    }
};
