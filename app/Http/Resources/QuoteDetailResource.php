<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteDetailResource extends JsonResource
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
            'expire_date' => $this->expiration_date?->format('Y-m-d'),
            'status' => $this->status,
            'customer_name' => $this->contact ? 
                ($this->contact->contact_company_name ?: 
                 $this->contact->contact_first_name . ' ' . $this->contact->contact_last_name) : 
                'Unknown Contact',
            'email' => $this->customer_email,
            'billing_address' => $this->billing_address,
            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_percent ? 'Percentage' : 'Fixed Amount',
            'discount_amount' => $this->discount_amount,
            'discount_percent' => $this->discount_percent,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'message_on_quote' => $this->message_on_quote,
            'message_on_statement' => $this->message_on_statement,
            'attachments' => $this->getAllAttachmentsAttribute()->map(function($attachment) {
                return [
                    'id' => $attachment['id'],
                    'path' => $attachment['image_url'],
                    'original_name' => $attachment['original_name'],
                    'filename' => $attachment['filename'],
                    'size' => $attachment['size'],
                    'mime_type' => $attachment['mime_type'],
                    'created_at' => $attachment['created_at'],
                    'is_legacy' => $attachment['is_legacy'] ?? false
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
                        'amount' => $item->amount,
                        'gst' => $item->gst,
                        'class' => $item->class,
                    ];
                });
            }),
        ];
    }
}
