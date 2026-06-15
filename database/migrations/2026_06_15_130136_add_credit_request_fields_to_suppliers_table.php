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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->timestamp('credit_approval_requested_at')->nullable()->after('credit_approved');
            $table->foreignId('credit_approval_requested_by')->nullable()->after('credit_approval_requested_at')->constrained('users')->nullOnDelete();
            $table->text('credit_approval_note')->nullable()->after('credit_approval_requested_by');
            $table->timestamp('credit_approved_at')->nullable()->after('credit_approval_note');
            $table->foreignId('credit_approved_by')->nullable()->after('credit_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_approved_by');
            $table->dropColumn('credit_approved_at');
            $table->dropColumn('credit_approval_note');
            $table->dropConstrainedForeignId('credit_approval_requested_by');
            $table->dropColumn('credit_approval_requested_at');
        });
    }
};
