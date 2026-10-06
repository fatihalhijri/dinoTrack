<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaymentNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $gateway
 * @property string $order_id
 * @property string $transaction_status
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['gateway', 'order_id', 'transaction_status', 'payload', 'signature_valid', 'processed_at'])]
class PaymentNotification extends Model
{
    /** @use HasFactory<PaymentNotificationFactory> */
    use HasFactory, MassPrunable;

    /** Notifikasi dengan signature salah hanya berguna untuk menyelidiki serangan atau salah konfigurasi. */
    public const int INVALID_RETENTION_DAYS = 30;

    /** Notifikasi valid yang sudah diproses disimpan setahun untuk mencocokkan sengketa pembayaran. */
    public const int PROCESSED_RETENTION_DAYS = 365;

    /**
     * Dihapus harian oleh `model:prune`. Notifikasi valid yang belum diproses (job gagal) tidak
     * pernah dihapus otomatis agar uang yang belum tercatat tetap bisa ditelusuri.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where(fn (Builder $query) => $query
                ->where('signature_valid', false)
                ->where('created_at', '<', now()->subDays(self::INVALID_RETENTION_DAYS)))
            ->orWhere(fn (Builder $query) => $query
                ->where('signature_valid', true)
                ->whereNotNull('processed_at')
                ->where('created_at', '<', now()->subDays(self::PROCESSED_RETENTION_DAYS)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
