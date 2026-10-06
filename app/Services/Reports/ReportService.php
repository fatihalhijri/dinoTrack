<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Data\Reports\AgingBucketTotal;
use App\Data\Reports\CustomerMovement;
use App\Data\Reports\DashboardSummary;
use App\Data\Reports\MonthlyRevenue;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OutstandingAgeBucket;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Laporan dan metrik dashboard. Semua angka diagregasi di database; definisi tiap angka ada
 * di docs/04 "Laporan".
 *
 * Bukan integrasi eksternal (hanya membaca database), sehingga tidak memakai interface.
 */
final class ReportService
{
    public const string DASHBOARD_CACHE_KEY = 'reports.dashboard-summary';

    public const int DASHBOARD_CACHE_SECONDS = 300;

    /** "Jatuh tempo minggu ini" = hari ini sampai H+6. */
    public const int DUE_SOON_DAYS = 7;

    /**
     * Pendapatan 12 bulan dari pembayaran normal, dipisah per metode. Bulan ditentukan `paid_at`
     * (basis kas), sehingga pembayaran yang dicatat mundur masuk ke bulan uangnya diterima.
     *
     * @return list<MonthlyRevenue>
     */
    public function revenueByMonth(int $year): array
    {
        $start = today()->setDate($year, 1, 1);

        $rows = $this->normalPayments($start, $start->addYear())
            ->toBase()
            ->selectRaw('MONTH(paid_at) as month, method, SUM(amount) as amount, COUNT(*) as payment_count')
            ->groupByRaw('MONTH(paid_at), method')
            ->get();

        $months = [];

        foreach (range(1, 12) as $month) {
            $monthRows = $rows->where('month', $month);
            $byMethod = [];

            foreach (PaymentMethod::cases() as $method) {
                $byMethod[$method->value] = (int) ($monthRows->firstWhere('method', $method->value)->amount ?? 0);
            }

            $months[] = new MonthlyRevenue(
                month: $month,
                byMethod: $byMethod,
                total: array_sum($byMethod),
                paymentCount: (int) $monthRows->sum('payment_count'),
            );
        }

        return $months;
    }

    /**
     * Jumlah dan nominal tunggakan per kelompok umur. Ketiga kelompok selalu ada.
     *
     * Database mengagregasi per umur (hari); hasilnya paling banyak satu baris per tanggal jatuh
     * tempo, lalu digabung ke kelompok di PHP agar batas kelompok hanya ada di enum.
     *
     * @return list<AgingBucketTotal>
     */
    public function outstandingAging(?CarbonImmutable $today = null): array
    {
        $today ??= today();

        $rows = Invoice::query()
            ->pastDue($today)
            ->toBase()
            ->selectRaw('DATEDIFF(?, due_at) as age_days, COUNT(*) as invoice_count, SUM(total) as amount', [$today->toDateString()])
            ->groupBy('age_days')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $bucket = OutstandingAgeBucket::forAgeDays((int) $row->age_days)->value;
            $totals[$bucket]['count'] = ($totals[$bucket]['count'] ?? 0) + (int) $row->invoice_count;
            $totals[$bucket]['amount'] = ($totals[$bucket]['amount'] ?? 0) + (int) $row->amount;
        }

