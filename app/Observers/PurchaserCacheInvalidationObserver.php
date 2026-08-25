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
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Purchasing\DailyPurchaserPriceSyncService;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaserCacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->syncPurchaserPriceForModel($model);
        $this->invalidateForModel($model);
    }

    public function deleted(Model $model): void
    {
        $this->syncPurchaserPriceForModel($model);
        $this->invalidateForModel($model);
    }

    public function restored(Model $model): void
    {
        $this->syncPurchaserPriceForModel($model);
        $this->invalidateForModel($model);
    }

    private function syncPurchaserPriceForModel(Model $model): void
    {
        if ($model instanceof PurchaserCartItem) {
            $businessDate = $model->cart?->business_date;
            $productId = (int) $model->product_id;
            if ($businessDate && $productId > 0) {
                app(DailyPurchaserPriceSyncService::class)->syncForBusinessDate($businessDate, $productId);
            }
        } elseif ($model instanceof PurchaserCart) {
            $businessDate = $model->business_date;
            if ($businessDate) {
                app(DailyPurchaserPriceSyncService::class)->syncForBusinessDate($businessDate);
            }
        } elseif ($model instanceof PurchaseInvoice) {
            $businessDate = $model->purchaserCart?->business_date;
            if ($businessDate) {
                app(DailyPurchaserPriceSyncService::class)->syncForBusinessDate($businessDate);
            }
        }
    }

    private function invalidateForModel(Model $model): void
    {
        $scopes = match (true) {
            $model instanceof ShopOrder, $model instanceof ShopOrderItem => ['orders'],
            $model instanceof PurchaserCart, $model instanceof PurchaserCartItem, $model instanceof PurchaseInvoice => ['carts'],
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
