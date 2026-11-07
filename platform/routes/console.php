<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler konteyneri (`php artisan schedule:work`) hər dəqiqə işləyir.
// Worker konteyneri dayanarsa belə outbox və e-mail növbəsi emal olunur (SKIP LOCKED: ikiqat göndərmə yoxdur).
Schedule::command('platform:work --once')->everyMinute()->withoutOverlapping();
Schedule::command('platform:live:generate')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('platform:reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:billing statuses')->everyTenMinutes()->withoutOverlapping();
Schedule::command('platform:billing reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:billing webhooks')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:billing renew')->hourlyAt(17)->withoutOverlapping();
Schedule::command('platform:cleanup')->dailyAt('02:40')->withoutOverlapping();
