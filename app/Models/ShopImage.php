<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto voor de publieke pagina van een zaak.
 */
#[Fillable(['path', 'sort_order'])]
class ShopImage extends Model
{
    use BelongsToShop;

    public function url(): ?string
    {
        return ImageStore::url($this->path);
    }
}
