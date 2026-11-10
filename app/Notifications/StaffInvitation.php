<?php

namespace App\Notifications;

use App\Models\Barbershop;
use App\Models\StaffMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Uitnodiging voor een kapper om een account te maken bij een zaak (§8). De link is 7 dagen geldig.
 */
class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(private StaffMember $staffMember, private Barbershop $shop, private string $inviterName) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return URL::temporarySignedRoute('invitation.accept', now()->addDays(7), [
            'staffMember' => $this->staffMember->id,
            'email' => $this->staffMember->invited_email,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Je bent uitgenodigd bij {$this->shop->name}")
            ->greeting("Hoi {$this->staffMember->name},")
            ->line("{$this->inviterName} heeft je uitgenodigd als kapper bij {$this->shop->name} op BarberCuts.")
            ->line('Maak je account aan om je eigen agenda, klanten en omzet te zien.')
            ->action('Uitnodiging accepteren', $this->url())
            ->line('Deze link is 7 dagen geldig.');
    }
}
