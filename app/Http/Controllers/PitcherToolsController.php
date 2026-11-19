<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Support\Features;
use App\Support\PitcherMessages;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Gereedschap voor pitchers: kant-en-klare berichten en een printbare flyer met de eigen QR-code.
 */
class PitcherToolsController extends Controller
{
    public function messages(Request $request): View
    {
        /** @var User $pitcher */
        $pitcher = $request->user();

        return view('pitcher.messages', ['pitcher' => $pitcher, 'messages' => PitcherMessages::for($pitcher)]);
    }

    public function flyer(Request $request): View
    {
        /** @var User $pitcher */
        $pitcher = $request->user();
        $link = route('onboarding', ['ref' => $pitcher->referralCode()]);
        $plan = Plan::current();

        return view('pitcher.flyer', [
            'pitcher' => $pitcher,
            'link' => $link,
            'shortLink' => preg_replace('#^https?://#', '', $link),
            'qr' => (new Writer(new ImageRenderer(new RendererStyle(400, 0), new SvgImageBackEnd)))->writeString($link),
            'price' => money($plan !== null ? $plan->price_cents : 2000),
            'trialDays' => $plan !== null ? $plan->trial_days : 14,
            'billing' => Features::billing(),
        ]);
    }
}
