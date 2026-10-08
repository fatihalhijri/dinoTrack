<?php

declare(strict_types=1);

use App\Contracts\MessageSender;
use App\Contracts\NetworkController;
use App\Contracts\PaymentGateway;
use App\Data\GatewayNotification;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentCharge;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeMessageSender;
use Tests\Fakes\FakeNetworkController;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Router palsu baru yang dipasang di container (menggantikan bawaan TestCase).
 */
function fakeNetwork(): FakeNetworkController
{
    $network = new FakeNetworkController;
    app()->instance(NetworkController::class, $network);

    return $network;
}

/**
 * Payment gateway palsu baru yang dipasang di container.
 */
function fakeGateway(): FakePaymentGateway
{
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

/**
 * WhatsApp palsu baru yang dipasang di container (menggantikan bawaan TestCase).
 */
function fakeMessages(): FakeMessageSender
{
    $messages = new FakeMessageSender;
    app()->instance(MessageSender::class, $messages);

    return $messages;
}

/**
 * Pelanggan dengan subscription aktif pada paket berprofil PPPoE $profile.
 */
function customerOnProfile(CustomerFactory $factory, string $profile = 'Home-20'): Customer
{
    return $factory->withSubscription(Package::factory()->create(['mikrotik_profile' => $profile]))->create();
}

/**
 * Invoice subscription terakhir pelanggan dengan jatuh tempo tertentu (periode dimulai 7 hari
 * sebelumnya, sehingga jatuh tempo yang berbeda tidak bentrok di unique periode).
 */
function invoiceDueAt(Customer $customer, string $dueAt, InvoiceStatus $status = InvoiceStatus::Overdue): Invoice
{
    $periodStart = CarbonImmutable::parse($dueAt)->subDays(7);

    return Invoice::factory()->for($customer->subscriptions()->latest('id')->firstOrFail())->create([
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodStart->addMonth()->subDay()->toDateString(),
        'issued_at' => $periodStart->toDateString(),
        'due_at' => $dueAt,
        'status' => $status,
    ]);
}

/**
 * User baru dengan role dan permission hasil RolePermissionSeeder.
 */
function userWithRole(Role $role): User
{
    test()->seed(RolePermissionSeeder::class);

    return User::factory()->create()->assignRole($role);
}

/**
 * Dataset role × ability untuk test policy. Setiap ability menyebut role yang boleh;
 * role lain diharapkan ditolak. `viewAny` dan `create` dicek terhadap nama kelas.
 * Target berupa closure agar model dibuat setelah aplikasi siap (bound dataset Pest).
 *
 * @param  class-string<Model>  $modelClass
 * @param  array<string, list<Role>>  $allowedRoles
 * @return array<string, array{string, Closure(): (Model|class-string<Model>), Role, bool}>
 */
function policyMatrix(string $modelClass, array $allowedRoles): array
{
    $dataset = [];

    foreach ($allowedRoles as $ability => $roles) {
        $target = in_array($ability, ['viewAny', 'create'], true)
            ? fn (): string => $modelClass
            : fn (): Model => new $modelClass;

        foreach (Role::cases() as $role) {
            $allowed = in_array($role, $roles, true);
            $dataset[sprintf('%s %s %s', $role->value, $allowed ? 'boleh' : 'tidak boleh', $ability)] = [$ability, $target, $role, $allowed];
        }
    }

    return $dataset;
}

/**
 * Subscription aktif dengan tanggal tagih, tanggal mulai, dan harga yang pasti untuk test tagihan.
 */
function billedSubscription(int $billingDay = 10, string $startsAt = '2026-08-10', int $price = 150_000, ?Customer $customer = null): Subscription
{
    $customer ??= Customer::factory()->active()->create(['installed_at' => $startsAt]);
    $package = Package::factory()->create(['name' => 'Home 20 Mbps', 'price' => $price]);

    return Subscription::factory()->for($customer)->for($package)->create([
        'billing_day' => $billingDay,
        'starts_at' => $startsAt,
        'price' => $price,
    ]);
}

/**
 * Invoice yang sudah ada untuk satu periode subscription.
 *
 * @param  array<string, mixed>  $attributes
 */
function existingInvoice(Subscription $subscription, string $periodStart, string $periodEnd, array $attributes = []): Invoice
{
    return Invoice::factory()->for($subscription)->create([
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        ...$attributes,
    ]);
}

/**
 * Payload notifikasi Midtrans bertanda tangan dengan Server Key test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signedMidtransPayload(array $overrides = [], string $serverKey = 'SB-Mid-server-test'): array
{
    $payload = [
        'order_id' => 'INV20261000001-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-123',
        'fraud_status' => 'accept',
        'settlement_time' => '2026-10-05 10:03:00',
        ...$overrides,
    ];
    $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);

    return $payload;
}

/**
 * Notifikasi gateway yang sudah diurai untuk satu charge; nominal default = nominal charge.
 */
function gatewayNotification(
    PaymentCharge $charge,
    ?PaymentChargeStatus $status = PaymentChargeStatus::Settled,
    ?int $grossAmount = null,
    string $transactionStatus = 'settlement',
    ?string $reference = 'trx-1',
    ?CarbonImmutable $paidAt = null,
): GatewayNotification {
    return new GatewayNotification(
        orderId: $charge->order_id,
        status: $status,
        grossAmount: $grossAmount ?? $charge->amount,
        reference: $reference,
        transactionStatus: $transactionStatus,
        paidAt: $paidAt,
    );
}
