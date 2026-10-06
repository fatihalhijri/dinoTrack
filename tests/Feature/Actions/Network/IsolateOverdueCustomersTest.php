<?php

declare(strict_types=1);

use App\Actions\Network\IsolateOverdueCustomers;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\Queue;

// Hari ini 2026-10-20, toleransi default 3 hari: tunggakan lewat toleransi = due_at sebelum 2026-10-17.
beforeEach(function () {
    Queue::fake([IsolateCustomerJob::class]);
    $this->travelTo('2026-10-20 01:15');
});

it('menjadwalkan isolir otomatis hanya untuk pelanggan aktif yang menunggak lewat toleransi', function () {
    $overdue = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($overdue, '2026-10-16');
    $unpaidPastGrace = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($unpaidPastGrace, '2026-10-10', InvoiceStatus::Unpaid);
    $withinGrace = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($withinGrace, '2026-10-17');
    $paid = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($paid, '2026-10-01', InvoiceStatus::Paid);
    $cancelled = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($cancelled, '2026-10-01', InvoiceStatus::Cancelled);
    foreach ([Customer::factory()->isolated(IsolationReason::Manual), Customer::factory()->terminated()] as $factory) {
        invoiceDueAt(customerOnProfile($factory), '2026-10-01');
    }

    $queued = app(IsolateOverdueCustomers::class)->handle(today());

    expect($queued)->toBe(2);
    Queue::assertPushed(IsolateCustomerJob::class, 2);
    Queue::assertPushed(IsolateCustomerJob::class, fn (IsolateCustomerJob $job) => $job->customer->is($overdue)
        && $job->reason === IsolationReason::Overdue
        && $job->today->toDateString() === '2026-10-20'
        && $job->by === null);
    Queue::assertPushed(IsolateCustomerJob::class, fn (IsolateCustomerJob $job) => $job->customer->is($unpaidPastGrace));
});

it('mengisolir tepat sehari setelah jatuh tempo jika toleransi 0 hari', function () {
    Setting::query()->create(['key' => 'billing.grace_days', 'value' => 0]);
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-19');
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-20');

    expect(app(IsolateOverdueCustomers::class)->handle(today()))->toBe(1);
});

it('tidak menjadwalkan apa pun jika isolir otomatis dimatikan', function () {
    Setting::query()->create(['key' => 'billing.auto_isolate', 'value' => false]);
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-01');

    expect(app(IsolateOverdueCustomers::class)->handle(today()))->toBe(0);

    Queue::assertNothingPushed();
});
