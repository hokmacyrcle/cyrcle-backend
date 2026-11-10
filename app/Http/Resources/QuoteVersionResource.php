<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_id' => $this->quote_id,
            'version_number' => $this->version_number,
            'formatted_version' => $this->formatted_version_number,
            'created_by' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name ?? 'Unknown User',
                'email' => $this->creator?->email ?? 'N/A'
            ],
            'company' => [
                'id' => $this->company?->company_id,
                'name' => $this->company?->company_name ?? 'N/A'
            ],
            'contact' => [
                'id' => $this->contact?->contact_id,
                'name' => $this->contact?->contact_first_name . ' ' . $this->contact?->contact_last_name,
                'company_name' => $this->contact?->contact_company_name ?? 'N/A',
                'email' => $this->contact?->contact_email_address ?? 'N/A'
            ],
            'payment_term' => $this->paymentTerm ? [
                'id' => $this->paymentTerm->id,
                'description' => $this->paymentTerm->description ?? 'N/A',
                'days' => $this->paymentTerm->days ?? 0
            ] : null,
            'quote_number' => $this->quote_number,
            'quote_date' => $this->quote_date?->format('Y-m-d'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_color' => $this->status_color,
            'customer_email' => $this->customer_email,
            'billing_address' => $this->billing_address,
            'financial_details' => [
                'subtotal' => $this->subtotal,
                'formatted_subtotal' => $this->formatted_subtotal,
                'discount_percent' => $this->discount_percent,
                'discount_amount' => $this->discount_amount,
                'tax_amount' => $this->tax_amount,
                'total' => $this->total,
                'formatted_total' => $this->formatted_total
            ],
            'content' => [
                'message_on_quote' => $this->message_on_quote,
                'message_on_statement' => $this->message_on_statement,
                'notes' => $this->notes,
                'terms' => $this->terms
            ],
            'attachments' => $this->attachments,
            'send_later' => $this->send_later,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),
            'version_metadata' => [
                'change_summary' => $this->change_summary,
                'change_reason' => $this->change_reason,
                'is_current_version' => $this->is_current_version
            ],
            'items' => $this->when($this->items, function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_service' => $item->product_service,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'formatted_rate' => $item->formatted_rate,
                        'amount' => $item->amount,
                        'formatted_amount' => $item->formatted_amount,
                        'gst' => $item->gst,
                        'formatted_gst' => $item->formatted_gst,
                        'gst_rate' => $item->gst_rate,
                        'total' => $item->total,
                        'formatted_total' => $item->formatted_total,
                        'notes' => $item->notes
                    ];
                });
            }),
            'items_count' => $this->items_count ?? $this->items?->count() ?? 0,
            'timestamps' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
            ]
        ];
    }
}
