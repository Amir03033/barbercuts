<?php

namespace App\Http\Middleware;

use App\Enums\AccessState;
use App\Models\Barbershop;
use App\Support\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bepaalt de zaak voor de publieke pagina's /{slug}. Alleen publieke zaken (§6.3).
 */
class ResolveShopFromSlug
{
    public function __construct(private CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = Barbershop::query()
            ->where('slug', (string) $request->route('slug'))
            ->where('is_public', true)
            ->firstOrFail();

        $this->currentShop->set($shop);
        View::share('shop', $shop);

        // Door de admin geblokkeerd: de pagina is niet beschikbaar (§7.7).
        if ($shop->accessState() === AccessState::Suspended) {
            return response()->view('shop.unavailable', ['shop' => $shop], 503);
        }

        // Demozaak van een pitcher (fase 6): weg zodra de tijd om is, en nooit in een zoekmachine.
        if ($shop->is_demo) {
            abort_if($shop->isExpiredDemo(), 404);

            $response = $next($request);
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        return $next($request);
    }
}
