<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateMonthlyInvoices;
use App\Enums\IsolationReason;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;

function runMonthlyInvoiceGenerator(): array
{
    return app(GenerateMonthlyInvoices::class)->handle(today());
}

/**
 * @return list<string>
 */
function billedPeriodsOf(Subscription $subscription): array
{
    return Invoice::query()
        ->whereBelongsTo($subscription)
        ->orderBy('period_start')
        ->get()
        ->map(fn (Invoice $invoice) => $invoice->period_start->toDateString().'..'.$invoice->period_end->toDateString())
        ->all();
}

it('menerbitkan tagihan periode berikutnya tepat pada billing_day', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10');
    existingInvoice($subscription, '2026-09-10', '2026-10-09');

    $this->travelTo('2026-10-09 00:10');
    expect(runMonthlyInvoiceGenerator()['created'])->toBe(0);

    $this->travelTo('2026-10-10 00:10');
    expect(runMonthlyInvoiceGenerator())->toBe(['created' => 1, 'skipped' => 0, 'failed' => 0, 'failed_subscription_ids' => []])
        ->and(billedPeriodsOf($subscription))->toBe(['2026-09-10..2026-10-09', '2026-10-10..2026-11-09'])
        ->and(Invoice::query()->latest('id')->first())
        ->issued_at->toDateString()->toBe('2026-10-10')
        ->due_at->toDateString()->toBe('2026-10-17');
});

it('tidak membuat duplikat saat dijalankan dua kali di hari yang sama', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10');
    existingInvoice($subscription, '2026-09-10', '2026-10-09');
    $this->travelTo('2026-10-10 00:10');

    runMonthlyInvoiceGenerator();
    $second = runMonthlyInvoiceGenerator();

    expect($second['created'])->toBe(0)
        ->and(Invoice::query()->whereBelongsTo($subscription)->count())->toBe(2);
});

it('menagih periode yang terlewat setelah server mati beberapa hari', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-07-10');
    existingInvoice($subscription, '2026-08-10', '2026-09-09');
    $this->travelTo('2026-10-13 00:10');

    expect(runMonthlyInvoiceGenerator()['created'])->toBe(2)
        ->and(billedPeriodsOf($subscription))->toBe([
            '2026-08-10..2026-09-09',
            '2026-09-10..2026-10-09',
            '2026-10-10..2026-11-09',
        ]);

    // Invoice catch-up terbit hari ini sehingga pelanggan tetap mendapat jatuh tempo penuh.
    expect(Invoice::query()->whereBelongsTo($subscription)->latest('period_start')->first())
        ->issued_at->toDateString()->toBe('2026-10-13')
        ->due_at->toDateString()->toBe('2026-10-20');
});

it('menangani billing_day 28 di Februari biasa dan kabisat', function (string $lastStart, string $lastEnd, string $today, string $expected) {
    $subscription = billedSubscription(billingDay: 28, startsAt: '2026-06-28');
    existingInvoice($subscription, $lastStart, $lastEnd);
    $this->travelTo($today.' 00:10');

    runMonthlyInvoiceGenerator();

    expect(billedPeriodsOf($subscription))->toContain($expected);
})->with([
    'Februari 2027' => ['2027-01-28', '2027-02-27', '2027-02-28', '2027-02-28..2027-03-27'],
    'Februari kabisat 2028' => ['2028-01-28', '2028-02-27', '2028-02-28', '2028-02-28..2028-03-27'],
]);

it('tidak menagih di tanggal 29–31 untuk billing_day 28 yang sudah ditagih', function () {
    $subscription = billedSubscription(billingDay: 28, startsAt: '2026-06-28');
    existingInvoice($subscription, '2027-01-28', '2027-02-27');

    foreach (['2027-01-29', '2027-01-30', '2027-01-31', '2027-02-27'] as $date) {
        $this->travelTo($date.' 00:10');
        expect(runMonthlyInvoiceGenerator()['created'])->toBe(0);
    }
});

it('menangani billing_day 1 di pergantian bulan dan tahun', function () {
    $subscription = billedSubscription(billingDay: 1, startsAt: '2026-06-01');
    existingInvoice($subscription, '2026-12-01', '2026-12-31');
    $this->travelTo('2027-01-01 00:10');

    runMonthlyInvoiceGenerator();

    expect(billedPeriodsOf($subscription))->toContain('2027-01-01..2027-01-31')
        ->and(Invoice::query()->latest('id')->value('number'))->toBe('INV/2027/01/00001');
});

it('tidak menagih pelanggan terminated, pending, atau yang dihapus', function (Closure $makeCustomer) {
    $subscription = billedSubscription(billingDay: 10, customer: $makeCustomer());
    $this->travelTo('2026-10-10 00:10');

    runMonthlyInvoiceGenerator();

    expect(Invoice::query()->whereBelongsTo($subscription)->count())->toBe(0);
})->with([
    'terminated' => [fn () => Customer::factory()->terminated()->create()],
    'pending' => [fn () => Customer::factory()->pending()->create()],
    'dihapus' => [fn () => tap(Customer::factory()->active()->create())->delete()],
]);

it('tidak menagih subscription yang sudah berakhir atau belum dimulai', function () {
    $ended = billedSubscription(billingDay: 10);
    $ended->update(['ends_at' => '2026-09-30']);
    $notStarted = billedSubscription(billingDay: 10);
    $notStarted->update(['starts_at' => null]);
    $this->travelTo('2026-10-10 00:10');

    runMonthlyInvoiceGenerator();

    expect(Invoice::query()->count())->toBe(0);
});

it('tetap menagih pelanggan yang sedang diisolir', function () {
    $subscription = billedSubscription(billingDay: 10, customer: Customer::factory()->isolated(IsolationReason::Overdue)->create());
    $this->travelTo('2026-10-10 00:10');

    runMonthlyInvoiceGenerator();

    expect(Invoice::query()->whereBelongsTo($subscription)->count())->toBe(1);
});

it('hanya menagih periode berjalan untuk subscription lama yang belum punya invoice', function () {
    $subscription = billedSubscription(billingDay: 10, startsAt: '2025-11-03');
    $this->travelTo('2026-10-15 00:10');

    runMonthlyInvoiceGenerator();

    expect(billedPeriodsOf($subscription))->toBe(['2026-10-10..2026-11-09']);
});

it('tetap memproses subscription lain jika satu subscription gagal', function () {
    $failing = billedSubscription(billingDay: 10);
    $healthy = billedSubscription(billingDay: 10);
    Invoice::creating(function (Invoice $invoice) use ($failing): void {
        if ($invoice->subscription_id === $failing->id) {
            throw new RuntimeException('Simulasi galat.');
        }
    });
    $this->travelTo('2026-10-10 00:10');

    expect(runMonthlyInvoiceGenerator())->toBe(['created' => 1, 'skipped' => 0, 'failed' => 1, 'failed_subscription_ids' => [$failing->id]])
        ->and(Invoice::query()->whereBelongsTo($healthy)->count())->toBe(1)
        ->and(Invoice::query()->whereBelongsTo($failing)->count())->toBe(0);
});
