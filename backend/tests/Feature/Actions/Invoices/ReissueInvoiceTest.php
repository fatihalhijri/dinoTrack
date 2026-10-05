<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateMonthlyInvoices;
use App\Actions\Invoices\ReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceNotificationJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function cancelledInvoiceFor(Subscription $subscription, string $periodStart = '2026-10-10', string $periodEnd = '2026-11-09'): Invoice
{
    return existingInvoice($subscription, $periodStart, $periodEnd, [
        'status' => InvoiceStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_reason' => 'Salah input paket',
    ]);
}

it('menerbitkan ulang periode yang dibatalkan dengan nomor dan jatuh tempo baru', function () {
    Queue::fake([SendInvoiceNotificationJob::class]);
    $this->travelTo('2026-10-14 10:00');
    $admin = User::factory()->create();
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10', price: 150_000);
    $cancelled = cancelledInvoiceFor($subscription);

    $invoice = app(ReissueInvoice::class)->handle($cancelled, null, $admin);

    expect($invoice->fresh())
        ->number->not->toBe($cancelled->number)
        ->period_start->toDateString()->toBe('2026-10-10')
        ->period_end->toDateString()->toBe('2026-11-09')
        ->issued_at->toDateString()->toBe('2026-10-14')
        ->due_at->toDateString()->toBe('2026-10-21')
        ->total->toBe(150_000)
        ->status->toBe(InvoiceStatus::Unpaid)
        ->and($cancelled->fresh()->status)->toBe(InvoiceStatus::Cancelled);

    $log = ActivityLog::query()->where('action', 'invoice.reissued')->sole();
    expect($log->subject_id)->toBe($invoice->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->properties['replaces_invoice_id'])->toBe($cancelled->id);
    Queue::assertPushed(SendInvoiceNotificationJob::class, fn (SendInvoiceNotificationJob $job) => $job->invoice->is($invoice));
});

it('menghitung ulang prorata untuk periode pertama', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-10-25', price: 150_000);
    $cancelled = cancelledInvoiceFor($subscription, '2026-10-25', '2026-11-09');

    $invoice = app(ReissueInvoice::class)->handle($cancelled);

    expect($invoice->total)->toBe(77_500);
});

it('mengoreksi paket subscription saat diterbitkan ulang karena salah input paket', function () {
    $subscription = billedSubscription(billingDay: 10, price: 150_000);
    $correct = Package::factory()->create(['name' => 'Home 10 Mbps', 'price' => 100_000]);
    $subscription->update(['next_package_id' => $correct->id]);
    $cancelled = cancelledInvoiceFor($subscription);

    $invoice = app(ReissueInvoice::class)->handle($cancelled, $correct->id);

    expect($invoice->total)->toBe(100_000)
        ->and($invoice->items()->value('description'))->toStartWith('Home 10 Mbps')
        ->and($subscription->fresh())
        ->package_id->toBe($correct->id)
        ->price->toBe(100_000)
        ->next_package_id->toBeNull();

    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'subscription.package_corrected', 'subject_id' => $subscription->customer_id]);
});

it('menolak paket koreksi yang sama dengan paket sekarang atau nonaktif', function (Closure $packageId, string $message) {
    $subscription = billedSubscription(billingDay: 10);
    $cancelled = cancelledInvoiceFor($subscription);

    expect(fn () => app(ReissueInvoice::class)->handle($cancelled, $packageId($subscription)))
        ->toThrow(ValidationException::class, $message);

    expect(Invoice::query()->count())->toBe(1);
})->with([
    'sama' => [fn ($subscription) => $subscription->package_id, 'sama dengan paket yang sedang dipakai'],
    'nonaktif' => [fn () => Package::factory()->inactive()->create()->id, 'Paket sudah nonaktif'],
]);

it('menolak menerbitkan ulang invoice yang tidak dibatalkan', function (InvoiceStatus $status) {
    $invoice = existingInvoice(billedSubscription(billingDay: 10), '2026-10-10', '2026-11-09', ['status' => $status]);

    app(ReissueInvoice::class)->handle($invoice);
})->with([InvoiceStatus::Unpaid, InvoiceStatus::Overdue, InvoiceStatus::Paid])
    ->throws(ValidationException::class, 'Hanya tagihan yang dibatalkan');

it('menolak penerbitan ulang kedua untuk periode yang sudah punya tagihan aktif', function () {
    $subscription = billedSubscription(billingDay: 10);
    $cancelled = cancelledInvoiceFor($subscription);
    app(ReissueInvoice::class)->handle($cancelled);

    expect(fn () => app(ReissueInvoice::class)->handle($cancelled))
        ->toThrow(ValidationException::class, 'sudah punya tagihan aktif');

    expect(Invoice::query()->whereBelongsTo($subscription)->count())->toBe(2);
});

it('boleh menerbitkan ulang untuk pelanggan yang sudah berhenti', function () {
    $subscription = billedSubscription(billingDay: 10, customer: Customer::factory()->terminated()->create());
    $subscription->update(['ends_at' => '2026-10-20']);
    $cancelled = cancelledInvoiceFor($subscription);

    $invoice = app(ReissueInvoice::class)->handle($cancelled);

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);
});

it('tidak menagih otomatis periode yang dibatalkan saat generator berjalan', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10');
    existingInvoice($subscription, '2026-09-10', '2026-10-09');
    cancelledInvoiceFor($subscription);
    $this->travelTo('2026-10-15 00:10');

    app(GenerateMonthlyInvoices::class)->handle(today());

    expect(Invoice::query()->whereBelongsTo($subscription)->where('period_start', '2026-10-10')->count())->toBe(1);
});
