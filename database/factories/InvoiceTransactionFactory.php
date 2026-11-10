<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceTransaction>
 */
class InvoiceTransactionFactory extends Factory
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
            'payment_id' => $this->faker->unique()->numberBetween(1000, 9999),
            'payment_amount' => $this->faker->randomFloat(2, 100, 2000),
        ];
    }

    /**
     * Create a full payment transaction.
     */
    public function fullPayment(): static
    {
        return $this->state(function (array $attributes) {
            $invoice = Invoice::find($attributes['invoice_id']);
            return [
                'payment_amount' => $invoice ? $invoice->invoice_total : $this->faker->randomFloat(2, 1000, 5000),
            ];
        });
    }

    /**
     * Create a partial payment transaction.
     */
    public function partialPayment(): static
    {
        return $this->state(function (array $attributes) {
            $invoice = Invoice::find($attributes['invoice_id']);
            $maxAmount = $invoice ? $invoice->invoice_total * 0.8 : 1000;
            return [
                'payment_amount' => $this->faker->randomFloat(2, 100, $maxAmount),
            ];
        });
    }

    /**
     * Create a small payment transaction.
     */
    public function smallPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_amount' => $this->faker->randomFloat(2, 50, 200),
        ]);
    }

    /**
     * Create a large payment transaction.
     */
    public function largePayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_amount' => $this->faker->randomFloat(2, 2000, 10000),
        ]);
    }
}
