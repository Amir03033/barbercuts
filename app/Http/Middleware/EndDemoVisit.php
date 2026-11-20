<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Demo\DemoShopService;
use App\Support\DemoCarryover;
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

            // Wat de bezoeker in de demo instelde, gaat mee naar het aanmelden (naam, kleur, diensten, openingstijden).
            $shop = $user->shops()->where('barbershops.is_demo', true)->where('barbershops.demo_expires_at', '>', now())->latest('barbershops.id')->first();
            $visit = $this->demos->visitFor($user);
            $carryover = $shop !== null ? DemoCarryover::fromShop($shop, $visit) : null;

            // De pitcher ziet dat de kapper aan het aanmelden begon.
            if ($visit !== null && $request->routeIs('onboarding')) {
                $visit->forceFill(['signup_started_at' => now()])->save();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->demos->endVisit($user);

            if ($referrer !== null) {
                $request->session()->put(Referral::SESSION, $referrer->referralCode());
            }

            if ($carryover !== null) {
                $request->session()->put(DemoCarryover::SESSION, $carryover);
            }
        }

        return $next($request);
    }
}
