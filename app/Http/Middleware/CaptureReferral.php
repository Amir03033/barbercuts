<?php

namespace App\Http\Middleware;

use App\Support\Referral;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onthoudt ?ref=code van een pitcher op elke publieke pagina, zodat de zaak later aan de pitcher gekoppeld kan worden.
 * Een onbekende code doet niets. Een eerdere geldige code blijft staan (de eerste pitcher telt).
 */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');

        if ($request->isMethod('GET') && is_string($code) && Referral::current($request) === null) {
            $pitcher = Referral::pitcherFor($code);

            if ($pitcher !== null) {
                $request->session()->put(Referral::SESSION, $pitcher->referral_code);
                Cookie::queue(Referral::COOKIE, (string) $pitcher->referral_code, Referral::DAYS * 24 * 60);
            }
        }

        return $next($request);
    }
}
