<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

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
     * Onthoud deze pitcher voor de bezoeker (sessie en een cookie van een maand), tenzij er al een geldige pitcher is:
     * de eerste pitcher telt.
     */
    public static function remember(Request $request, User $pitcher): void
    {
        if (self::current($request) !== null || ! $pitcher->is_pitcher) {
            return;
        }

        $request->session()->put(self::SESSION, $pitcher->referralCode());
        Cookie::queue(self::COOKIE, $pitcher->referralCode(), self::DAYS * 24 * 60);
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
