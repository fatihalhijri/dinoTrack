<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => 'Langganan internet',
            'quantity' => 1,
            'unit_price' => 150_000,
            'amount' => fn (array $attributes): int => $attributes['quantity'] * $attributes['unit_price'],
        ];
    }
}
