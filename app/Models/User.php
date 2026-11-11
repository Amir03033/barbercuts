<?php

namespace App\Models;

use App\Enums\ShopRole;
use App\Models\Scopes\ShopScope;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

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
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Alleen platformadmins mogen in /admin (§6.1, §11).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
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

    /**
     * Waar deze gebruiker na inloggen thuishoort: de kapper-app, of het klantaccount.
     */
    public function homeRoute(): string
    {
        return $this->shops()->exists() ? route('app.today', absolute: false) : route('account', absolute: false);
    }

    /**
     * De klantrecords van deze gebruiker bij alle zaken (voor /account). Bewust zonder tenant-scope (§6.3).
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope(ShopScope::class);
    }
}
