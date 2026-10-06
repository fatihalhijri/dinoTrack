<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Reports\ReportCsvExporter;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

const CSV_BOM = "\xEF\xBB\xBF";

/**
 * Menjalankan satu ekspor ke memori dan mengembalikan isi berkasnya.
 *
 * @param  Closure(resource): void  $export
 */
function csvOutput(Closure $export): string
{
    $stream = fopen('php://memory', 'w+b');
    $export($stream);
    rewind($stream);
    $content = (string) stream_get_contents($stream);
    fclose($stream);

    return $content;
}

/**
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    $lines = explode("\n", rtrim(substr($content, strlen(CSV_BOM)), "\n"));

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), $lines);
}

function exporter(int $chunkSize = ReportCsvExporter::CHUNK_SIZE): ReportCsvExporter
{
    return new ReportCsvExporter(app(ReportService::class), $chunkSize);
}

function csvCustomerInvoice(string $customerName, string $number, string $dueAt = '2026-10-10', InvoiceStatus $status = InvoiceStatus::Paid, int $total = 150_000, string $code = 'PLG-000001'): Invoice
{
    $customer = Customer::factory()->active()->create(['code' => $code, 'name' => $customerName]);

    return Invoice::factory()->for(Subscription::factory()->for($customer)->create(['price' => $total]))->create([
        'number' => $number,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'due_at' => $dueAt,
        'subtotal' => $total,
        'total' => $total,
        'status' => $status,
    ]);
}

function exportPayments(string $from = '2026-10-01', string $to = '2026-10-31', int $chunkSize = ReportCsvExporter::CHUNK_SIZE): string
{
    return csvOutput(fn ($stream) => exporter($chunkSize)->payments($stream, CarbonImmutable::parse($from), CarbonImmutable::parse($to)));
}

it('mengekspor rincian pembayaran dalam rentang tanggal untuk Excel berlocale Indonesia', function () {
    $this->travelTo('2026-10-20 10:00');
    $cashier = User::factory()->create(['name' => 'Kasir Satu']);
    $invoice = csvCustomerInvoice('Budi Santoso', 'INV/2026/10/00001');
    Payment::factory()->for($invoice)->for($cashier, 'receivedBy')->create(['paid_at' => '2026-10-05 09:30:00', 'amount' => 150_000]);
    Payment::factory()->for($invoice)->qris()->needsReview('Invoice sudah lunas sebelumnya')->create([
        'paid_at' => '2026-10-31 23:59:59',
        'amount' => 150_000,
        'reference' => 'trx-777',
    ]);
    Payment::factory()->for($invoice)->create(['paid_at' => '2026-09-30 23:59:59']);
    Payment::factory()->for($invoice)->create(['paid_at' => '2026-11-01 00:00:00']);

    $content = exportPayments();

    expect($content)->toStartWith(CSV_BOM)
        ->and(csvRows($content))->toBe([
            ['Tanggal bayar', 'Nomor invoice', 'Kode pelanggan', 'Nama pelanggan', 'Metode', 'Nominal', 'Referensi', 'Diterima oleh', 'Status tinjauan', 'Catatan tinjauan'],
            ['2026-10-05 09:30:00', 'INV/2026/10/00001', 'PLG-000001', 'Budi Santoso', 'Tunai', '150000', '', 'Kasir Satu', 'Normal', ''],
            ['2026-10-31 23:59:59', 'INV/2026/10/00001', 'PLG-000001', 'Budi Santoso', 'QRIS', '150000', 'trx-777', '', 'Perlu tinjauan', 'Invoice sudah lunas sebelumnya'],
        ]);
});

it('mengekspor semua baris walau melebihi ukuran satu chunk', function () {
    $this->travelTo('2026-10-20 10:00');
    $invoice = csvCustomerInvoice('Budi Santoso', 'INV/2026/10/00001');
    Payment::factory()->for($invoice)->count(5)->sequence(fn ($sequence) => ['paid_at' => '2026-10-0'.($sequence->index + 1).' 08:00:00'])->create();

    $rows = csvRows(exportPayments(chunkSize: 2));

    expect(array_column(array_slice($rows, 1), 0))->toBe([
        '2026-10-01 08:00:00', '2026-10-02 08:00:00', '2026-10-03 08:00:00', '2026-10-04 08:00:00', '2026-10-05 08:00:00',
    ]);
});

it('hanya menulis baris judul jika tidak ada data', function () {
    $this->travelTo('2026-10-20 10:00');

    $rows = csvRows(exportPayments());

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe('Tanggal bayar');
});

it('menulis nama berawalan karakter formula sebagai teks', function (string $name) {
    $this->travelTo('2026-10-20 10:00');
    Payment::factory()->for(csvCustomerInvoice($name, 'INV/2026/10/00001'))->create(['paid_at' => '2026-10-05 09:30:00']);

    $rows = csvRows(exportPayments());

    expect($rows[1][3])->toBe("'".$name);
})->with([
    'sama dengan' => ['=HYPERLINK("http://contoh.test","klik")'],
    'plus' => ['+6281234567890'],
    'minus' => ['-2+3'],
    'at' => ['@SUM(A1:A9)'],
]);

it('mengekspor tunggakan beserta umur dan kelompok umurnya', function () {
    $this->travelTo('2026-10-20 10:00');
    csvCustomerInvoice('Siti Aminah', 'INV/2026/09/00002', dueAt: '2026-09-01', status: InvoiceStatus::Overdue, total: 120_000, code: 'PLG-000002');
    csvCustomerInvoice('Budi Santoso', 'INV/2026/10/00001', dueAt: '2026-10-19', status: InvoiceStatus::Unpaid, total: 150_000);
    csvCustomerInvoice('Lunas', 'INV/2026/10/00003', dueAt: '2026-09-01', status: InvoiceStatus::Paid, code: 'PLG-000003');

    $rows = csvRows(csvOutput(fn ($stream) => exporter()->outstandingInvoices($stream, today())));

    expect($rows)->toBe([
        ['Nomor invoice', 'Kode pelanggan', 'Nama pelanggan', 'Status pelanggan', 'Periode mulai', 'Periode selesai', 'Jatuh tempo', 'Umur (hari)', 'Kelompok umur', 'Total', 'Status invoice'],
        ['INV/2026/09/00002', 'PLG-000002', 'Siti Aminah', 'Aktif', '2026-10-01', '2026-10-31', '2026-09-01', '49', 'Lebih dari 30 hari', '120000', 'Lewat jatuh tempo'],
        ['INV/2026/10/00001', 'PLG-000001', 'Budi Santoso', 'Aktif', '2026-10-01', '2026-10-31', '2026-10-19', '1', '0–7 hari', '150000', 'Belum dibayar'],
    ]);
});

it('mengekspor rekap pendapatan 12 bulan dengan satu kolom per metode', function () {
    $this->travelTo('2026-10-20 10:00');
    $invoice = csvCustomerInvoice('Budi Santoso', 'INV/2026/10/00001');
    Payment::factory()->for($invoice)->create(['paid_at' => '2026-01-05 09:00:00', 'amount' => 100_000, 'method' => PaymentMethod::Cash]);
    Payment::factory()->for($invoice)->create(['paid_at' => '2026-01-06 09:00:00', 'amount' => 150_000, 'method' => PaymentMethod::Qris]);

    $rows = csvRows(csvOutput(fn ($stream) => exporter()->revenueByMonth($stream, 2026)));

    expect($rows)->toHaveCount(13)
        ->and($rows[0])->toBe(['Bulan', 'QRIS', 'Tunai', 'Transfer bank', 'Total', 'Jumlah pembayaran'])
        ->and($rows[1])->toBe(['2026-01', '150000', '100000', '0', '250000', '2'])
        ->and($rows[12])->toBe(['2026-12', '0', '0', '0', '0', '0']);
});

it('mengirim CSV sebagai unduhan berkas yang di-stream', function () {
    $this->travelTo('2026-10-20 10:00');
    $exporter = exporter();

    $response = TestResponse::fromBaseResponse(
        $exporter->download('pendapatan-2026.csv', fn ($stream) => $exporter->revenueByMonth($stream, 2026)),
    );

    $response->assertDownload('pendapatan-2026.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toStartWith(CSV_BOM.'Bulan;QRIS;Tunai;');
});
