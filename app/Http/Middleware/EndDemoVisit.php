<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Demo\DemoShopService;
use App\Support\Referral;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wie vanuit de openbare demo zijn eigen zaak gaat aanmelden (of gaat inloggen of registreren), verlaat de demo: het
 * tijdelijke demo-account en de demozaak worden gewist en je bent uitgelogd. De pitcher van de demolink blijft onthouden.
 */
class EndDemoVisit
{
    public function __construct(private DemoShopService $demos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_demo_visitor) {
            $referrer = Referral::current($request);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->demos->endVisit($user);

            if ($referrer !== null) {
                $request->session()->put(Referral::SESSION, $referrer->referralCode());
            }
        }

        return $next($request);
    }
}
