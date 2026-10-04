<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageTemplateKey;
use Carbon\CarbonImmutable;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property MessageTemplateKey $key
 * @property string $body
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'body', 'is_active'])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => MessageTemplateKey::class,
            'is_active' => 'boolean',
        ];
    }
}
