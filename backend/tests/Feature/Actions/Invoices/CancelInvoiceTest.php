<?php

declare(strict_types=1);

use App\Actions\Invoices\CancelInvoice;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Jobs\ActivateCustomerJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

it('mengaktifkan pelanggan isolir otomatis yang tidak lagi menunggak setelah tagihannya dibatalkan', function () {
    $this->travelTo('2026-10-20 10:00');
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue));
    $invoice = invoiceDueAt($customer, '2026-10-01');

    app(CancelInvoice::class)->handle($invoice, 'Salah input paket', User::factory()->create());

    $network->assertCalled('activate', 1);
    expect($customer->fresh()->status)->toBe(CustomerStatus::Active)
        ->and(ActivityLog::query()->where('action', 'invoice.cancelled')->sole()->properties['activation_queued'])->toBeTrue();
});

it('tidak mengaktifkan pelanggan setelah pembatalan jika masih ada tunggakan lain atau isolirnya manual', function (IsolationReason $reason, bool $hasOtherArrears) {
    $this->travelTo('2026-10-20 10:00');
    Queue::fake([ActivateCustomerJob::class]);
    $customer = customerOnProfile(Customer::factory()->isolated($reason));
    $invoice = invoiceDueAt($customer, '2026-10-01');

    if ($hasOtherArrears) {
        invoiceDueAt($customer, '2026-10-16');
    }

    app(CancelInvoice::class)->handle($invoice, 'Salah input paket', User::factory()->create());

    Queue::assertNotPushed(ActivateCustomerJob::class);
})->with([
    'tunggakan lain lewat toleransi' => [IsolationReason::Overdue, true],
    'isolir manual' => [IsolationReason::Manual, false],
]);

it('tidak menjalankan aktivasi jika transaksi pemanggil pembatalan dibatalkan', function () {
    $this->travelTo('2026-10-20 10:00');
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue));
    $invoice = invoiceDueAt($customer, '2026-10-01');

    try {
        DB::transaction(function () use ($invoice): void {
            app(CancelInvoice::class)->handle($invoice, 'Salah input paket', User::factory()->create());

            throw new RuntimeException('Simulasi galat.');
        });
    } catch (RuntimeException) {
    }

    $network->assertNotCalled('activate');
    expect($customer->fresh()->status)->toBe(CustomerStatus::Isolated);
});
