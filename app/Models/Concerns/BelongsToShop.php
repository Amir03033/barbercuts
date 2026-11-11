<?php

namespace App\Models\Concerns;

use App\Models\Barbershop;
use App\Models\Scopes\ShopScope;
use App\Support\CurrentShop;
use App\Support\ShopWriteGuard;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hoort bij één zaak: filtert automatisch op de huidige zaak en vult barbershop_id in bij aanmaken (§6.3).
 */
trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopScope);

        static::creating(function (self $model): void {
            $model->barbershop_id ??= app(CurrentShop::class)->id();
        });

        // Alleen-lezen zolang het abonnement verlopen is of de zaak geblokkeerd (§7.7).
        static::saving(fn () => ShopWriteGuard::assertWritable());
        static::deleting(fn () => ShopWriteGuard::assertWritable());
    }

    /**
     * @return BelongsTo<Barbershop, $this>
     */
    public function barbershop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class);
    }
}
