<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
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
        $quantity = $this->faker->numberBetween(1, 10);
        $rate = $this->faker->randomFloat(2, 50, 1000);
        $cost = $quantity * $rate;
        $gst = $cost * 0.10;
        $total = $cost + $gst;

        $descriptions = [
            'Website Design & Development',
            'SEO Optimization',
            'Digital Marketing Campaign',
            'Social Media Management',
            'Content Creation',
            'Business Strategy Consultation',
            'Server Maintenance',
            'Database Optimization',
            'Security Audit',
            'Backup System Setup',
            'Logo Design',
            'Brand Identity Package',
            'Mobile App Development',
            'E-commerce Setup',
            'Analytics & Reporting',
            'Customer Support',
            'Training & Workshops',
            'System Integration',
            'Cloud Migration',
            'Performance Optimization',
        ];

        return [
            'invoice_id' => Invoice::factory(),
            'item_description' => $this->faker->randomElement($descriptions),
            'item_quantity' => $quantity,
            'item_rate' => $rate,
            'item_cost' => $cost,
            'item_gst' => $gst,
            'item_total' => $total,
        ];
    }

    /**
     * Create a high-value item.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_rate' => $this->faker->randomFloat(2, 1000, 5000),
        ]);
    }

    /**
     * Create a low-value item.
     */
    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_rate' => $this->faker->randomFloat(2, 50, 200),
        ]);
    }

    /**
     * Create an item with high quantity.
     */
    public function highQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_quantity' => $this->faker->numberBetween(10, 100),
        ]);
    }
}
