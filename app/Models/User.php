<?php

namespace App\Models;

use App\Enums\ShopRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Rollen per zaak, onthouden binnen dit request.
     *
     * @var array<int, ShopRole|null>
     */
    private array $shopRoles = [];

    /**
     * De zaken waar deze gebruiker in /app mag inloggen.
     *
     * @return BelongsToMany<Barbershop, $this>
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Barbershop::class, 'shop_user')->withPivot('role')->withTimestamps();
    }

    /**
     * De rol van deze gebruiker binnen een zaak, of null als hij er niet bij hoort (§6.2).
     */
    public function roleIn(Barbershop $shop): ?ShopRole
    {
        if (! array_key_exists($shop->id, $this->shopRoles)) {
            $role = $this->shops()->where('barbershops.id', $shop->id)->value('shop_user.role');

            $this->shopRoles[$shop->id] = $role === null ? null : ShopRole::from($role);
        }

        return $this->shopRoles[$shop->id];
    }

    public function isOwnerOf(Barbershop $shop): bool
    {
        return $this->roleIn($shop) === ShopRole::Owner;
    }

    public function isMemberOf(Barbershop $shop): bool
    {
        return $this->roleIn($shop) !== null;
    }

    public function forgetShopRoles(): void
    {
        $this->shopRoles = [];
    }
}
