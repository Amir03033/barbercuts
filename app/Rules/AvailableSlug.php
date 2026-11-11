<?php

namespace App\Rules;

use App\Models\Barbershop;
use App\Support\Features;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Een slug voor barbercuts.nl/{slug}: kleine letters, cijfers en streepjes, niet gereserveerd en nog vrij (§4).
 */
class AvailableSlug implements ValidationRule
{
    public function __construct(private ?int $ignoreShopId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = (string) $value;

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) < 3 || strlen($slug) > 40) {
            $fail('Gebruik 3 tot 40 kleine letters, cijfers en streepjes, bijvoorbeeld "jf-cuts".');

            return;
        }

        if (self::isReserved($slug)) {
            $fail('Deze link is niet beschikbaar. Kies een andere.');

            return;
        }

        $taken = Barbershop::query()
            ->where('slug', $slug)
            ->when($this->ignoreShopId, fn ($query) => $query->whereKeyNot($this->ignoreShopId))
            ->exists();

        if ($taken) {
            $fail('Deze link is al in gebruik. Kies een andere.');
        }
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, Features::reservedSlugs(), true)
            || str_starts_with($slug, 'livewire')
            || str_starts_with($slug, '_');
    }
}
