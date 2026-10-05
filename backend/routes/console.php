<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Jadwal sesuai docs/02-arsitektur.md. Isolir (Tahap 06) dan pengingat (Tahap 07) menyusul.

Schedule::command('billing:generate-invoices')
    ->dailyAt('00:10')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('billing:mark-overdue')
    ->dailyAt('01:00')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('billing:reconcile-payments')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer();
