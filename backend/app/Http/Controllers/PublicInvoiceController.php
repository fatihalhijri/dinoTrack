<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\CreateQrisCharge;
use App\Enums\InvoiceStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use App\Support\InvoicePaymentLink;
use App\Support\SettingsRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Halaman tagihan publik lewat signed URL (tanpa login, session, dan cookie). Membuka halaman
 * tidak membuat charge QRIS, karena pratinjau link di WhatsApp ikut membuka halaman ini;
 * charge dibuat saat pelanggan menekan tombol bayar.
 */
class PublicInvoiceController extends Controller
{
    public function show(Invoice $invoice, SettingsRepository $settings): Response
    {
        $invoice->load(['customer', 'items']);

        return response()->view('public.invoice', [
            'businessName' => $settings->businessName(),
            'businessWhatsapp' => $settings->businessWhatsapp(),
            'invoice' => $invoice,
            'isPayable' => $this->isPayable($invoice),
            'charge' => $this->chargePayload($this->activeCharge($invoice)),
            'payUrl' => InvoicePaymentLink::pay($invoice),
            'statusUrl' => InvoicePaymentLink::status($invoice),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Status untuk polling halaman; hanya membaca database (perubahan datang dari webhook).
     */
    public function status(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'status' => $invoice->status->value,
            'is_paid' => $invoice->status === InvoiceStatus::Paid,
            'charge' => $this->chargePayload($invoice->paymentCharges()->orderByDesc('attempt')->first()),
        ])->header('Cache-Control', 'no-store');
    }

    public function pay(Invoice $invoice, CreateQrisCharge $createCharge): JsonResponse
    {
        try {
            $charge = $createCharge->handle($invoice);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gagal membuat QRIS dari halaman tagihan publik.', ['invoice_id' => $invoice->id, 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Layanan pembayaran sedang bermasalah. Silakan coba beberapa saat lagi.'], 503);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Permintaan sebelumnya masih diproses. Silakan coba beberapa saat lagi.'], 503);
        }

        return response()->json(['charge' => $this->chargePayload($charge)])->header('Cache-Control', 'no-store');
    }

    private function isPayable(Invoice $invoice): bool
    {
        return in_array($invoice->status, InvoiceStatus::outstanding(), true);
    }

    /**
     * Charge `pending` yang QR-nya masih berlaku, untuk langsung ditampilkan saat halaman dibuka ulang.
     */
    private function activeCharge(Invoice $invoice): ?PaymentCharge
    {
        if (! $this->isPayable($invoice)) {
            return null;
        }

        return $invoice->paymentCharges()
            ->pending()
            ->whereNotNull('qr_url')
            ->where('expires_at', '>', now())
            ->orderByDesc('attempt')
            ->first();
    }

    /**
     * @return array{status: string, qr_url: string|null, expires_at: string|null}|null
     */
    private function chargePayload(?PaymentCharge $charge): ?array
    {
        if ($charge === null) {
            return null;
        }

        return [
            'status' => $charge->status->value,
            'qr_url' => $charge->qr_url,
            'expires_at' => $charge->expires_at?->toIso8601String(),
        ];
    }
}
