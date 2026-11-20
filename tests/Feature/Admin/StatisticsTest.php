<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Filament\Widgets\Insights\BookingStatsWidget;
use App\Filament\Widgets\Insights\BusiestDaysChartWidget;
use App\Filament\Widgets\Insights\BusiestHoursChartWidget;
use App\Filament\Widgets\Insights\CustomerStatsWidget;
use App\Filament\Widgets\Insights\GrowthStatsWidget;
use App\Filament\Widgets\Insights\OnboardingFunnelChartWidget;
use App\Filament\Widgets\Insights\RankingsWidget;
use App\Filament\Widgets\Insights\RevenueChartWidget;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\DemoVisit;
use App\Models\User;
use App\Services\AdminInsightsService;
use App\Services\Demo\DemoShopService;
use App\Support\CurrentShop;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(at('2026-11-18 12:00'));
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();

    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

function appointmentAt(Barbershop $shop, string $local, AppointmentStatus $status, AppointmentSource $source = AppointmentSource::Online, int $paid = 2500): Appointment
{
    return Appointment::factory()->for($shop)->create([
        'starts_at' => at($local), 'ends_at' => at($local)->addMinutes(30), 'blocked_until' => at($local)->addMinutes(30),
        'status' => $status, 'source' => $source,
        'amount_paid_cents' => $status === AppointmentStatus::Completed ? $paid : null,
        'tip_cents' => $status === AppointmentStatus::Completed ? 200 : null,
    ]);
}

it('counts bookings, revenue and the busiest hour of real shops only', function () {
    [$shop] = shopWithOwner();
    appointmentAt($shop, '2026-11-14 10:00', AppointmentStatus::Completed, paid: 3000);
    appointmentAt($shop, '2026-11-14 10:30', AppointmentStatus::Completed, AppointmentSource::Staff, paid: 2000);
    appointmentAt($shop, '2026-11-15 14:00', AppointmentStatus::NoShow);
    appointmentAt($shop, '2026-11-16 15:00', AppointmentStatus::Cancelled);

    // Een demozaak telt nergens mee
    $pitcher = User::factory()->create();
    $pitcher->forceFill(['is_pitcher' => true])->save();
    app(DemoShopService::class)->start($pitcher);

    $insights = app(AdminInsightsService::class);
    $bookings = $insights->bookings(30);

    expect($bookings['total'])->toBe(4)
        ->and($bookings['online'])->toBe(3)
        ->and($bookings['completed'])->toBe(2)
        ->and($bookings['no_show'])->toBe(1)
        ->and($bookings['cancelled'])->toBe(1)
        ->and($bookings['revenue_cents'])->toBe(5000)
        ->and($bookings['tips_cents'])->toBe(400)
        ->and($bookings['avg_ticket_cents'])->toBe(2500);

    $busiest = $insights->busiest(30);
    expect($busiest['hours'][10])->toBe(2)->and($busiest['weekdays'][6])->toBe(2);

    expect(array_sum($insights->revenueSeries(30)))->toBe(5000)
        ->and($insights->topServices(30)[0]['name'])->toBe('Knippen');
});

it('measures growth, the onboarding funnel and the demo conversion', function () {
    $pitcher = User::factory()->create(['name' => 'Sam']);
    $pitcher->forceFill(['is_pitcher' => true])->save();

    [$done] = shopWithOwner(['referred_by_user_id' => $pitcher->id]);
    [$stuck] = shopWithOwner();
    $stuck->forceFill(['onboarding_step' => 4, 'is_public' => false])->save();

    DemoVisit::query()->create(['pitcher_id' => $pitcher->id]);
    $converted = DemoVisit::query()->create(['pitcher_id' => $pitcher->id]);
    $converted->forceFill(['signed_up_barbershop_id' => $done->id])->save();

    $insights = app(AdminInsightsService::class);

    expect($insights->growth(30))->toMatchArray(['signups' => 2, 'finished' => 1, 'via_pitcher' => 1, 'demos' => 2, 'demo_signups' => 1])
        ->and($insights->onboardingFunnel(30))->toMatchArray(['Openingstijden' => 1, 'Afgerond' => 1])
        ->and($insights->pitchers(30)[0])->toMatchArray(['name' => 'Sam', 'demos' => 2, 'signups' => 1, 'demo_signups' => 1]);
});

it('renders every widget on the statistics page for each period', function () {
    [$shop] = shopWithOwner();
    appointmentAt($shop, '2026-11-14 10:00', AppointmentStatus::Completed);

    foreach (array_keys(AdminInsightsService::PERIODS) as $period) {
        foreach ([GrowthStatsWidget::class, BookingStatsWidget::class, CustomerStatsWidget::class, RevenueChartWidget::class,
            BusiestHoursChartWidget::class, BusiestDaysChartWidget::class, OnboardingFunnelChartWidget::class, RankingsWidget::class] as $widget) {
            Livewire::test($widget, ['pageFilters' => ['period' => $period]])->assertOk();
        }
    }

    Livewire::test(BookingStatsWidget::class, ['pageFilters' => ['period' => 30]])->assertSee('Gemiddelde bon')->assertSee('€ 25,00');
    Livewire::test(RankingsWidget::class, ['pageFilters' => ['period' => 30]])->assertSee('Populairste diensten')->assertSee('Knippen');
});

it('keeps the dashboard widgets from polling every few seconds', function () {
    foreach (glob(app_path('Filament/Widgets/*Widget.php')) as $file) {
        $class = 'App\\Filament\\Widgets\\'.basename($file, '.php');
        $property = (new ReflectionClass($class))->hasProperty('pollingInterval') ? (new ReflectionProperty($class, 'pollingInterval')) : null;

        if ($property !== null && ! is_subclass_of($class, Filament\Widgets\TableWidget::class)) {
            expect($property->getDefaultValue())->toBeNull("{$class} ververst zichzelf nog");
        }
    }
});

it('serves the statistics page to admins only', function () {
    // Zonder ingestelde 2FA stuurt Filament de admin eerst naar het instellen ervan; nooit een 403 of 404.
    expect($this->get('/admin/statistieken')->status())->toBeIn([200, 302]);

    auth()->logout();
    $this->actingAs(User::factory()->create())->get('/admin/statistieken')->assertForbidden();
});
