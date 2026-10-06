<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\ReissueInvoice;
use App\Actions\Invoices\ResendInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoices\CancelInvoiceRequest;
use App\Http\Requests\Invoices\InvoiceIndexRequest;
use App\Http\Requests\Invoices\ReissueInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\MessageLogResource;
use App\Models\Invoice;
use App\Models\Package;
use App\Support\InvoicePaymentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(InvoiceIndexRequest $request): Response
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->applyFilters($request->filters())
            ->latest('issued_at')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => $request->validated(),
            'statuses' => array_map(fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()], InvoiceStatus::cases()),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['customer', 'items', 'payments.receivedBy', 'payments.paymentCharge', 'paymentCharges' => fn ($query) => $query->latest('attempt')]);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceResource::make($invoice),
            'payment_link' => InvoicePaymentLink::for($invoice),
            'messages' => MessageLogResource::collection($invoice->messageLogs()->latest('id')->get()),
            // Paket koreksi untuk terbit ulang (B12), hanya untuk yang boleh menerbitkan ulang.
            'packages' => $request->user()?->can('reissue', $invoice)
                ? Package::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price'])
                    ->map(fn (Package $package): array => ['id' => $package->id, 'name' => $package->name, 'price' => $package->price])->values()->all()
                : null,
        ]);
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice, CancelInvoice $cancelInvoice): RedirectResponse
    {
        $cancelInvoice->handle($invoice, $request->string('reason')->toString(), $request->user());
        $this->toast("Tagihan {$invoice->number} dibatalkan.");

        return to_route('invoices.show', $invoice);
    }

    public function reissue(ReissueInvoiceRequest $request, Invoice $invoice, ReissueInvoice $reissueInvoice): RedirectResponse
    {
        $newInvoice = $reissueInvoice->handle($invoice, $request->correctedPackageId(), $request->user());
        $this->toast("Tagihan {$newInvoice->number} diterbitkan menggantikan {$invoice->number}.");

        return to_route('invoices.show', $newInvoice);
    }

    public function resend(Request $request, Invoice $invoice, ResendInvoice $resendInvoice): RedirectResponse
    {
        Gate::authorize('resend', $invoice);
        $resendInvoice->handle($invoice, $this->actor($request));
        $this->toast("Tagihan {$invoice->number} dijadwalkan untuk dikirim ulang ke WhatsApp pelanggan.");

        return back();
    }
}
