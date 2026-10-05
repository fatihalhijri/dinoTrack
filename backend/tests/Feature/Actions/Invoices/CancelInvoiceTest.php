<?php

declare(strict_types=1);

use App\Actions\Invoices\CancelInvoice;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('membatalkan invoice yang belum dibayar dengan alasan', function (InvoiceStatus $status) {
    $this->freezeSecond();
    $admin = User::factory()->create();
    $invoice = Invoice::factory()->create(['status' => $status]);

    app(CancelInvoice::class)->handle($invoice, '  Salah input paket  ', $admin);

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Cancelled)
        ->cancelled_reason->toBe('Salah input paket')
        ->cancelled_at->equalTo(now())->toBeTrue();

    $log = ActivityLog::query()->where('action', 'invoice.cancelled')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties['previous_status'])->toBe($status->value)
        ->and($log->properties['reason'])->toBe('Salah input paket');
})->with([InvoiceStatus::Unpaid, InvoiceStatus::Overdue]);

it('menolak membatalkan invoice yang sudah lunas atau dibatalkan', function (InvoiceStatus $status, string $message) {
    $invoice = Invoice::factory()->create(['status' => $status]);

    expect(fn () => app(CancelInvoice::class)->handle($invoice, 'Alasan apa pun'))
        ->toThrow(ValidationException::class, $message);

    expect($invoice->fresh()->status)->toBe($status);
})->with([
    'lunas' => [InvoiceStatus::Paid, 'sudah lunas'],
    'dibatalkan' => [InvoiceStatus::Cancelled, 'sudah dibatalkan'],
]);

it('menolak pembatalan tanpa alasan atau dengan alasan terlalu pendek', function (string $reason, string $message) {
    $invoice = Invoice::factory()->create();

    expect(fn () => app(CancelInvoice::class)->handle($invoice, $reason))
        ->toThrow(ValidationException::class, $message);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
})->with([
    'kosong' => ['   ', 'Alasan pembatalan wajib diisi.'],
    'terlalu pendek setelah dipangkas' => ['  abcd  ', 'Alasan pembatalan minimal berisi 5 karakter.'],
]);
