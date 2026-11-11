<?php

use Illuminate\Support\Facades\Schedule;

// Platformstatistiek voor het adminpanel: elke nacht, voor gisteren (afgerond) en vandaag (momentopname).
Schedule::command('stats:compute --days=2')->dailyAt('02:30')->timezone('Europe/Amsterdam');
