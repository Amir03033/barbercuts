<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Een pitcher brengt een zaak aan via een link met ?ref=code. We onthouden die code een maand (cookie) en in de sessie,
 * en koppelen de zaak aan de pitcher op het moment dat ze wordt aangemaakt.
 */
class Referral
{
    public const COOKIE = 'bc_ref';

    public const SESSION = 'referral_code';

    public const DAYS = 30;

    /**
     * De pitcher bij een code, of null als de code niet (meer) bij een pitcher hoort.
     */
    public static function pitcherFor(?string $code): ?User
    {
        $code = strtolower(trim((string) $code));

        if ($code === '' || preg_match('/^[a-z0-9-]{1,40}$/', $code) !== 1) {
            return null;
        }

        return User::query()->where('is_pitcher', true)->where('referral_code', $code)->first();
    }

    /**
     * De pitcher die deze bezoeker heeft aangebracht, uit de sessie of anders uit de cookie.
     */
    public static function current(Request $request): ?User
    {
        $code = session()->get(self::SESSION);

        return self::pitcherFor($code ?: $request->cookie(self::COOKIE));
    }
}