        return array_map(fn (OutstandingAgeBucket $bucket): AgingBucketTotal => new AgingBucketTotal(
            bucket: $bucket,
            invoiceCount: $totals[$bucket->value]['count'] ?? 0,
            amount: $totals[$bucket->value]['amount'] ?? 0,
        ), OutstandingAgeBucket::cases());
    }

    /**
     * Daftar tunggakan, paling lama lebih dulu, untuk dipaginasi atau diekspor. Data pelanggan
     * diambil lewat join (bukan eager load) agar bisa diurutkan dan di-stream tanpa N+1.
     *
     * Atribut tambahan: `age_days`, `customer_code`, `customer_name`, `customer_status`.
     *
     * @return Builder<Invoice>
     */
    public function outstandingInvoicesQuery(?CarbonImmutable $today = null): Builder
    {
        $today ??= today();

        return Invoice::query()
            ->pastDue($today)
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->select('invoices.*')
            ->addSelect([
                'customers.code as customer_code',
                'customers.name as customer_name',
                'customers.status as customer_status',
            ])
            ->selectRaw('DATEDIFF(?, invoices.due_at) as age_days', [$today->toDateString()])
            ->orderBy('invoices.due_at')
            ->orderBy('invoices.id');
    }

    /**
     * Pelanggan yang dipasang, berhenti, dan diisolir dalam rentang tanggal (inklusif).
     *
     * Dibaca dari activity_logs karena kolom di customers ditimpa saat status berubah lagi
     * (isolir dibuka, pelanggan berhenti lalu berlangganan lagi).
     */
    public function customerMovement(CarbonImmutable $from, CarbonImmutable $to): CustomerMovement
    {
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('Tanggal awal laporan tidak boleh setelah tanggal akhir.');
        }

        $start = $from->startOfDay();
        $end = $to->addDay()->startOfDay();

        // Mengikuti tanggal pasang (boleh mundur), bukan tanggal dicatat. Tanggal pasang tidak
        // pernah setelah log dicatat, sehingga log sebelum $start pasti di luar rentang.
        $newCustomers = $this->customerLogs('customer.activated')
            ->where('created_at', '>=', $start)
            ->whereBetween('properties->installed_at', [$start->toDateString(), $to->toDateString()]);

        $terminatedCustomers = $this->customerLogs('customer.terminated')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);

        // Isolir manual atas pelanggan yang sudah diisolir otomatis hanya mengganti alasan.
        $isolatedCustomers = $this->customerLogs('customer.isolated')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereNull('properties->previous_isolation_reason');

        return new CustomerMovement(
            from: $from,
            to: $to,
            newCustomers: $this->countDistinctSubjects($newCustomers),
            terminatedCustomers: $this->countDistinctSubjects($terminatedCustomers),
            isolatedCustomers: $this->countDistinctSubjects($isolatedCustomers),
        );
    }

    /**
     * Ringkasan dashboard, di-cache 5 menit. Cache dihapus setiap ada perubahan pembayaran atau
     * invoice (lihat forgetDashboardSummary()); perubahan status pelanggan menunggu cache habis.
     */
    public function dashboardSummary(): DashboardSummary
    {
        /** @var array{revenue_this_month: int, payments_this_month: int, outstanding_amount: int, outstanding_invoices: int, active_customers: int, isolated_customers: int, pending_customers: int, due_this_week_amount: int, due_this_week_invoices: int, payments_needing_review: int, payments_needing_review_amount: int, customers_with_network_error: int, generated_at: string} $values */
        $values = Cache::remember(
            self::DASHBOARD_CACHE_KEY,
            self::DASHBOARD_CACHE_SECONDS,
            fn (): array => $this->computeDashboardSummary(today())->toArray(),
        );

        return DashboardSummary::fromArray($values);
    }

    /**
     * Dihapus setelah transaksi commit: jika dihapus di dalam transaksi, dashboard yang dibuka
     * sebelum commit akan menyimpan angka lama ke cache selama 5 menit.
     */
    public function forgetDashboardSummary(): void
    {
        DB::afterCommit(fn (): bool => Cache::forget(self::DASHBOARD_CACHE_KEY));
    }

    private function computeDashboardSummary(CarbonImmutable $today): DashboardSummary
    {
        $monthStart = $today->startOfMonth();

        $revenue = $this->normalPayments($monthStart, $monthStart->addMonth())
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as payment_count')
            ->first();

        $outstanding = Invoice::query()
            ->pastDue($today)
            ->toBase()
            ->selectRaw('COALESCE(SUM(total), 0) as amount, COUNT(*) as invoice_count')
            ->first();

        $dueSoon = Invoice::query()
            ->whereIn('status', InvoiceStatus::outstanding())
            ->whereBetween('due_at', [$today->toDateString(), $today->addDays(self::DUE_SOON_DAYS - 1)->toDateString()])
            ->toBase()
            ->selectRaw('COALESCE(SUM(total), 0) as amount, COUNT(*) as invoice_count')
            ->first();

        $needsReview = Payment::query()
            ->needsReview()
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as payment_count')
            ->first();

        $customersByStatus = Customer::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as customer_count')
            ->groupBy('status')
            ->pluck('customer_count', 'status');

        return new DashboardSummary(
            revenueThisMonth: (int) ($revenue->amount ?? 0),
            paymentsThisMonth: (int) ($revenue->payment_count ?? 0),
            outstandingAmount: (int) ($outstanding->amount ?? 0),
            outstandingInvoices: (int) ($outstanding->invoice_count ?? 0),
            activeCustomers: (int) $customersByStatus->get(CustomerStatus::Active->value, 0),
            isolatedCustomers: (int) $customersByStatus->get(CustomerStatus::Isolated->value, 0),
            pendingCustomers: (int) $customersByStatus->get(CustomerStatus::Pending->value, 0),
            dueThisWeekAmount: (int) ($dueSoon->amount ?? 0),
            dueThisWeekInvoices: (int) ($dueSoon->invoice_count ?? 0),
            paymentsNeedingReview: (int) ($needsReview->payment_count ?? 0),
            paymentsNeedingReviewAmount: (int) ($needsReview->amount ?? 0),
            customersWithNetworkError: Customer::query()->hasNetworkError()->count(),
            generatedAt: now(),
        );
    }

    /**
     * Pembayaran yang dihitung sebagai pendapatan: hanya yang normal. Pembayaran anomali
     * (`needs_review`/`resolved`) dikembalikan manual sehingga bukan pendapatan.
     *
     * @return Builder<Payment>
     */
    private function normalPayments(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return Payment::query()
            ->where('review_status', PaymentReviewStatus::None)
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $until);
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function customerLogs(string $action): Builder
    {
        return ActivityLog::query()
            ->where('action', $action)
            ->where('subject_type', (new Customer)->getMorphClass());
    }

    /**
     * @param  Builder<ActivityLog>  $logs
     */
    private function countDistinctSubjects(Builder $logs): int
    {
        return (int) $logs->toBase()->selectRaw('COUNT(DISTINCT subject_id) as customer_count')->value('customer_count');
    }
}
