<?php

declare(strict_types=1);

use App\Actions\Customers\ActivateNewCustomer;
use App\Actions\Customers\ReactivateCustomer;
use App\Actions\Customers\TerminateCustomer;
use App\Actions\Invoices\CancelInvoice;
use App\Actions\Network\ActivateCustomer;
use App\Actions\Network\IsolateCustomer;
use App\Actions\Payments\MarkInvoicePaid;
use App\Data\Reports\AgingBucketTotal;
use App\Data\Reports\CustomerMovement;
use App\Data\Reports\MonthlyRevenue;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\OutstandingAgeBucket;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function reports(): ReportService
{
    return app(ReportService::class);
}

/**
 * Pembayaran untuk invoice yang sudah lunas (bukan tunggakan) dengan nominal dan waktu bayar pasti.
 */
function reportPayment(int $amount, string $paidAt, PaymentMethod $method = PaymentMethod::Cash, PaymentReviewStatus $reviewStatus = PaymentReviewStatus::None): Payment
{
    return Payment::factory()->for(Invoice::factory()->paid())->create([
        'amount' => $amount,
        'paid_at' => $paidAt,
        'method' => $method,
        'review_status' => $reviewStatus,
    ]);
}

/**
 * Invoice dengan jatuh tempo dan total pasti, masing-masing pada subscription sendiri.
 */
function reportInvoice(string $dueAt, int $total = 100_000, InvoiceStatus $status = InvoiceStatus::Overdue, ?Customer $customer = null): Invoice
{
    $subscription = Subscription::factory()->for($customer ?? Customer::factory()->active()->create())->create(['price' => $total]);

    return Invoice::factory()->for($subscription)->create([
        'due_at' => $dueAt,
        'subtotal' => $total,
        'total' => $total,
        'status' => $status,
    ]);
}

