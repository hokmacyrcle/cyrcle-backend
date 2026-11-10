<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invoice_id,
            'company' => [
                'id' => $this->company->company_id ?? null,
                'name' => $this->company->company_name ?? null,
                'email' => $this->company->company_email ?? null,
            ],
            'contact' => [
                'id' => $this->contact->contact_id ?? null,
                'name' => $this->contact->contact_name ?? null,
                'email' => $this->contact->contact_email ?? null,
                'phone' => $this->contact->contact_phone ?? null,
            ],
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'invoice_due_date' => $this->invoice_due_date?->format('Y-m-d'),
            'payment_term' => $this->when($this->paymentTerm, [
                'id' => $this->paymentTerm->id ?? null,
                'name' => $this->paymentTerm->name ?? null,
                'display_name' => $this->paymentTerm->display_name ?? null,
            ]),
            'amounts' => [
                'invoice_amount' => (float) $this->invoice_amount,
                'discount' => [
                    'type' => (float) $this->discount_type,
                    'method' => $this->discount_method,
                    'amount' => (float) $this->discount_amount,
                ],
                'subtotal' => (float) $this->subtotal,
                'tax' => [
                    'rate' => (float) $this->tax_rate,
                    'amount' => (float) $this->tax_amount,
                ],
                'gst_amount' => (float) $this->gst_amount,
                'invoice_total' => (float) $this->invoice_total,
                'paid_amount' => (float) $this->invoice_paid_amount,
                'balance_due' => (float) ($this->invoice_total - $this->invoice_paid_amount),
            ],
            'status' => [
                'invoice_status' => $this->invoice_status,
                'payment_status' => $this->invoice_payment_status,
            ],
            'items' => $this->when($this->items, function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->item_description,
                        'quantity' => (int) $item->item_quantity,
                        'rate' => (float) $item->item_rate,
                        'cost' => (float) $item->item_cost,
                        'gst' => (float) $item->item_gst,
                        'total' => (float) $item->item_total,
                    ];
                });
            }),
            'transactions' => $this->when($this->transactions, function () {
                return $this->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'payment_amount' => (float) $transaction->payment_amount,
                        'payment_date' => $transaction->payment_date?->format('Y-m-d'),
                        'payment_method' => $transaction->payment_method,
                        'reference_number' => $transaction->reference_number,
                        'notes' => $transaction->notes,
                    ];
                });
            }),
            'attachments' => [
                'invoice_attachment' => $this->invoice_attachment,
            ],
            'notes' => $this->notes,
            'terms' => $this->terms,
            'dates' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
