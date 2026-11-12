<?php

use Illuminate\Support\Facades\Schedule;

// Platformstatistiek voor het adminpanel: elke nacht, voor gisteren (afgerond) en vandaag (momentopname).
Schedule::command('stats:compute --days=2')->dailyAt('02:30')->timezone('Europe/Amsterdam');

// Herinneringen en andere geplande klantberichten (§7.6): elke minuut, nooit twee runs tegelijk.
Schedule::command('notifications:send-due')->everyMinute()->withoutOverlapping(5);

// Demozaken van pitchers (fase 6): elk uur opruimen wat verlopen is.
Schedule::command('demo:cleanup')->hourly();

// Fase 8: klanten terughalen (elke ochtend) en de wachtlijst (elke minuut).
Schedule::command('winback:send')->dailyAt('10:30')->timezone('Europe/Amsterdam');
Schedule::command('waitlist:advance')->everyMinute()->withoutOverlapping(5);
