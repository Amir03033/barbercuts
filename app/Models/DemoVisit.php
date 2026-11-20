<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén gestarte demo via de openbare demolink. Blijft bestaan als de demozaak al gewist is, zodat de pitcher kan zien
 * hoe vaak zijn link werkt en welke demo tot een aanmelding leidde. Bevat geen gegevens van de bezoeker.
 *
 * @property int|null $pitcher_id
 * @property int|null $demo_invite_id
 * @property int|null $visitor_id
 * @property int|null $signed_up_barbershop_id
 * @property int $page_views
 * @property list<string>|null $explored
 * @property int $appointments_made
 * @property Carbon|null $last_active_at
 * @property Carbon|null $signup_started_at
 * @property Carbon|null $created_at
 */
class DemoVisit extends Model
{
    /**
     * Onderdelen van de app die we bijhouden, op routenaam (begint met). Volgorde = volgorde in het overzicht.
     */
    public const array SECTIONS = [
        'app.today' => 'Overzicht',
        'app.calendar' => 'Agenda',
        'app.appointments' => 'Nieuwe afspraak',
        'app.customers' => 'Klanten',
        'app.revenue' => 'Omzet',
        'app.reviews' => 'Reviews',
        'app.staff' => 'Kappers',
        'app.services' => 'Diensten',
        'app.opening-hours' => 'Openingstijden',
        'app.appearance' => 'Uiterlijk',
        'app.settings' => 'Instellingen',
        'app.loyalty' => 'Spaarkaart',
        'app.winback' => 'Terughalen',
        'app.waitlist' => 'Wachtlijst',
        'shop.' => 'Klantpagina',
    ];

    /** De onderdelen die er echt toe doen; samen de "verkenning" in het pitcherdashboard. */
    public const array KEY_SECTIONS = ['Agenda', 'Nieuwe afspraak', 'Klanten', 'Omzet', 'Klantpagina', 'Uiterlijk'];

    protected $fillable = ['pitcher_id', 'demo_invite_id', 'visitor_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'explored' => 'array',
            'last_active_at' => 'datetime',
            'signup_started_at' => 'datetime',
        ];
    }

    public static function sectionFor(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::SECTIONS as $prefix => $label) {
            if (str_starts_with($routeName, $prefix)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Noteer een bekeken pagina (en het onderdeel) bij deze demo.
     */
    public function recordPage(?string $section): void
    {
        $explored = $this->explored ?? [];

        if ($section !== null && ! in_array($section, $explored, true)) {
            $explored[] = $section;
        }

        $this->forceFill(['page_views' => $this->page_views + 1, 'last_active_at' => now(), 'explored' => $explored])->save();
    }

    /**
     * Hoe lang er rondgekeken is: van de start tot de laatste activiteit, in minuten (hooguit een paar uur per keer).
     */
    public function minutesSpent(): int
    {
        if ($this->last_active_at === null || $this->created_at === null) {
            return 0;
        }

        return (int) min(240, max(0, round($this->created_at->diffInSeconds($this->last_active_at) / 60)));
    }

    /**
     * @return BelongsTo<DemoInvite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(DemoInvite::class, 'demo_invite_id');
    }

    /**
     * @return BelongsTo<Barbershop, $this>
     */
    public function signedUpShop(): BelongsTo
    {
        return $this->belongsTo(Barbershop::class, 'signed_up_barbershop_id');
    }
}
