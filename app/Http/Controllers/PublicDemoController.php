<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Services\Demo\DemoShopService;
use App\Support\Features;
use App\Support\Referral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * De openbare demolink: barbercuts.nl/probeer of /probeer/{code van een pitcher}. Een kapper die de link krijgt (WhatsApp,
 * Instagram, e-mail) start zonder account een eigen, gevulde demozaak. Een code van een pitcher wordt onthouden, zodat een
 * aanmelding na de demo bij die pitcher hoort. De demo start pas na een tik op de knop (POST): linkvoorbeelden en
 * zoekmachines maken dus geen demozaken aan.
 */
class PublicDemoController extends Controller
{
    private const int STARTS_PER_IP_PER_HOUR = 5;

    private const int STARTS_PER_HOUR = 150;

    private const int RESTARTS_PER_HOUR = 6;

    public function show(Request $request, ?string $code = null): Response
    {
        $pitcher = Referral::pitcherFor($code);

        if ($pitcher !== null) {
            Referral::remember($request, $pitcher);
        }

        $plan = Plan::current();

        $response = response()->view('demo.try', [
            'pitcher' => $pitcher ?? Referral::current($request),
            'viewer' => $request->user(),
            'hours' => (int) config('barbercuts.demo.hours'),
            'trialDays' => $plan !== null ? $plan->trial_days : 14,
            'billing' => Features::billing(),
        ]);

        // De varianten met een code zijn dezelfde pagina: alleen /probeer zelf mag in zoekmachines.
        if ($code !== null) {
            $response->header('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    public function start(Request $request, DemoShopService $demos): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_pitcher) {
            return redirect()->route('demo');
        }

        if ($user instanceof User && $user->is_demo_visitor && $user->shops()->where('barbershops.demo_expires_at', '>', now())->exists()) {
            return redirect()->route('app.today');
        }

        if ($this->tooManyStarts($request)) {
            return back()->with('demo_error', 'Er worden nu veel demo\'s gestart. Probeer het over een paar minuten opnieuw.');
        }

        $referrer = Referral::current($request);

        // Wie met een ander account is ingelogd, start de demo in een apart demo-account (de pagina zegt dat vooraf).
        if ($user instanceof User) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        [$visitor, $shop] = $demos->startForVisitor($referrer);

        Auth::guard('web')->login($visitor);
        $request->session()->regenerate();
        $request->session()->put('current_shop_id', $shop->id);

        if ($referrer !== null) {
            $request->session()->put(Referral::SESSION, $referrer->referralCode());
        }

        return redirect()->route('app.today')->with('toast', 'Welkom in je eigen demozaak');
    }

    public function restart(Request $request, DemoShopService $demos): RedirectResponse
    {
        /** @var User $visitor */
        $visitor = $request->user();

        abort_unless($visitor->is_demo_visitor, 404);

        $key = 'demo-restart:'.$visitor->id;

        if (RateLimiter::tooManyAttempts($key, self::RESTARTS_PER_HOUR)) {
            return back()->with('toast', 'Je hebt de demo net al een paar keer opnieuw gestart. Probeer het later nog eens.');
        }

        RateLimiter::hit($key, 3600);

        $shop = $demos->restartForVisitor($visitor);
        $request->session()->put('current_shop_id', $shop->id);

        return redirect()->route('app.today')->with('toast', 'Je demo staat weer klaar, met verse gegevens');
    }

    /**
     * Een demozaak aanmaken kost wat (honderden afspraken): per netwerk en in totaal beperkt.
     */
    private function tooManyStarts(Request $request): bool
    {
        $limits = ['demo-visitor:ip:'.$request->ip() => self::STARTS_PER_IP_PER_HOUR, 'demo-visitor:all' => self::STARTS_PER_HOUR];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return true;
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }

        return false;
    }
}
