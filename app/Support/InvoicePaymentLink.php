<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Facades\URL;

/**
 * Signed URL halaman tagihan publik dan endpoint-nya. Tanpa masa berlaku (A4): pelanggan yang
 * menunggak sering membuka link dari pesan lama, dan halaman itu sendiri menolak pembayaran
 * untuk invoice yang sudah lunas atau dibatalkan. Signature bergantung pada APP_KEY, sehingga
 * mengganti APP_KEY membatalkan semua link yang sudah terkirim.
 */
final class InvoicePaymentLink
{
    public static function for(Invoice $invoice): string
    {
        return URL::signedRoute('public-invoices.show', ['invoice' => $invoice->id]);
    }

    public static function status(Invoice $invoice): string
    {
        return URL::signedRoute('public-invoices.status', ['invoice' => $invoice->id]);
    }

    public static function pay(Invoice $invoice): string
    {
        return URL::signedRoute('public-invoices.pay', ['invoice' => $invoice->id]);
    }
}
