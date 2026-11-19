<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * De link uit de bevestigingsmail. De gesigneerde link wijst zelf het account aan, dus dit werkt ook als de mail opent in
 * een andere browser of app dan waar je bent ingelogd. Een ander open scherm in dezelfde browser hoort het via een
 * BroadcastChannel en gaat meteen verder; andere apparaten zien het binnen een paar seconden (wire:poll).
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse|Response
    {
        $user = User::query()->find($id);
        $matches = $user !== null && hash_equals(sha1($user->getEmailForVerification()), $hash);

        if (! $request->hasValidSignature()) {
            // Een oude link van een adres dat inmiddels bevestigd is: dan is er niets aan de hand.
            return $matches && $user->hasVerifiedEmail()
                ? $this->result($request, 'verified', $user)
                : $this->result($request, 'expired', $matches ? $user : null, 403);
        }

        if (! $matches) {
            return $this->result($request, 'invalid', null, 403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->user()?->is($user)) {
            return redirect()->to($user->homeRoute())->with(['toast' => 'Je e-mailadres is bevestigd.', 'email_verified' => true]);
        }

        return $this->result($request, 'verified', $user);
    }

    /**
     * @param  'verified'|'expired'|'invalid'  $state
     */
    private function result(Request $request, string $state, ?User $user, int $status = 200): Response
    {
        /** @var View $view */
        $view = view('auth.email-verification-result', [
            'state' => $state,
            'email' => $user?->email,
            'viewer' => $request->user(),
            'sameUser' => $user !== null && $request->user()?->is($user) === true,
            'hours' => intdiv((int) config('auth.verification.expire', 60), 60),
        ]);

        return response($view, $status)->header('X-Robots-Tag', 'noindex');
    }
}
