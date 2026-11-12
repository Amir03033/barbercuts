<?php

namespace App\Livewire\Concerns;

use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Services\Booking\AppointmentService;
use App\Services\LoyaltyService;
use Closure;

/**
 * Acties op een afspraak vanuit Vandaag en de Agenda: voltooien, no-show, annuleren, terugzetten (§9.1).
 * Hoort bij <x-app.appointment-sheet>. De rechten worden in AppointmentService gecontroleerd (policy).
 */
trait HandlesAppointmentActions
{
    public ?int $selectedId = null;

    /** actions | complete | cancel */
    public string $sheetMode = 'actions';

    public string $amount = '';

    public string $tip = '0';

    public bool $customTip = false;

    /** De volle spaarkaart van de klant wordt bij deze afspraak verzilverd (fase 9). */
    public bool $redeem = false;

    public function selectAppointment(int $appointmentId): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        $this->selectedId = $appointment->id;
        $this->sheetMode = 'actions';
        $this->amount = money_input($appointment->price_cents);
        $this->tip = '0';
        $this->customTip = false;
        $this->redeem = false;
        $this->resetErrorBag();

        $this->dispatch('open-sheet', name: 'appointment');
    }

    public function showSheet(string $mode): void
    {
        $this->sheetMode = in_array($mode, ['actions', 'complete', 'cancel'], true) ? $mode : 'actions';
    }

    public function setTip(string $tip): void
    {
        $this->customTip = $tip === 'custom';
        $this->tip = $this->customTip ? '' : $tip;
    }

    /**
     * Beloning van de spaarkaart verzilveren (of toch niet): het bedrag past zich aan, de kapper kan het nog wijzigen.
     */
    public function toggleRedeem(LoyaltyService $loyalty): void
    {
        $appointment = Appointment::query()->with('customer')->findOrFail($this->selectedId);
        $card = $loyalty->cardFor($appointment->barbershop_id);

        $this->redeem = ! $this->redeem && $loyalty->canRedeem($card, $appointment->customer);
        $this->amount = money_input($this->redeem && $card !== null ? $card->amountAfterReward($appointment->price_cents) : $appointment->price_cents);
    }

    /**
     * Voltooien: de knop van de betaalmethode slaat meteen op (maximaal 3 tikken als het bedrag klopt).
     */
    public function completeAppointment(string $method, AppointmentService $appointments): void
    {
        $amount = parse_money($this->amount);
        $tip = trim($this->tip) === '' ? 0 : parse_money($this->tip);

        if ($amount === null) {
            $this->addError('amount', 'Vul een bedrag in, bijvoorbeeld 25,00.');

            return;
        }

        if ($tip === null) {
            $this->addError('tip', 'Vul een bedrag in, bijvoorbeeld 2,00.');

            return;
        }

        $this->act(
            fn (Appointment $appointment) => $appointments->complete($appointment, $this->user(), $amount, $tip, PaymentMethod::tryFrom($method) ?? PaymentMethod::Other, $this->redeem),
            'Voltooid · '.money($amount),
        );
    }

    public function markNoShow(AppointmentService $appointments): void
    {
        $this->act(fn (Appointment $appointment) => $appointments->markNoShow($appointment, $this->user()), 'Gemarkeerd als no-show');
    }

    public function cancelAppointment(AppointmentService $appointments): void
    {
        $this->act(fn (Appointment $appointment) => $appointments->cancel($appointment, $this->user()), 'Afspraak geannuleerd');
    }

    public function undoComplete(AppointmentService $appointments): void
    {
        $this->act(fn (Appointment $appointment) => $appointments->undoComplete($appointment, $this->user()), 'Teruggezet naar gepland');
    }

    /**
     * De gekozen afspraak binnen de huidige zaak; een id van een andere zaak geeft null.
     */
    protected function selectedAppointment(): ?Appointment
    {
        return $this->selectedId === null
            ? null
            : Appointment::query()->with(['customer', 'staffMember'])->find($this->selectedId);
    }

    /**
     * @param  Closure(Appointment): mixed  $action
     */
    private function act(Closure $action, string $success): void
    {
        $appointment = Appointment::query()->findOrFail($this->selectedId);

        try {
            $action($appointment);
        } catch (BookingException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: $success);
        $this->selectedId = null;
    }
}
