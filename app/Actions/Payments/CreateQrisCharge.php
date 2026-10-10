<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Memberi charge QRIS untuk invoice yang masih terbuka (A5): charge `pending` yang masih
 * berlaku dipakai ulang, charge baru hanya dibuat jika sebelumnya `expired`/`failed`.
 *
 * Baris charge dibuat lebih dulu sebelum gateway dipanggil, sehingga setiap percobaan punya
 * `order_id` sendiri (`INV20261000001-1`, `-2`, ...). Jika panggilan gagal, baris itu ditandai
 * `failed` dan percobaan berikutnya tidak memakai ulang `order_id` yang mungkin sudah tercatat
 * di gateway (galat 406 order_id ganda).
 */
final class CreateQrisCharge
{
    public const string GATEWAY = 'midtrans';

    /** Charge yang tersisa kurang dari ini dianggap hampir kedaluwarsa dan dicek ulang ke gateway. */
    private const int MIN_REMAINING_SECONDS = 60;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ProcessGatewayNotification $processNotification,
    ) {}

    /**
     * @throws ValidationException invoice sudah lunas/dibatalkan
     * @throws PaymentGatewayException
     * @throws LockTimeoutException permintaan lain untuk invoice yang sama masih berjalan
     */
    public function handle(Invoice $invoice): PaymentCharge
    {
        // Lock cache (bukan lock baris) karena di dalamnya ada panggilan HTTP ke gateway.
        // Batas lock lebih lama dari dua kali timeout HTTP agar tidak lepas di tengah panggilan.
        return Cache::lock("qris-charge:{$invoice->id}", 60)->block(20, function () use ($invoice): PaymentCharge {
            $invoice = $this->payableInvoice($invoice);

            $reusable = $this->reusablePendingCharge($invoice);

            if ($reusable !== null) {
                return $reusable;
            }

            return $this->createCharge($this->payableInvoice($invoice));
        });
    }

    private function payableInvoice(Invoice $invoice): Invoice
    {
        $invoice = Invoice::query()->findOrFail($invoice->id);

        if (! in_array($invoice->status, InvoiceStatus::outstanding(), true)) {
            throw ValidationException::withMessages([
                'invoice' => $invoice->status === InvoiceStatus::Paid
                    ? 'Tagihan sudah lunas.'
                    : 'Tagihan sudah dibatalkan.',
            ]);
        }

        return $invoice;
    }

    private function reusablePendingCharge(Invoice $invoice): ?PaymentCharge
    {
        $reusable = null;

        foreach ($invoice->paymentCharges()->pending()->orderByDesc('attempt')->get() as $charge) {
            // Tanpa gambar QR tidak ada pelanggan yang bisa membayarnya: proses sebelumnya berhenti
            // sebelum gateway menjawab (lock menjamin tidak ada panggilan yang masih berjalan), atau
            // gateway hanya mengirim qr_string yang tidak bisa ditampilkan halaman tagihan. Jika QR
            // itu tetap dibayar, webhook menerapkannya seperti charge `failed` lain.
            if ($charge->qr_url === null) {
                $charge->update(['status' => PaymentChargeStatus::Failed]);

                continue;
            }

            if ($charge->expires_at !== null && $charge->expires_at->greaterThan(now()->addSeconds(self::MIN_REMAINING_SECONDS))) {
                $reusable ??= $charge;

                continue;
            }

            // Waktunya habis menurut kita, tetapi bisa saja sudah dibayar dan webhook-nya belum
            // masuk; status gateway diterapkan dulu sebelum membuat charge baru.
            $this->processNotification->handle($this->gateway->checkStatus($charge->order_id));

            if ($charge->refresh()->status === PaymentChargeStatus::Pending) {
                $reusable ??= $charge;
            }
        }

        return $reusable;
    }

    private function createCharge(Invoice $invoice): PaymentCharge
    {
        $attempt = (int) $invoice->paymentCharges()->max('attempt') + 1;

        $charge = $invoice->paymentCharges()->create([
            'attempt' => $attempt,
            'gateway' => self::GATEWAY,
            'order_id' => str_replace('/', '', $invoice->number).'-'.$attempt,
            'amount' => $invoice->total,
            'status' => PaymentChargeStatus::Pending,
        ]);

        try {
            $result = $this->gateway->createQrisCharge($invoice, $charge->order_id);
        } catch (Throwable $exception) {
            $charge->update(['status' => PaymentChargeStatus::Failed]);

            throw $exception;
        }

        $charge->update([
            'qr_string' => $result->qrString,
            'qr_url' => $result->qrUrl,
            'status' => $result->status,
            'expires_at' => $result->expiresAt,
            'raw_response' => $result->rawResponse === [] ? null : $result->rawResponse,
        ]);

        return $charge;
    }
}
