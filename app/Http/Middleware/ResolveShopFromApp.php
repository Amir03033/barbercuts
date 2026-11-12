<?php

namespace App\Http\Middleware;

use App\Enums\AccessState;
use App\Models\Barbershop;
use App\Models\User;
use App\Support\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bepaalt de zaak voor /app: de gekozen zaak uit de sessie, anders het eerste lidmaatschap (§6.3).
 */
class ResolveShopFromApp
{
    public function __construct(private CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $shops = $user->shops()->orderBy('shop_user.id')->get();

        // Een verlopen demozaak (fase 6) bestaat voor de pitcher niet meer; hij wordt binnen het uur gewist.
        $hadDemo = $shops->contains(fn (Barbershop $candidate): bool => $candidate->isExpiredDemo());
        $shops = $shops->reject(fn (Barbershop $candidate): bool => $candidate->isExpiredDemo())->values();

        if ($shops->isEmpty() && ($hadDemo || $user->is_pitcher)) {
            return redirect()->route('demo');
        }

        /** @var Barbershop|null $shop */
        $shop = $shops->firstWhere('id', $request->session()->get('current_shop_id')) ?? $shops->first();

        if ($shop === null) {
            return redirect()->route('onboarding');
        }

        if (! $shop->hasFinishedOnboarding()) {
            /** @var Barbershop|null $finished */
            $finished = $shops->first(fn (Barbershop $candidate): bool => $candidate->hasFinishedOnboarding());

            if ($finished === null) {
                abort_unless($user->isOwnerOf($shop), 403);

                return redirect()->route('onboarding');
            }

            $shop = $finished;
        }

        $request->session()->put('current_shop_id', $shop->id);
        $this->currentShop->set($shop);
        View::share('currentShop', $shop);

        // Door de admin geblokkeerd: alleen een melding (uitloggen kan nog), nooit data verwijderen (§7.7).
        if ($shop->accessState() === AccessState::Suspended && ! $request->routeIs('logout', 'impersonate.stop')) {
            return response()->view('app.suspended', ['shop' => $shop], 403);
        }

        return $next($request);
    }
}
