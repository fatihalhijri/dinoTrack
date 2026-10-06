<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Jadwal sesuai docs/02-arsitektur.md.

Schedule::command('billing:generate-invoices')
    ->dailyAt('00:10')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('billing:mark-overdue')
    ->dailyAt('01:00')
    ->withoutOverlapping(60)
    ->onOneServer();

// Setelah mark-overdue agar status invoice sudah terbarui saat isolir dinilai.
Schedule::command('billing:isolate-overdue')
    ->dailyAt('01:15')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('billing:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('billing:reconcile-payments')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer();
