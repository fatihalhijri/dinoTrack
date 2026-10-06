<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\CustomerStatus;
use App\Enums\OutstandingAgeBucket;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor laporan ke CSV yang ditulis baris demi baris ke stream, sehingga data besar tidak
 * dimuat sekaligus ke memori.
 *
 * Format untuk Excel berlocale Indonesia: UTF-8 dengan BOM, pemisah `;`. Nominal ditulis
 * sebagai integer rupiah tanpa "Rp" agar bisa dijumlah, tanggal `Y-m-d`.
 */
final class ReportCsvExporter
{
    public const string DELIMITER = ';';

    public const int CHUNK_SIZE = 1000;

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /** Awalan yang membuat spreadsheet menjalankan sel sebagai formula (CSV injection). */
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  int  $chunkSize  jumlah baris per query; diperkecil di test agar batas chunk teruji
     */
    public function __construct(
        private readonly ReportService $reports,
        private readonly int $chunkSize = self::CHUNK_SIZE,
    ) {}

    /**
     * @param  Closure(resource): void  $write  salah satu method ekspor di kelas ini
     */
    public function download(string $filename, Closure $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            $write($output);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Rincian semua pembayaran dalam rentang tanggal bayar (inklusif), termasuk pembayaran
     * anomali yang dibedakan lewat kolom status tinjauan. Diurutkan menurut ID (urutan dicatat).
     *
     * @param  resource  $stream
     */
    public function payments($stream, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->writeHeader($stream, [
            'Tanggal bayar', 'Nomor invoice', 'Kode pelanggan', 'Nama pelanggan', 'Metode', 'Nominal',
            'Referensi', 'Diterima oleh', 'Status tinjauan', 'Catatan tinjauan',
        ]);

        $payments = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('users', 'users.id', '=', 'payments.received_by')
            ->where('payments.paid_at', '>=', $from->startOfDay())
            ->where('payments.paid_at', '<', $to->addDay()->startOfDay())
            ->select('payments.*')
            ->addSelect([
                'invoices.number as invoice_number',
                'customers.code as customer_code',
                'customers.name as customer_name',
                'users.name as received_by_name',
            ]);

        foreach ($payments->lazyById($this->chunkSize, 'payments.id', 'id') as $payment) {
            /** @var Payment $payment */
            $this->writeRow($stream, [
                $payment->paid_at->format('Y-m-d H:i:s'),
                $payment->getAttribute('invoice_number'),
                $payment->getAttribute('customer_code'),
                $payment->getAttribute('customer_name'),
                $payment->method->label(),
                $payment->amount,
                $payment->reference,
                $payment->getAttribute('received_by_name'),
                $payment->review_status->label(),
                $payment->review_status === PaymentReviewStatus::None ? null : $payment->review_note,
            ]);
        }
    }

    /**
     * Daftar tunggakan per hari $today. Diurutkan menurut ID invoice (urutan terbit).
     *
     * @param  resource  $stream
     */
    public function outstandingInvoices($stream, ?CarbonImmutable $today = null): void
    {
        $this->writeHeader($stream, [
            'Nomor invoice', 'Kode pelanggan', 'Nama pelanggan', 'Status pelanggan', 'Periode mulai',
            'Periode selesai', 'Jatuh tempo', 'Umur (hari)', 'Kelompok umur', 'Total', 'Status invoice',
        ]);

        // Paginasi menurut ID agar aman jika ada invoice yang lunas selama ekspor berjalan.
        $invoices = $this->reports->outstandingInvoicesQuery($today)->reorder();

        foreach ($invoices->lazyById($this->chunkSize, 'invoices.id', 'id') as $invoice) {
            /** @var Invoice $invoice */
            $ageDays = (int) $invoice->getAttribute('age_days');

            $this->writeRow($stream, [
                $invoice->number,
                $invoice->getAttribute('customer_code'),
                $invoice->getAttribute('customer_name'),
                CustomerStatus::from((string) $invoice->getAttribute('customer_status'))->label(),
                $invoice->period_start->toDateString(),
                $invoice->period_end->toDateString(),
                $invoice->due_at->toDateString(),
                $ageDays,
                OutstandingAgeBucket::forAgeDays($ageDays)->label(),
                $invoice->total,
                $invoice->status->label(),
            ]);
        }
    }

    /**
     * Rekap pendapatan 12 bulan, satu kolom per metode pembayaran.
     *
     * @param  resource  $stream
     */
    public function revenueByMonth($stream, int $year): void
    {
        $this->writeHeader($stream, [
            'Bulan',
            ...array_map(fn (PaymentMethod $method): string => $method->label(), PaymentMethod::cases()),
            'Total',
            'Jumlah pembayaran',
        ]);

        foreach ($this->reports->revenueByMonth($year) as $month) {
            $this->writeRow($stream, [
                sprintf('%04d-%02d', $year, $month->month),
                ...array_values($month->byMethod),
                $month->total,
                $month->paymentCount,
            ]);
        }
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $columns
     */
    private function writeHeader($stream, array $columns): void
    {
        fwrite($stream, self::UTF8_BOM);
        $this->writeRow($stream, $columns);
    }

    /**
     * @param  resource  $stream
     * @param  list<mixed>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        fputcsv($stream, array_map($this->sanitizeCell(...), $cells), self::DELIMITER, '"', '');
    }

    /**
     * Teks dari input pengguna (nama pelanggan, catatan) yang diawali karakter formula diberi
     * awalan `'` agar ditampilkan sebagai teks, bukan dijalankan spreadsheet.
     */
    private function sanitizeCell(mixed $value): string|int|null
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        $text = is_scalar($value) ? (string) $value : '';

        if ($text !== '' && in_array($text[0], self::FORMULA_PREFIXES, true)) {
            return "'".$text;
        }

        return $text;
    }
}
