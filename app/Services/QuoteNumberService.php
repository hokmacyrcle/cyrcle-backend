<?php

namespace App\Services;

use App\Models\Quote;
use Carbon\Carbon;

class QuoteNumberService
{
    /**
     * Generate a unique quote number
     */
    public function generateQuoteNumber(int $companyId): string
    {
        $prefix = $this->getCompanyPrefix($companyId);
        $year = Carbon::now()->format('Y');
        $month = Carbon::now()->format('m');
        
        // Get the last quote number for this company and year/month
        $lastQuote = Quote::where('company_id', $companyId)
            ->whereYear('quote_date', $year)
            ->whereMonth('quote_date', $month)
            ->orderBy('quote_id', 'desc')
            ->first();
        
        if ($lastQuote && $lastQuote->quote_number) {
            // Extract the sequence number from the last quote number
            $lastSequence = $this->extractSequenceNumber($lastQuote->quote_number);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }
        
        // Format: Q-COMPANY-YEAR-MONTH-SEQUENCE (e.g., Q-001-2025-08-001)
        return sprintf(
            '%s-%s-%s-%s-%s',
            $prefix,
            str_pad($companyId, 3, '0', STR_PAD_LEFT),
            $year,
            $month,
            str_pad($nextSequence, 3, '0', STR_PAD_LEFT)
        );
    }
    
    /**
     * Get company prefix for quote numbers
     */
    private function getCompanyPrefix(int $companyId): string
    {
        // You can customize this based on company settings
        return 'Q';
    }
    
    /**
     * Extract sequence number from existing quote number
     */
    private function extractSequenceNumber(string $quoteNumber): int
    {
        $parts = explode('-', $quoteNumber);
        return (int) end($parts);
    }
    
    /**
     * Validate if a quote number is unique
     */
    public function isQuoteNumberUnique(string $quoteNumber, int $companyId, ?int $excludeQuoteId = null): bool
    {
        $query = Quote::where('quote_number', $quoteNumber)
            ->where('company_id', $companyId);
            
        if ($excludeQuoteId) {
            $query->where('quote_id', '!=', $excludeQuoteId);
        }
        
        return !$query->exists();
    }
    
    /**
     * Get next available sequence for a specific month
     */
    public function getNextSequence(int $companyId, string $date = null): int
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();
        $year = $date->format('Y');
        $month = $date->format('m');
        
        $lastQuote = Quote::where('company_id', $companyId)
            ->whereYear('quote_date', $year)
            ->whereMonth('quote_date', $month)
            ->orderBy('quote_id', 'desc')
            ->first();
        
        if ($lastQuote && $lastQuote->quote_number) {
            $lastSequence = $this->extractSequenceNumber($lastQuote->quote_number);
            return $lastSequence + 1;
        }
        
        return 1;
    }
}
