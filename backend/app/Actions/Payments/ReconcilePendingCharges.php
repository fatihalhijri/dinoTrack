<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentChargeStatus;
use App\Models\PaymentCharge;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menanyakan status charge `pending` ke gateway untuk menangkap webhook yang terlewat
 * (server mati, signature salah karena Server Key keliru, notifikasi gagal dikirim).
 *
 * Charge yang baru dibuat dilewati agar tidak mendahului webhook-nya; charge yang terlalu tua
 * dilewati karena QRIS sudah lama kedaluwarsa dan sisanya perlu ditinjau manual.
 */
final class ReconcilePendingCharges
{
    public const int MIN_AGE_MINUTES = 5;

    public const int MAX_AGE_DAYS = 7;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ProcessGatewayNotification $processNotification,
    ) {}

    /**
     * @return array{checked: int, failed: int, failed_charge_ids: list<int>}
     */
    public function handle(): array
    {
        $checked = 0;
        $failedChargeIds = [];

        $charges = PaymentCharge::query()
            ->pending()
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->where('created_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->lazyById(100);

        foreach ($charges as $charge) {
            // Proses yang berhenti sebelum gateway menjawab: tidak ada QR yang bisa dibayar.
            if (! $charge->hasQr()) {
                $charge->update(['status' => PaymentChargeStatus::Failed]);
                $checked++;

                continue;
            }

            try {
                $this->processNotification->handle($this->gateway->checkStatus($charge->order_id));
                $checked++;
            } catch (Throwable $exception) {
                $failedChargeIds[] = $charge->id;

                Log::error('Gagal merekonsiliasi charge pembayaran.', [
                    'payment_charge_id' => $charge->id,
                    'order_id' => $charge->order_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'checked' => $checked,
            'failed' => count($failedChargeIds),
            'failed_charge_ids' => $failedChargeIds,
        ];
    }
}
