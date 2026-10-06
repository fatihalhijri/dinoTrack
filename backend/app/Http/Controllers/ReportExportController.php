<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ExportPaymentsRequest;
use App\Http\Requests\Reports\ReportRequest;
use App\Services\Reports\ReportCsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan CSV laporan (UTF-8 BOM, pemisah `;`), ditulis per chunk ke stream.
 */
class ReportExportController extends Controller
{
    public function payments(ExportPaymentsRequest $request, ReportCsvExporter $exporter): StreamedResponse
    {
        $from = $request->from();
        $to = $request->to();

        return $exporter->download(
            "pembayaran-{$from->toDateString()}-{$to->toDateString()}.csv",
            fn ($stream) => $exporter->payments($stream, $from, $to),
        );
    }

    public function outstanding(ReportRequest $request, ReportCsvExporter $exporter): StreamedResponse
    {
        $today = today();

        return $exporter->download(
            "tunggakan-{$today->toDateString()}.csv",
            fn ($stream) => $exporter->outstandingInvoices($stream, $today),
        );
    }

    public function revenue(ReportRequest $request, ReportCsvExporter $exporter): StreamedResponse
    {
        $year = $request->year();

        return $exporter->download(
            "pendapatan-{$year}.csv",
            fn ($stream) => $exporter->revenueByMonth($stream, $year),
        );
    }
}
