<?php

namespace App\Models;

use App\Support\Referral;
use Database\Factories\DemoInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Een persoonlijke demo die een pitcher klaarzet voor één kapperszaak: wie de link opent, krijgt een demo met de
 * naam (en eventueel plaats en kleur) van die zaak. Geen tenant-scope: hoort bij de pitcher.
 *
 * @property int $user_id
 * @property string $token
 * @property string $shop_name
 * @property string|null $city
 * @property string|null $accent_color
 * @property int $starts
 * @property int $views
 * @property Carbon|null $last_viewed_at
 * @property Carbon|null $last_started_at
 */
class DemoInvite extends Model
{
    /** @use HasFactory<DemoInviteFactory> */
    use HasFactory;

    protected $fillable = ['shop_name', 'city', 'accent_color'];

    protected static function booted(): void
    {
        static::creating(function (DemoInvite $invite): void {
            $invite->token ??= self::freshToken();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_started_at' => 'datetime', 'last_viewed_at' => 'datetime'];
    }

    public static function freshToken(): string
    {
        do {
            // Kort en zonder verwarrende tekens (0/o, 1/l), zodat een link ook over te typen is.
            $token = Str::lower(Str::random(8));
            $token = strtr($token, ['0' => 'q', 'o' => 'x', '1' => 'z', 'l' => 'k']);
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Zoek een uitnodiging bij de code van de pitcher en de token; null als ze niet bij elkaar horen.
     */
    public static function findForLink(?string $code, ?string $token): ?self
    {
        $pitcher = Referral::pitcherFor($code);

        if ($pitcher === null || $token === null || preg_match('/^[a-z0-9]{8}$/', $token) !== 1) {
            return null;
        }

        return self::query()->where('user_id', $pitcher->id)->where('token', $token)->first();
    }

    public function url(): string
    {
        return route('demo.try', ['code' => $this->pitcher->referralCode(), 'invite' => $this->token]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pitcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<DemoVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(DemoVisit::class);
    }
}
