<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Maak een platformadmin: een bestaand account promoveren of een nieuw account aanmaken (voor /admin).
 */
#[Signature('barbercuts:make-admin {email : E-mailadres van de admin} {--name= : Naam (alleen bij een nieuw account)}')]
#[Description('Maak een gebruiker platformadmin (toegang tot /admin)')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Dat is geen geldig e-mailadres.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $password = (string) $this->secret('Kies een wachtwoord voor het nieuwe account (minimaal 12 tekens)');

            if (strlen($password) < 12) {
                $this->error('Het wachtwoord is te kort.');

                return self::FAILURE;
            }

            $user = new User(['name' => (string) ($this->option('name') ?: 'Admin'), 'email' => $email, 'password' => Hash::make($password)]);
            $user->email_verified_at = now();
        }

        $user->is_admin = true;
        $user->save();

        $this->info("{$email} is nu platformadmin. Bij de eerste keer inloggen op /admin stel je twee-staps-verificatie in.");

        return self::SUCCESS;
    }
}
