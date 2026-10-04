<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use Carbon\CarbonImmutable;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $invoice_id
 * @property MessageTemplateKey|null $template_key
 * @property string $phone
 * @property string $body
 * @property MessageStatus $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'customer_id', 'invoice_id', 'template_key', 'phone', 'body', 'status', 'provider_message_id', 'error', 'sent_at',
])]
class MessageLog extends Model
{
    /** @use HasFactory<MessageLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_key' => MessageTemplateKey::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
