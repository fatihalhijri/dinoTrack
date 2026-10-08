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
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Support\InvoicePaymentLink;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(InvoiceIndexRequest $request): Response
    {
        $filters = $request->filters();
        $invoices = Invoice::query()
            ->with('customer')
            ->applyFilters($filters)
            ->latest('issued_at')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => $request->validated(),
            'statuses' => array_map(fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()], InvoiceStatus::cases()),
            // Pelanggan yang dipilih lewat `customer_id` (dari detail pelanggan) untuk chip filter.
            'customer' => $filters['customer_id'] === null
                ? null
                : Customer::query()->find($filters['customer_id'], ['id', 'code', 'name'])?->only(['id', 'code', 'name']),
        ]);
    }

    public function show(Request $request, Invoice $invoice, SettingsRepository $settings): Response
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['customer', 'items', 'payments.receivedBy', 'payments.paymentCharge', 'paymentCharges' => fn ($query) => $query->latest('attempt')]);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceResource::make($invoice),
            'payment_link' => InvoicePaymentLink::for($invoice),
            'messages' => MessageLogResource::collection($invoice->messageLogs()->latest('id')->get()),
            // Paket koreksi untuk terbit ulang (B12), hanya untuk yang boleh menerbitkan ulang.
            'packages' => $request->user()?->can('reissue', $invoice) ? $this->correctionPackages() : null,
            'replacement' => $this->replacementOf($invoice),
            // Identitas usaha untuk tampilan cetak.
            'business' => ['name' => $settings->businessName(), 'address' => $settings->businessAddress(), 'whatsapp' => $settings->businessWhatsapp()],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, speed_label: string, price: int}>
     */
    private function correctionPackages(): array
    {
        return Package::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'speed_label', 'price'])
            ->map(fn (Package $package): array => ['id' => $package->id, 'name' => $package->name, 'speed_label' => $package->speed_label, 'price' => $package->price])
            ->values()->all();
    }

    /**
     * Invoice aktif untuk periode yang sama dengan invoice batal ini (hasil terbit ulang). Jika ada,
     * periode itu tidak bisa diterbitkan ulang lagi (ReissueInvoice).
     *
     * @return array{id: int, number: string}|null
     */
    private function replacementOf(Invoice $invoice): ?array
    {
        if ($invoice->status !== InvoiceStatus::Cancelled) {
            return null;
        }

        $replacement = Invoice::query()
            ->where('subscription_id', $invoice->subscription_id)
            ->where('period_start', $invoice->period_start->toDateString())
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->first(['id', 'number']);

        return $replacement === null ? null : ['id' => $replacement->id, 'number' => $replacement->number];
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