function movementIn(string $from, string $to): CustomerMovement
{
    return reports()->customerMovement(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

function registerPendingCustomer(string $at): Customer
{
    test()->travelTo($at);

    return Customer::factory()->pending()->withSubscription()->create();
}

function installCustomer(Customer $customer, string $at, ?string $installedAt = null): void
{
    test()->travelTo($at);
    app(ActivateNewCustomer::class)->handle($customer, CarbonImmutable::parse($installedAt ?? $at));
}

function terminateCustomerAt(Customer $customer, string $at): void
{
    test()->travelTo($at);
    app(TerminateCustomer::class)->handle($customer);
}

function isolateManuallyAt(Customer $customer, string $at): void
{
    test()->travelTo($at);
    app(IsolateCustomer::class)->handle($customer, IsolationReason::Manual, today(), note: 'Pelanggaran');
}

describe('pendapatan per bulan', function () {
    it('menjumlah pembayaran per bulan dan per metode untuk 12 bulan', function () {
        $this->travelTo('2026-10-20 10:00');
        reportPayment(150_000, '2026-01-05 09:00', PaymentMethod::Qris);
        reportPayment(100_000, '2026-01-20 14:00', PaymentMethod::Cash);
        reportPayment(50_000, '2026-01-25 19:00', PaymentMethod::Qris);
        reportPayment(200_000, '2026-03-10 08:00', PaymentMethod::Transfer);

        $months = reports()->revenueByMonth(2026);

        expect($months)->toHaveCount(12)
            ->and($months[0])->toEqual(new MonthlyRevenue(1, ['qris' => 200_000, 'cash' => 100_000, 'transfer' => 0], 300_000, 3))
            ->and($months[1])->toEqual(new MonthlyRevenue(2, ['qris' => 0, 'cash' => 0, 'transfer' => 0], 0, 0))
            ->and($months[2])->toEqual(new MonthlyRevenue(3, ['qris' => 0, 'cash' => 0, 'transfer' => 200_000], 200_000, 1));
    });

    it('tidak menghitung pembayaran anomali sebagai pendapatan', function (PaymentReviewStatus $reviewStatus) {
        $this->travelTo('2026-10-20 10:00');
        reportPayment(100_000, '2026-10-05 09:00');
        reportPayment(150_000, '2026-10-06 09:00', PaymentMethod::Qris, $reviewStatus);

        $october = reports()->revenueByMonth(2026)[9];

        expect($october)->toEqual(new MonthlyRevenue(10, ['qris' => 0, 'cash' => 100_000, 'transfer' => 0], 100_000, 1));
    })->with([
        'perlu tinjauan' => [PaymentReviewStatus::NeedsReview],
        'sudah ditinjau' => [PaymentReviewStatus::Resolved],
    ]);

    it('memasukkan pembayaran ke tahun dan bulan sesuai waktu bayar sampai detik terakhir', function () {
        $this->travelTo('2027-01-05 10:00');
        reportPayment(30_000, '2025-12-31 23:59:59');
        reportPayment(50_000, '2026-12-31 23:59:59');
        reportPayment(70_000, '2027-01-01 00:00:00');

        $year2026 = reports()->revenueByMonth(2026);

        expect(array_sum(array_map(fn (MonthlyRevenue $month): int => $month->total, $year2026)))->toBe(50_000)
            ->and($year2026[11]->total)->toBe(50_000)
            ->and(reports()->revenueByMonth(2027)[0]->total)->toBe(70_000);
    });
});

describe('umur tunggakan', function () {
    it('mengelompokkan tunggakan menurut jumlah hari sejak jatuh tempo', function () {
        $this->travelTo('2026-10-20 10:00');
        reportInvoice('2026-10-19', 100_000);
        reportInvoice('2026-10-13', 110_000);
        reportInvoice('2026-10-12', 120_000);
        reportInvoice('2026-09-20', 130_000);
        reportInvoice('2026-09-19', 140_000);

        $aging = reports()->outstandingAging();

        expect($aging)->toEqual([
            new AgingBucketTotal(OutstandingAgeBucket::UpToSevenDays, 2, 210_000),
            new AgingBucketTotal(OutstandingAgeBucket::EightToThirtyDays, 2, 250_000),
            new AgingBucketTotal(OutstandingAgeBucket::OverThirtyDays, 1, 140_000),
        ]);
    });

    it('tidak menghitung tagihan yang belum lewat jatuh tempo, lunas, atau dibatalkan', function () {
        $this->travelTo('2026-10-20 10:00');
        reportInvoice('2026-10-20', status: InvoiceStatus::Unpaid);
        reportInvoice('2026-10-21', status: InvoiceStatus::Unpaid);
        reportInvoice('2026-09-01', status: InvoiceStatus::Paid);
        reportInvoice('2026-09-01', status: InvoiceStatus::Cancelled);

        $aging = reports()->outstandingAging();

        expect(array_map(fn (AgingBucketTotal $bucket): int => $bucket->invoiceCount, $aging))->toBe([0, 0, 0])
            ->and(reports()->outstandingInvoicesQuery()->count())->toBe(0);
    });

    it('menghitung tagihan unpaid yang belum ditandai overdue dan tagihan pelanggan yang sudah berhenti', function () {
        $this->travelTo('2026-10-20 10:00');
        reportInvoice('2026-10-19', 100_000, InvoiceStatus::Unpaid);
        reportInvoice('2026-10-01', 150_000, customer: Customer::factory()->terminated()->create());

        $aging = reports()->outstandingAging();

        expect($aging[0])->toEqual(new AgingBucketTotal(OutstandingAgeBucket::UpToSevenDays, 1, 100_000))
            ->and($aging[1])->toEqual(new AgingBucketTotal(OutstandingAgeBucket::EightToThirtyDays, 1, 150_000));
    });

    it('mengurutkan daftar tunggakan dari yang paling lama beserta umur dan data pelanggan', function () {
        $this->travelTo('2026-10-20 10:00');
        $recent = reportInvoice('2026-10-19');
        $oldest = reportInvoice('2026-09-01', customer: Customer::factory()->terminated()->create(['code' => 'PLG-000007', 'name' => 'Siti Aminah']));
        $middle = reportInvoice('2026-10-10');

        $invoices = reports()->outstandingInvoicesQuery()->get();

        expect($invoices->pluck('id')->all())->toBe([$oldest->id, $middle->id, $recent->id])
            ->and($invoices->pluck('age_days')->map(fn (mixed $days): int => (int) $days)->all())->toBe([49, 10, 1])
            ->and($invoices->first()->only(['customer_code', 'customer_name', 'customer_status']))->toBe([
                'customer_code' => 'PLG-000007',
                'customer_name' => 'Siti Aminah',
                'customer_status' => 'terminated',
            ]);
    });
});

describe('pergerakan pelanggan', function () {
    it('menghitung pelanggan baru menurut tanggal pasang, termasuk pemasangan yang dicatat mundur', function () {
        $backdated = registerPendingCustomer('2026-09-20 09:00');
        $onTime = registerPendingCustomer('2026-10-02 09:00');
        installCustomer($backdated, '2026-10-02 10:00', installedAt: '2026-09-30');
        installCustomer($onTime, '2026-10-02 11:00');

        expect(movementIn('2026-09-01', '2026-09-30')->newCustomers)->toBe(1)
            ->and(movementIn('2026-10-01', '2026-10-31')->newCustomers)->toBe(1);
    });

    it('mencatat pemasangan, berhenti, dan pemasangan ulang pada periodenya masing-masing', function () {
        $customer = registerPendingCustomer('2026-07-01 08:00');
        installCustomer($customer, '2026-07-01 09:00');
        terminateCustomerAt($customer, '2026-08-15 09:00');
        $this->travelTo('2026-09-05 08:00');
        app(ReactivateCustomer::class)->handle($customer, Package::factory()->create()->id, billingDay: 5);
        installCustomer($customer, '2026-09-05 09:00');

        expect(movementIn('2026-07-01', '2026-07-31'))->newCustomers->toBe(1)->terminatedCustomers->toBe(0)
            ->and(movementIn('2026-08-01', '2026-08-31'))->newCustomers->toBe(0)->terminatedCustomers->toBe(1)
            ->and(movementIn('2026-09-01', '2026-09-30'))->newCustomers->toBe(1)->terminatedCustomers->toBe(0);
    });

    it('menghitung pelanggan terisolir sekali dan tidak menghitung perubahan alasan isolir', function () {
        $isolatedTwice = customerOnProfile(Customer::factory()->active());
        $alreadyAutoIsolated = customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue));
        isolateManuallyAt($isolatedTwice, '2026-10-05 09:00');
        app(ActivateCustomer::class)->handle($isolatedTwice, isManual: true, note: 'Sudah diperbaiki');
        isolateManuallyAt($isolatedTwice, '2026-10-06 09:00');
        isolateManuallyAt($alreadyAutoIsolated, '2026-10-07 09:00');

        expect(movementIn('2026-10-01', '2026-10-31')->isolatedCustomers)->toBe(1);
    });

    it('memakai batas awal dan akhir rentang secara inklusif', function () {
        $customers = Customer::factory()->active()->withSubscription()->count(4)->create();
        terminateCustomerAt($customers[0], '2026-09-30 23:59:59');
        terminateCustomerAt($customers[1], '2026-10-01 00:00:00');
        terminateCustomerAt($customers[2], '2026-10-31 23:59:59');
        terminateCustomerAt($customers[3], '2026-11-01 00:00:00');

        expect(movementIn('2026-10-01', '2026-10-31')->terminatedCustomers)->toBe(2);
    });

    it('menolak rentang dengan tanggal awal setelah tanggal akhir', function () {
        movementIn('2026-10-31', '2026-10-01');
    })->throws(InvalidArgumentException::class, 'Tanggal awal laporan tidak boleh setelah tanggal akhir.');
});

