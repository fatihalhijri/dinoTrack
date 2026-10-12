<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Enums\CustomerStatus;
use App\Jobs\ActivateCustomerJob;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\IsolationRules;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin membuka isolir (manual maupun otomatis) dengan alasan. Perintah router berjalan lewat
 * ActivateCustomerJob.
 *
 * Mengembalikan true jika pelanggan masih punya tunggakan lewat toleransi dan isolir otomatis
 * menyala: isolir otomatis berikutnya akan mengisolirnya lagi, sehingga admin perlu diberi
 * peringatan. Jika `billing.auto_isolate` dimatikan, peringatan itu tidak berlaku.
 */
final class ActivateCustomerManually
{
    public function __construct(
        private readonly IsolationRules $rules,
        private readonly SettingsRepository $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, string $reason, User $by): bool
    {
        $reason = IsolateCustomerManually::validReason($reason);

        return DB::transaction(function () use ($customer, $reason, $by): bool {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($customer->status !== CustomerStatus::Isolated) {
                throw ValidationException::withMessages(['status' => 'Pelanggan tidak sedang diisolir.']);
            }

            $hasArrearsPastGrace = $this->rules->hasArrearsPastGrace($customer, today());

            $this->logger->log('customer.activation_requested', $customer, $by, [
                'reason' => $reason,
                'isolation_reason' => $customer->isolation_reason?->value,
                'has_arrears_past_grace' => $hasArrearsPastGrace,
            ]);

            ActivateCustomerJob::dispatch($customer, true, $by, $reason)->afterCommit();

            return $hasArrearsPastGrace && $this->settings->autoIsolate();
        });
    }
}
