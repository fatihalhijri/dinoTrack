<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\PaymentCharge;
use App\Models\Setting;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakePaymentGateway;

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
})->with(['billing:generate-invoices', 'billing:mark-overdue', 'billing:isolate-overdue', 'billing:send-reminders']);

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
    'isolir 01:15 setelah overdue' => ['billing:isolate-overdue', '15 1 * * *'],
    'pengingat 09:00' => ['billing:send-reminders', '0 9 * * *'],
    'rekonsiliasi pembayaran tiap jam' => ['billing:reconcile-payments', '0 * * * *'],
]);

it('menerapkan status gateway untuk charge pending yang webhook-nya terlewat', function () {
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $settled = PaymentCharge::factory()->create(['created_at' => now()->subMinutes(10)]);
    $expired = PaymentCharge::factory()->create(['created_at' => now()->subHours(2)]);
    $gateway->respondWith(gatewayNotification($settled));
    $gateway->respondWith(gatewayNotification($expired, PaymentChargeStatus::Expired, transactionStatus: 'expire'));

    $this->artisan('billing:reconcile-payments')
        ->expectsOutputToContain('Rekonsiliasi pembayaran: 2 charge dicek, 0 gagal.')
        ->assertSuccessful();

    expect($settled->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($expired->fresh()->status)->toBe(PaymentChargeStatus::Expired);
});

it('melewati charge yang terlalu baru, terlalu lama, atau sudah selesai', function () {
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    PaymentCharge::factory()->create(['created_at' => now()->subMinutes(2)]);
    PaymentCharge::factory()->create(['created_at' => now()->subDays(8)]);
    PaymentCharge::factory()->settled()->create(['created_at' => now()->subHour()]);
    PaymentCharge::factory()->expired()->create(['created_at' => now()->subHour()]);

    $this->artisan('billing:reconcile-payments')
        ->expectsOutputToContain('0 charge dicek, 0 gagal.')
        ->assertSuccessful();

    $gateway->assertNothingCalled();
});

it('menggagalkan charge tanpa QR tanpa bertanya ke gateway', function () {
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    $abandoned = PaymentCharge::factory()->create(['qr_string' => null, 'qr_url' => null, 'created_at' => now()->subHour()]);

    $this->artisan('billing:reconcile-payments')->assertSuccessful();

    expect($abandoned->fresh()->status)->toBe(PaymentChargeStatus::Failed);
    $gateway->assertNothingCalled();
});

it('melanjutkan rekonsiliasi dan keluar dengan kode gagal jika satu charge galat', function () {
    $gateway = (new FakePaymentGateway)->failTimes(1, new PaymentGatewayException('Midtrans tidak bisa dihubungi.'));
    $this->app->instance(PaymentGateway::class, $gateway);
    $failing = PaymentCharge::factory()->create(['created_at' => now()->subHour()]);
    $next = PaymentCharge::factory()->create(['created_at' => now()->subHour()]);
    $gateway->respondWith(gatewayNotification($next));

    $this->artisan('billing:reconcile-payments')
        ->expectsOutputToContain('1 charge dicek, 1 gagal.')
        ->expectsOutputToContain("Charge gagal (detail di log): {$failing->id}")
        ->assertFailed();

    expect($next->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('menjadwalkan isolir pelanggan yang menunggak lewat toleransi pada tanggal simulasi', function () {
    Queue::fake([IsolateCustomerJob::class]);
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-16');

    $this->artisan('billing:isolate-overdue', ['--date' => '2026-10-20'])
        ->expectsOutputToContain('Isolir 2026-10-20: 1 pelanggan dijadwalkan.')
        ->assertSuccessful();

    Queue::assertPushed(IsolateCustomerJob::class, fn (IsolateCustomerJob $job) => $job->today->toDateString() === '2026-10-20');
});

it('memberi tahu bahwa isolir otomatis dimatikan', function () {
    Queue::fake([IsolateCustomerJob::class]);
    Setting::query()->create(['key' => 'billing.auto_isolate', 'value' => false]);

    $this->artisan('billing:isolate-overdue')
        ->expectsOutputToContain('Isolir otomatis dimatikan')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('menjadwalkan pengingat untuk tanggal simulasi', function () {
    $this->seed(MessageTemplateSeeder::class);
    invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', InvoiceStatus::Unpaid);

    $this->artisan('billing:send-reminders', ['--date' => '2026-10-09'])
        ->expectsOutputToContain('Pengingat 2026-10-09: 1 dijadwalkan, 0 dilewati, 0 gagal.')
        ->assertSuccessful();
});

it('keluar dengan kode gagal dan menyebut invoice yang pengingatnya gagal dijadwalkan', function () {
    $this->seed(MessageTemplateSeeder::class);
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-09', InvoiceStatus::Unpaid);
    MessageLog::creating(fn () => throw new RuntimeException('Simulasi galat.'));

    $this->artisan('billing:send-reminders', ['--date' => '2026-10-09'])
        ->expectsOutputToContain("Invoice gagal (detail di log): {$invoice->id}")
        ->assertFailed();
});
