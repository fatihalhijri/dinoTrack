<?php

declare(strict_types=1);

use App\Actions\Invoices\MarkOverdueInvoices;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

function invoiceWithDueDate(string $dueAt, InvoiceStatus $status = InvoiceStatus::Unpaid): Invoice
{
    return Invoice::factory()->create(['due_at' => $dueAt, 'status' => $status]);
}

it('menandai invoice unpaid yang jatuh temponya sudah lewat sebagai overdue', function () {
    $invoice = invoiceWithDueDate('2026-10-16');

    $marked = app(MarkOverdueInvoices::class)->handle(CarbonImmutable::parse('2026-10-17'));

    expect($marked)->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'invoice.overdue', 'subject_id' => $invoice->id, 'user_id' => null]);
});

it('belum menandai overdue pada hari jatuh tempo', function () {
    $invoice = invoiceWithDueDate('2026-10-17');

    app(MarkOverdueInvoices::class)->handle(CarbonImmutable::parse('2026-10-17'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('tidak menyentuh invoice lunas, dibatalkan, atau yang sudah overdue', function (InvoiceStatus $status) {
    $invoice = invoiceWithDueDate('2026-10-01', $status);

    $marked = app(MarkOverdueInvoices::class)->handle(CarbonImmutable::parse('2026-10-17'));

    expect($marked)->toBe(0)
        ->and($invoice->fresh()->status)->toBe($status);
})->with([InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Overdue]);

it('aman dijalankan ulang dan tidak menambah denda', function () {
    $invoice = invoiceWithDueDate('2026-10-01');
    $total = $invoice->total;

    $first = app(MarkOverdueInvoices::class)->handle(CarbonImmutable::parse('2026-10-17'));
    $second = app(MarkOverdueInvoices::class)->handle(CarbonImmutable::parse('2026-10-18'));

    expect([$first, $second])->toBe([1, 0])
        ->and($invoice->fresh())
        ->penalty->toBe(0)
        ->total->toBe($total);
    expect(ActivityLog::query()->where('action', 'invoice.overdue')->count())->toBe(1);
});
