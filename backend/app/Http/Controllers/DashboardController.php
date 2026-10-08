<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\Permission;
use App\Models\Customer;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan keuangan hanya untuk `reports.view`; role lain melihat jumlah pelanggan per status
 * dan pelanggan dengan galat router (tautan ke daftar pelanggan `network_error=1`).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'summary' => $user?->can(Permission::ReportsView->value) ? $reports->dashboardSummary()->toArray() : null,
            'customer_counts' => $user?->can('viewAny', Customer::class) ? $this->customerCounts() : null,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function customerCounts(): array
    {
        $counts = Customer::query()->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $result = [];

        foreach (CustomerStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $result['network_error'] = Customer::query()->hasNetworkError()->count();

        return $result;
    }
}
