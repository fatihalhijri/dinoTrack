<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateInvoiceForSubscription;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Jobs\SendInvoiceNotificationJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Subscription;
use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Queue;

function generateInvoiceForPeriod(Subscription $subscription, string $periodStart, string $issuedAt): ?Invoice
{
    $period = BillingPeriod::containing(CarbonImmutable::parse($periodStart), $subscription->billing_day);

    if ($period->start->toDateString() !== $periodStart) {
        $period = BillingPeriod::first(CarbonImmutable::parse($periodStart), $subscription->billing_day);
    }

    return app(GenerateInvoiceForSubscription::class)->handle($subscription, $period, CarbonImmutable::parse($issuedAt));
}

it('menerbitkan invoice periode penuh dengan nomor, jatuh tempo, item, dan log', function () {
    Queue::fake([SendInvoiceNotificationJob::class]);
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10', price: 150_000);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10');

    expect($invoice->fresh())
        ->number->toBe('INV/2026/10/00001')
        ->customer_id->toBe($subscription->customer_id)
        ->period_start->toDateString()->toBe('2026-10-10')
        ->period_end->toDateString()->toBe('2026-11-09')
        ->issued_at->toDateString()->toBe('2026-10-10')
        ->due_at->toDateString()->toBe('2026-10-17')
        ->subtotal->toBe(150_000)
        ->discount->toBe(0)
        ->penalty->toBe(0)
        ->total->toBe(150_000)
        ->status->toBe(InvoiceStatus::Unpaid);

    expect($invoice->items()->sole())
        ->description->toBe('Home 20 Mbps (10 Okt 2026 – 9 Nov 2026)')
        ->quantity->toBe(1)
        ->unit_price->toBe(150_000)
        ->amount->toBe(150_000);

    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'invoice.issued', 'subject_type' => 'invoice', 'subject_id' => $invoice->id, 'user_id' => null]);
    Queue::assertPushed(SendInvoiceNotificationJob::class, fn (SendInvoiceNotificationJob $job) => $job->invoice->is($invoice));
});

it('memakai jumlah hari jatuh tempo dari pengaturan', function () {
    Setting::query()->create(['key' => 'billing.due_days', 'value' => 10]);
    $subscription = billedSubscription(billingDay: 10);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-13');

    expect($invoice->due_at->toDateString())->toBe('2026-10-23');
});

it('menghitung prorata untuk periode pertama yang tidak penuh', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-10-25', price: 150_000);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-25', '2026-10-25');

    expect($invoice->total)->toBe(77_500)
        ->and($invoice->period_end->toDateString())->toBe('2026-11-09')
        ->and($invoice->items()->value('description'))->toBe('Home 20 Mbps (25 Okt 2026 – 9 Nov 2026), prorata 16/31 hari');
});

it('menagih harga penuh untuk periode pertama jika prorata dimatikan', function () {
    Setting::query()->create(['key' => 'billing.prorate_first_month', 'value' => false]);
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-10-25', price: 150_000);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-25', '2026-10-25');

    expect($invoice->total)->toBe(150_000)
        ->and($invoice->period_end->toDateString())->toBe('2026-11-09')
        ->and($invoice->items()->value('description'))->not->toContain('prorata');
});

it('tidak memprorata periode pertama yang dimulai tepat di billing_day', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-10-10', price: 150_000);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10');

    expect($invoice->total)->toBe(150_000)
        ->and($invoice->items()->value('description'))->not->toContain('prorata');
});

it('menerapkan rencana ganti paket lalu mengosongkannya', function (Closure $makeCustomer) {
    $newPackage = Package::factory()->create(['name' => 'Home 50 Mbps', 'price' => 300_000]);
    $subscription = billedSubscription(billingDay: 10, customer: $makeCustomer());
    $subscription->update(['next_package_id' => $newPackage->id]);

    $invoice = generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10');

    expect($subscription->fresh())
        ->package_id->toBe($newPackage->id)
        ->price->toBe(300_000)
        ->next_package_id->toBeNull()
        ->and($invoice->total)->toBe(300_000)
        ->and($invoice->items()->value('description'))->toStartWith('Home 50 Mbps');

    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'subscription.package_applied', 'subject_id' => $subscription->customer_id]);
})->with([
    'active' => [fn () => Customer::factory()->active()->create()],
    'isolated' => [fn () => Customer::factory()->isolated(IsolationReason::Overdue)->create()],
]);

it('tidak menerbitkan invoice kedua untuk periode yang sudah ditagih, termasuk yang dibatalkan', function (InvoiceStatus $status) {
    Queue::fake([SendInvoiceNotificationJob::class]);
    $subscription = billedSubscription(billingDay: 10);
    existingInvoice($subscription, '2026-10-10', '2026-11-09', ['status' => $status]);

    expect(generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10'))->toBeNull()
        ->and(Invoice::query()->count())->toBe(1);
    Queue::assertNotPushed(SendInvoiceNotificationJob::class);
})->with([InvoiceStatus::Unpaid, InvoiceStatus::Paid, InvoiceStatus::Cancelled]);

it('tidak menagih pelanggan yang tidak aktif atau subscription yang sudah berakhir', function (Closure $prepare) {
    $subscription = billedSubscription(billingDay: 10);
    $prepare($subscription);

    expect(generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10'))->toBeNull()
        ->and(Invoice::query()->count())->toBe(0);
})->with([
    'pending' => [fn ($subscription) => $subscription->customer->update(['status' => 'pending'])],
    'terminated' => [fn ($subscription) => $subscription->customer->update(['status' => 'terminated'])],
    'pelanggan dihapus' => [fn ($subscription) => $subscription->customer->delete()],
    'subscription berakhir' => [fn ($subscription) => $subscription->update(['ends_at' => '2026-10-01'])],
]);

it('melempar galat jika yang bentrok bukan periode melainkan nomor invoice', function () {
    $other = billedSubscription(billingDay: 10);
    existingInvoice($other, '2026-10-10', '2026-11-09', ['number' => 'INV/2026/10/00001']);
    $subscription = billedSubscription(billingDay: 10);

    expect(fn () => generateInvoiceForPeriod($subscription, '2026-10-10', '2026-10-10'))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(Invoice::query()->whereBelongsTo($subscription)->exists())->toBeFalse();
});
