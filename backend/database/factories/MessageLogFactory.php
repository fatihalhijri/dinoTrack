<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_id' => null,
            'template_key' => MessageTemplateKey::InvoiceIssued,
            'phone' => fn (array $attributes): string => Customer::query()->whereKey($attributes['customer_id'])->firstOrFail()->phone,
            'body' => fake()->sentence(),
            'status' => MessageStatus::Sent,
            'provider_message_id' => fake()->uuid(),
            'error' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(string $error = 'Nomor tidak terdaftar di WhatsApp'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Failed,
            'provider_message_id' => null,
            'error' => $error,
            'sent_at' => null,
        ]);
    }
}
