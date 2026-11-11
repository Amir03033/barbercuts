<?php

namespace App\Http\Middleware;

use App\Support\CurrentShop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Het adminpanel ziet alle zaken: de tenant-scope staat daar uit (docs/BOUWOPDRACHT.md §6.3, §11).
 * Alleen in de panel-middleware van /admin gebruiken, nooit in /app of op publieke pagina's.
 */
class DisableShopScope
{
    public function __construct(private CurrentShop $currentShop) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentShop->disableScope();

        return $next($request);
    }
}
