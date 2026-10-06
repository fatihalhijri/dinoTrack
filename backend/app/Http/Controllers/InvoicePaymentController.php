<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\RecordManualPayment;
use App\Http\Requests\Payments\RecordManualPaymentRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

/**
 * Pembayaran manual (tunai/transfer) oleh kasir untuk satu invoice.
 */
class InvoicePaymentController extends Controller
{
    public function store(RecordManualPaymentRequest $request, Invoice $invoice, RecordManualPayment $recordPayment): RedirectResponse
    {
        $recordPayment->handle(
            $invoice,
            $request->paymentMethod(),
            $request->amount(),
            $this->actor($request),
            $request->paidAt(),
            $request->notes(),
        );
        $this->toast("Pembayaran tagihan {$invoice->number} dicatat.");

        return to_route('invoices.show', $invoice);
    }
}
