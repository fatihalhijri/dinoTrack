<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Reports\AgingBucketTotal;
use App\Data\Reports\MonthlyRevenue;
use App\Enums\PaymentMethod;
use App\Http\Requests\Reports\ReportRequest;
use App\Http\Resources\OutstandingInvoiceResource;
use App\Services\Reports\ReportService;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan (definisi angka: docs/04 "Laporan"). Ekspor CSV di ReportExportController.
 */
class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportService $reports): Response
    {
        return Inertia::render('reports/index', [
            'year' => $request->year(),
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            'revenue' => array_map(fn (MonthlyRevenue $month): array => $month->toArray(), $reports->revenueByMonth($request->year())),
            'aging' => array_map(fn (AgingBucketTotal $bucket): array => $bucket->toArray(), $reports->outstandingAging()),
            'movement' => $reports->customerMovement($request->from(), $request->to())->toArray(),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
        ]);
    }

    /**
     * Daftar tunggakan, paling lama lebih dulu.
     */
    public function outstanding(ReportRequest $request, ReportService $reports): Response
    {
        $search = $request->searchTerm();
        $invoices = $reports->outstandingInvoicesQuery()
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = SearchTerm::contains((string) $search);
                $query->where('invoices.number', 'like', $pattern)
                    ->orWhere('customers.code', 'like', $pattern)
                    ->orWhere('customers.name', 'like', $pattern);
            }))
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('reports/outstanding', [
            'invoices' => OutstandingInvoiceResource::collection($invoices),
            'filters' => $request->safe()->only(['search', 'per_page']),
        ]);
    }
}