describe('ringkasan dashboard', function () {
    it('menghitung pendapatan bulan ini, tunggakan, jatuh tempo minggu ini, dan pembayaran anomali', function () {
        $this->travelTo('2026-10-20 10:00');
        reportPayment(150_000, '2026-10-01 00:00:00');
        reportPayment(100_000, '2026-10-20 08:00:00', PaymentMethod::Qris);
        reportPayment(70_000, '2026-09-30 23:59:59');
        reportPayment(50_000, '2026-10-10 12:00:00', PaymentMethod::Qris, PaymentReviewStatus::NeedsReview);
        reportInvoice('2026-10-19', 120_000);
        reportInvoice('2026-09-01', 80_000);
        reportInvoice('2026-10-20', 60_000, InvoiceStatus::Unpaid);
        reportInvoice('2026-10-26', 40_000, InvoiceStatus::Unpaid);
        reportInvoice('2026-10-27', 30_000, InvoiceStatus::Unpaid);

        $summary = reports()->dashboardSummary();

        expect($summary)
            ->revenueThisMonth->toBe(250_000)
            ->paymentsThisMonth->toBe(2)
            ->outstandingAmount->toBe(200_000)
            ->outstandingInvoices->toBe(2)
            ->dueThisWeekAmount->toBe(100_000)
            ->dueThisWeekInvoices->toBe(2)
            ->paymentsNeedingReview->toBe(1)
            ->paymentsNeedingReviewAmount->toBe(50_000)
            ->generatedAt->toDateTimeString()->toBe('2026-10-20 10:00:00');
    });

    it('menghitung pelanggan per status dan pelanggan dengan galat router', function () {
        Customer::factory()->active()->create();
        Customer::factory()->active()->create(['network_error_at' => now(), 'network_error' => 'Router tidak bisa dijangkau']);
        Customer::factory()->isolated()->create();
        Customer::factory()->pending()->create();
        Customer::factory()->pending()->create()->delete();
        Customer::factory()->terminated()->create();

        $summary = reports()->dashboardSummary();

        expect($summary)
            ->activeCustomers->toBe(2)
            ->isolatedCustomers->toBe(1)
            ->pendingCustomers->toBe(1)
            ->customersWithNetworkError->toBe(1);
    });

    it('memakai angka dari cache selama 5 menit', function () {
        $this->travelTo('2026-10-20 10:00');
        Customer::factory()->active()->create();
        reports()->dashboardSummary();
        Customer::factory()->active()->create();

        $cached = reports()->dashboardSummary();
        $this->travel(ReportService::DASHBOARD_CACHE_SECONDS + 1)->seconds();
        $refreshed = reports()->dashboardSummary();

        expect($cached->activeCustomers)->toBe(1)
            ->and($refreshed->activeCustomers)->toBe(2);
    });

    it('menghitung ulang ringkasan setelah pembayaran tercatat', function () {
        $this->travelTo('2026-10-20 10:00');
        $invoice = reportInvoice('2026-10-25', 150_000, InvoiceStatus::Unpaid);
        reports()->dashboardSummary();

        app(MarkInvoicePaid::class)->handle($invoice, PaymentMethod::Cash, 150_000, now());

        expect(reports()->dashboardSummary())
            ->revenueThisMonth->toBe(150_000)
            ->dueThisWeekInvoices->toBe(0);
    });

    it('menghitung ulang ringkasan setelah tagihan dibatalkan', function () {
        $this->travelTo('2026-10-20 10:00');
        $invoice = reportInvoice('2026-10-10', 120_000);
        reports()->dashboardSummary();

        app(CancelInvoice::class)->handle($invoice, 'Salah input paket');

        expect(reports()->dashboardSummary()->outstandingAmount)->toBe(0);
    });

    it('baru menghapus cache setelah transaksi pembayaran commit', function () {
        $this->travelTo('2026-10-20 10:00');
        $invoice = reportInvoice('2026-10-25', 150_000, InvoiceStatus::Unpaid);
        reports()->dashboardSummary();

        $revenueBeforeCommit = DB::transaction(function () use ($invoice): int {
            app(MarkInvoicePaid::class)->handle($invoice, PaymentMethod::Cash, 150_000, now());

            return reports()->dashboardSummary()->revenueThisMonth;
        });

        expect($revenueBeforeCommit)->toBe(0)
            ->and(reports()->dashboardSummary()->revenueThisMonth)->toBe(150_000);
    });
});
