<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\QuoteVersionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuoteVersioningService
{
    /**
     * Create a new version of a quote
     */
    public function createVersion(
        Quote $quote, 
        string $changeSummary = null, 
        string $changeReason = null
    ): QuoteVersion {
        DB::beginTransaction();

        try {
            // Get the next version number
            $nextVersionNumber = $this->getNextVersionNumber($quote->quote_id);

            // Mark all existing versions as not current
            QuoteVersion::where('quote_id', $quote->quote_id)
                ->update(['is_current_version' => false]);

            // Create the new version
            $quoteVersion = QuoteVersion::create([
                'quote_id' => $quote->quote_id,
                'version_number' => $nextVersionNumber,
                'created_by' => Auth::id(),
                'company_id' => $quote->company_id,
                'contact_id' => $quote->contact_id,
                'payment_term_id' => $quote->payment_term_id,
                'quote_number' => $quote->quote_number,
                'quote_date' => $quote->quote_date,
                'expiration_date' => $quote->expiration_date,
                'status' => $quote->status,
                'customer_email' => $quote->customer_email ?? null,
                'billing_address' => $quote->billing_address ?? null,
                'subtotal' => $quote->subtotal,
                'discount_percent' => $quote->discount_percent,
                'discount_amount' => $quote->discount_amount,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
                'message_on_quote' => $quote->message_on_quote ?? null,
                'message_on_statement' => $quote->message_on_statement ?? null,
                'notes' => $quote->notes,
                'terms' => $quote->terms,
                'attachments' => $quote->attachments,
                'send_later' => $quote->send_later ?? false,
                'scheduled_at' => $quote->scheduled_at,
                'change_summary' => $changeSummary,
                'change_reason' => $changeReason,
                'is_current_version' => true,
            ]);

            // Create version items
            foreach ($quote->items as $item) {
                QuoteVersionItem::create([
                    'quote_version_id' => $quoteVersion->id,
                    'product_service' => $item->product_service,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'amount' => $item->amount,
                    'gst' => $item->gst,
                    'gst_rate' => $item->gst_rate ?? 10.00,
                    'total' => $item->amount + $item->gst,
                    'notes' => $item->notes ?? null,
                ]);
            }

            DB::commit();
            return $quoteVersion;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get the next version number for a quote
     */
    public function getNextVersionNumber(int $quoteId): string
    {
        $latestVersion = QuoteVersion::where('quote_id', $quoteId)
            ->orderBy('version_number', 'desc')
            ->first();

        if (!$latestVersion) {
            return '1.0';
        }

        // Parse current version number (e.g., "1.0", "2.1", "3.0")
        $versionParts = explode('.', $latestVersion->version_number);
        $major = (int) $versionParts[0];
        $minor = isset($versionParts[1]) ? (int) $versionParts[1] : 0;

        // Increment minor version for updates, major for significant changes
        $minor++;
        
        // Reset minor to 0 and increment major if minor reaches 10
        if ($minor >= 10) {
            $major++;
            $minor = 0;
        }

        return $major . '.' . $minor;
    }

    /**
     * Get version history for a quote
     */
    public function getVersionHistory(int $quoteId): array
    {
        $versions = QuoteVersion::where('quote_id', $quoteId)
            ->with(['creator', 'items'])
            ->orderBy('version_number', 'desc')
            ->get();

        $history = [];
        foreach ($versions as $version) {
            $history[] = [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'formatted_version' => $version->formatted_version_number,
                'created_at' => $version->created_at->format('Y-m-d H:i:s'),
                'created_by' => $version->creator ? [
                    'id' => $version->creator->id,
                    'name' => $version->creator->name ?? 'Unknown User'
                ] : null,
                'status' => $version->status,
                'status_color' => $version->status_color,
                'total' => $version->total,
                'formatted_total' => $version->formatted_total,
                'change_summary' => $version->change_summary,
                'change_reason' => $version->change_reason,
                'is_current_version' => $version->is_current_version,
                'items_count' => $version->items->count(),
                'summary' => $this->generateVersionSummary($version),
            ];
        }

        return $history;
    }

    /**
     * Get a specific version of a quote
     */
    public function getVersion(int $quoteId, string $versionNumber): ?QuoteVersion
    {
        return QuoteVersion::where('quote_id', $quoteId)
            ->where('version_number', $versionNumber)
            ->with(['creator', 'items', 'company', 'contact', 'paymentTerm'])
            ->first();
    }

    /**
     * Compare two versions of a quote
     */
    public function compareVersions(int $quoteId, string $version1, string $version2): array
    {
        $version1Data = $this->getVersion($quoteId, $version1);
        $version2Data = $this->getVersion($quoteId, $version2);

        if (!$version1Data || !$version2Data) {
            throw new \Exception('One or both versions not found');
        }

        return $version1Data->compareWith($version2Data);
    }

    /**
     * Restore a quote to a specific version
     */
    public function restoreToVersion(Quote $quote, string $versionNumber): bool
    {
        $targetVersion = $this->getVersion($quote->quote_id, $versionNumber);
        
        if (!$targetVersion) {
            throw new \Exception('Version not found');
        }

        DB::beginTransaction();

        try {
            // Create a new version before restoring (to preserve current state)
            $this->createVersion(
                $quote, 
                "Restored to version {$versionNumber}", 
                "Quote restored to previous version {$versionNumber}"
            );

            // Update the quote with version data
            $quote->update([
                'company_id' => $targetVersion->company_id,
                'contact_id' => $targetVersion->contact_id,
                'payment_term_id' => $targetVersion->payment_term_id,
                'quote_date' => $targetVersion->quote_date,
                'expiration_date' => $targetVersion->expiration_date,
                'status' => $targetVersion->status,
                'customer_email' => $targetVersion->customer_email,
                'billing_address' => $targetVersion->billing_address,
                'subtotal' => $targetVersion->subtotal,
                'discount_percent' => $targetVersion->discount_percent,
                'discount_amount' => $targetVersion->discount_amount,
                'tax_amount' => $targetVersion->tax_amount,
                'total' => $targetVersion->total,
                'message_on_quote' => $targetVersion->message_on_quote,
                'message_on_statement' => $targetVersion->message_on_statement,
                'notes' => $targetVersion->notes,
                'terms' => $targetVersion->terms,
                'attachments' => $targetVersion->attachments,
                'send_later' => $targetVersion->send_later,
                'scheduled_at' => $targetVersion->scheduled_at,
            ]);

            // Update quote items
            $quote->items()->delete();
            foreach ($targetVersion->items as $item) {
                $quote->items()->create([
                    'quote_id' => $quote->quote_id,
                    'product_service' => $item->product_service,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'amount' => $item->amount,
                    'gst' => $item->gst,
                    'gst_rate' => $item->gst_rate,
                ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate a summary of changes for a version
     */
    private function generateVersionSummary(QuoteVersion $version): string
    {
        $summary = [];

        if ($version->change_summary) {
            $summary[] = $version->change_summary;
        }

        if ($version->items->count() > 0) {
            $summary[] = "Contains {$version->items->count()} items";
        }

        if ($version->total > 0) {
            $summary[] = "Total: A$" . number_format($version->total, 2);
        }

        return implode(' • ', $summary) ?: 'No summary available';
    }

    /**
     * Get version statistics for a quote
     */
    public function getVersionStatistics(int $quoteId): array
    {
        $versions = QuoteVersion::where('quote_id', $quoteId)->get();
        
        return [
            'total_versions' => $versions->count(),
            'latest_version' => $versions->max('version_number'),
            'first_version' => $versions->min('version_number'),
            'created_date' => $versions->min('created_at')?->format('Y-m-d'),
            'last_updated' => $versions->max('created_at')?->format('Y-m-d'),
            'total_changes' => $versions->whereNotNull('change_summary')->count(),
            'version_timeline' => $versions->map(function ($version) {
                return [
                    'version' => $version->version_number,
                    'date' => $version->created_at->format('Y-m-d H:i'),
                    'changes' => $version->change_summary ?: 'No changes documented'
                ];
            })->toArray()
        ];
    }

    /**
     * Check if a quote has multiple versions
     */
    public function hasMultipleVersions(int $quoteId): bool
    {
        return QuoteVersion::where('quote_id', $quoteId)->count() > 1;
    }

    /**
     * Get the current version of a quote
     */
    public function getCurrentVersion(int $quoteId): ?QuoteVersion
    {
        return QuoteVersion::where('quote_id', $quoteId)
            ->where('is_current_version', true)
            ->with(['creator', 'items'])
            ->first();
    }
}
