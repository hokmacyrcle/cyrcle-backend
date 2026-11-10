<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteListResource extends JsonResource
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
            'quote_date' => $this->quote_date?->format('Y-m-d'),
            'quote_number' => $this->quote_number,
            'contact_name' => $this->contact ? 
                ($this->contact->contact_company_name ?: 
                 $this->contact->contact_first_name . ' ' . $this->contact->contact_last_name) : 
                'Unknown Contact',
            'amount' => $this->total,
            'formatted_amount' => '$' . number_format($this->total, 2),
            'status' => $this->status,
            'status_color' => $this->getStatusColor($this->status)
        ];
    }

    /**
     * Get status color for the quote status
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'Pending' => 'warning',
            'Accepted' => 'success',
            'Closed' => 'info',
            'Rejected' => 'danger',
            default => 'secondary'
        };
    }
}
