<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quote_id' => $this->quote_id,
            'quote_number' => $this->quote_number,
            'quote_date' => $this->quote_date?->format('Y-m-d'),
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_color' => $this->status_color,
            'customer_email' => $this->customer_email,
            'billing_address' => $this->billing_address,
            'subtotal' => $this->subtotal,
            'formatted_subtotal' => $this->formatted_subtotal,
            'discount_percent' => $this->discount_percent,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'formatted_total' => $this->formatted_total,
            'message_on_quote' => $this->message_on_quote,
            'message_on_statement' => $this->message_on_statement,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'attachments' => $this->attachments,
            'send_later' => $this->send_later,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),
            'is_expired' => $this->isExpired(),
            'can_convert_to_invoice' => $this->canBeConvertedToInvoice(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->company_id,
                    'name' => $this->company->company_name,
                    'abn' => $this->company->company_abn,
                ];
            }),
            
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->contact_id,
                    'first_name' => $this->contact->contact_first_name,
                    'last_name' => $this->contact->contact_last_name,
                    'company_name' => $this->contact->contact_company_name,
                    'email' => $this->contact->contact_email_address,
                    'phone' => $this->contact->contact_phone_number,
                ];
            }),
            
            'payment_term' => $this->whenLoaded('paymentTerm', function () {
                return [
                    'id' => $this->paymentTerm->id,
                    'name' => $this->paymentTerm->term_name,
                    'days' => $this->paymentTerm->term_days,
                ];
            }),
            
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'item_id' => $item->item_id,
                        'product_service' => $item->product_service,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'formatted_rate' => $item->formatted_rate,
                        'amount' => $item->amount,
                        'formatted_amount' => $item->formatted_amount,
                        'gst' => $item->gst,
                        'formatted_gst' => $item->formatted_gst,
                        'class' => $item->class,
                    ];
                });
            }),
        ];
    }
}
