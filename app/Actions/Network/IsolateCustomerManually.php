<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin mengisolir pelanggan dengan alasan (K8). Perintah router berjalan lewat
 * IsolateCustomerJob; isolir manual hanya bisa dibuka manual oleh admin.
 *
 * Pelanggan yang sedang diisolir otomatis boleh diisolir manual: alasannya berubah menjadi
 * `manual` sehingga pembayaran tidak lagi membuka isolirnya.
 */
final class IsolateCustomerManually
{
    public const int MIN_REASON_LENGTH = 5;

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, string $reason, User $by): void
    {
        $reason = self::validReason($reason);

        DB::transaction(function () use ($customer, $reason, $by): void {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $error = match (true) {
                $customer->status === CustomerStatus::Pending => 'Pelanggan belum terpasang.',
                $customer->status === CustomerStatus::Terminated => 'Pelanggan sudah berhenti berlangganan.',
                $customer->isolation_reason === IsolationReason::Manual => 'Pelanggan sudah diisolir manual.',
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['status' => $error]);
            }

            $this->logger->log('customer.isolation_requested', $customer, $by, ['reason' => $reason]);

            IsolateCustomerJob::dispatch($customer, IsolationReason::Manual, today(), $by, $reason)->afterCommit();
        });
    }

    /**
     * Alasan wajib untuk isolir dan buka isolir manual; juga dipakai ActivateCustomerManually.
     */
    public static function validReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => sprintf('Alasan wajib diisi minimal %d karakter.', self::MIN_REASON_LENGTH),
            ]);
        }

        return $reason;
    }
}
