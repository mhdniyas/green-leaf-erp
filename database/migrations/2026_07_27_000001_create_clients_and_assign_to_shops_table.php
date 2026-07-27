<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->foreignId('client_id')
                ->nullable()
                ->after('shop_price_group_id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index(['client_id', 'status']);
        });

        $aishwaryaVeg = Client::query()->create([
            'code' => 'AISHWARYA_VEG',
            'name' => 'Aishwarya Veg',
            'status' => 'active',
            'notes' => 'Default client for existing owned-shop accounting workflow.',
        ]);

        Shop::query()
            ->where('accounting_enabled', true)
            ->where('accounting_mode', 'owned')
            ->update(['client_id' => $aishwaryaVeg->id]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::dropIfExists('clients');
    }
};
