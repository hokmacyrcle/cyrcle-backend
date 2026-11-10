<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $dueDate = Carbon::parse($invoiceDate)->addDays($this->faker->numberBetween(15, 60));
        $invoiceAmount = $this->faker->randomFloat(2, 100, 5000);
        $gstAmount = $invoiceAmount * 0.10;
        $invoiceTotal = $invoiceAmount + $gstAmount;
        
        $statuses = ['Due', 'Past Due'];
        $paymentStatuses = ['Paid', 'Unpaid', 'Partially Paid'];
        
        $status = $this->faker->randomElement($statuses);
        $paymentStatus = $this->faker->randomElement($paymentStatuses);
        
        // Adjust status based on due date
        if (Carbon::parse($dueDate)->isPast()) {
            $status = 'Past Due';
        }
        
        // Calculate paid amount based on payment status
        $paidAmount = 0;
        if ($paymentStatus === 'Paid') {
            $paidAmount = $invoiceTotal;
        } elseif ($paymentStatus === 'Partially Paid') {
            $paidAmount = $this->faker->randomFloat(2, 100, $invoiceTotal - 100);
        }

        return [
            'company_id' => Company::factory(),
            'invoice_date' => $invoiceDate,
            'sent_to' => Contact::factory(),
            'invoice_amount' => $invoiceAmount,
            'gst_amount' => $gstAmount,
            'invoice_total' => $invoiceTotal,
            'invoice_due_date' => $dueDate,
            'invoice_status' => $status,
            'invoice_payment_status' => $paymentStatus,
            'invoice_paid_amount' => $paidAmount,
            'invoice_attachment' => 'invoices/inv-' . $this->faker->unique()->numberBetween(1000, 9999) . '.pdf',
        ];
    }

    /**
     * Indicate that the invoice is due.
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_status' => 'Due',
            'invoice_due_date' => Carbon::now()->addDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the invoice is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_status' => 'Past Due',
            'invoice_due_date' => Carbon::now()->subDays($this->faker->numberBetween(1, 60)),
        ]);
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_payment_status' => 'Paid',
            'invoice_paid_amount' => $attributes['invoice_total'],
        ]);
    }

    /**
     * Indicate that the invoice is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_payment_status' => 'Unpaid',
            'invoice_paid_amount' => 0,
        ]);
    }

    /**
     * Indicate that the invoice is partially paid.
     */
    public function partiallyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_payment_status' => 'Partially Paid',
            'invoice_paid_amount' => $this->faker->randomFloat(2, 100, $attributes['invoice_total'] - 100),
        ]);
    }
}
