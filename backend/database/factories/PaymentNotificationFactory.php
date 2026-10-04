<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentNotification>
 */
class PaymentNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderId = fake()->numerify('INV202610#####-1');

        return [
            'gateway' => 'midtrans',
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'payload' => [
                'order_id' => $orderId,
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '150000.00',
            ],
            'signature_valid' => true,
            'processed_at' => null,
        ];
    }
}
