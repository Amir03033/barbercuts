<?php

namespace App\Models\Scopes;

use App\Support\CurrentShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class ShopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentShop::class);

        if ($current->scopeIsDisabled()) {
            return;
        }

        $shopId = $current->id();

        if ($shopId === null) {
            // Geen zaak bekend: niets teruggeven in plaats van alles.
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('barbershop_id'), $shopId);
    }
}
