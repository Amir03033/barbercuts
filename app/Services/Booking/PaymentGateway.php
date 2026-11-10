<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Payment;

/**
 * Betalingen voor aanbetalingen (§7.8). De implementatie (MolliePaymentGateway via Mollie Connect, zodat het
 * geld naar de rekening van de kapper gaat) volgt in de latere update waarin aanbetalingen worden geactiveerd.
 */
interface PaymentGateway
{
    /**
     * Maak een betaling aan voor de aanbetaling en geef de URL terug waar de klant betaalt.
     */
    public function createPayment(Appointment $appointment, int $amountCents, string $redirectUrl): string;

    public function refund(Payment $payment): void;
}
