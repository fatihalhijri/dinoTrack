<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Template WhatsApp default. Template yang sudah diubah admin tidak ditimpa.
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MessageTemplateKey::cases() as $key) {
            MessageTemplate::query()->firstOrCreate(['key' => $key], [
                'body' => $key->defaultBody(),
                'is_active' => true,
            ]);
        }
    }
}
