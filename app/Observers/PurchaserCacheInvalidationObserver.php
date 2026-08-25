<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaserCacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->invalidateForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidateForModel($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidateForModel($model);
    }

    private function invalidateForModel(Model $model): void
    {
        $scopes = match (true) {
            $model instanceof ShopOrder, $model instanceof ShopOrderItem => ['orders'],
            $model instanceof PurchaserCart, $model instanceof PurchaserCartItem => ['carts'],
            $model instanceof DailyProductPrice,
            $model instanceof DailyProductPriceRevision,
            $model instanceof DailyPriceApproval,
            $model instanceof DailyPricePublication,
            $model instanceof PurchaseGradePrice => ['prices'],
            $model instanceof Product, $model instanceof Category, $model instanceof ProductUnit => ['products'],
            $model instanceof BusinessSetting => ['settings'],
            default => [],
        };

        if ($scopes === []) {
            return;
        }

        DB::afterCommit(function () use ($scopes): void {
            app(PurchaserReadCacheService::class)->invalidate($scopes);
        });
    }
}
