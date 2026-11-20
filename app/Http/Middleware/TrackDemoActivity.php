<?php

namespace App\Http\Middleware;

use App\Models\DemoVisit;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Houdt bij wat een bezoeker van de openbare demolink bekijkt (welke onderdelen, hoe lang), zodat de pitcher de
 * voortgang van zijn demo ziet. Alleen voor tijdelijke demo-accounts; echte gebruikers worden nooit gevolgd.
 * Alleen gewone paginaverzoeken (ook wire:navigate), niet de Livewire-updates binnen een pagina.
 */
class TrackDemoActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User && $user->is_demo_visitor && $request->isMethod('GET') && $response->getStatusCode() < 400) {
            DemoVisit::query()->where('visitor_id', $user->id)->latest('id')->first()
                ?->recordPage(DemoVisit::sectionFor($request->route()?->getName()));
        }

        return $response;
    }
}
