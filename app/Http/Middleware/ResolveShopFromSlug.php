<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
