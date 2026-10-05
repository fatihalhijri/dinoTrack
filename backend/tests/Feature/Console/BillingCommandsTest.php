<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('menerbitkan tagihan untuk tanggal simulasi', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10');
    existingInvoice($subscription, '2026-09-10', '2026-10-09');

    $this->artisan('billing:generate-invoices', ['--date' => '2026-10-10'])
        ->expectsOutputToContain('Tagihan 2026-10-10: 1 dibuat, 0 dilewati, 0 gagal.')
        ->assertSuccessful();

    expect(Invoice::query()->latest('id')->first()->issued_at->toDateString())->toBe('2026-10-10');
});

it('memakai hari ini jika --date tidak diisi', function () {
    $this->travelTo('2026-10-10 00:10');
    billedSubscription(billingDay: 10, startsAt: '2026-10-10');

    $this->artisan('billing:generate-invoices')
        ->expectsOutputToContain('Tagihan 2026-10-10: 1 dibuat')
        ->assertSuccessful();
});

it('mengembalikan kode gagal jika ada subscription yang gagal ditagih', function () {
    $subscription = billedSubscription(billingDay: 10);
    Invoice::creating(fn () => throw new RuntimeException('Simulasi galat.'));

    $this->artisan('billing:generate-invoices', ['--date' => '2026-10-10'])
        ->expectsOutputToContain('1 gagal')
        ->expectsOutputToContain("Subscription gagal (detail di log): {$subscription->id}")
        ->assertFailed();
});

it('menandai overdue untuk tanggal simulasi', function () {
    $invoice = Invoice::factory()->create(['due_at' => '2026-10-16']);

    $this->artisan('billing:mark-overdue', ['--date' => '2026-10-17'])
        ->expectsOutputToContain('Tagihan 2026-10-17: 1 ditandai overdue.')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('menolak format --date yang salah', function (string $command, string $date) {
    $this->artisan($command, ['--date' => $date])
        ->expectsOutputToContain('Format --date harus YYYY-MM-DD')
        ->assertExitCode(Command::INVALID);
})->with([
    ['billing:generate-invoices', '10-10-2026'],
    ['billing:generate-invoices', '2026-02-30'],
    ['billing:mark-overdue', 'besok'],
]);

it('menolak --date di production', function (string $command) {
    $this->app['env'] = 'production';

    $this->artisan($command, ['--date' => '2026-10-10'])
        ->expectsOutputToContain('tidak boleh dipakai di production')
        ->assertExitCode(Command::INVALID);
})->with(['billing:generate-invoices', 'billing:mark-overdue']);

it('menjadwalkan command tagihan sesuai docs/02 tanpa tumpang tindih dan di satu server', function (string $command, string $expression) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression)
        ->and($event->withoutOverlapping)->toBeTrue()
        // Lock 24 jam bawaan bisa membuat jadwal esok hari terlewat jika proses sebelumnya mati.
        ->and($event->expiresAt)->toBe(60)
        ->and($event->onOneServer)->toBeTrue();
})->with([
    'generate tagihan 00:10' => ['billing:generate-invoices', '10 0 * * *'],
    'tandai overdue 01:00' => ['billing:mark-overdue', '0 1 * * *'],
]);
