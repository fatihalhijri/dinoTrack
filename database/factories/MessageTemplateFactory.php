<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => MessageTemplateKey::InvoiceIssued,
            'body' => 'Halo {nama}, tagihan {nomor_invoice} sebesar {total} jatuh tempo {jatuh_tempo}.',
            'is_active' => true,
        ];
    }
}
