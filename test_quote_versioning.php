<?php

/**
 * Test Quote Versioning System
 * 
 * This file demonstrates the quote versioning functionality that has been implemented.
 * It shows how quotes are automatically versioned when updated and how to work with versions.
 */

require_once 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Services\QuoteVersioningService;

echo "=== Quote Versioning System Test ===\n\n";

try {
    // Get a sample quote
    $quote = Quote::with('items')->first();
    
    if (!$quote) {
        echo "No quotes found. Please run the seeders first.\n";
        exit(1);
    }

    echo "Testing with Quote: {$quote->quote_number}\n";
    echo "Current Status: {$quote->status}\n";
    echo "Current Total: A$" . number_format($quote->total, 2) . "\n";
    echo "Items Count: " . $quote->items->count() . "\n\n";

    // Initialize versioning service
    $versioningService = new QuoteVersioningService();

    // Check if quote has versions
    $hasVersions = $versioningService->hasMultipleVersions($quote->quote_id);
    echo "Quote has multiple versions: " . ($hasVersions ? 'Yes' : 'No') . "\n\n";

    if ($hasVersions) {
        // Get version history
        echo "=== Version History ===\n";
        $versionHistory = $versioningService->getVersionHistory($quote->quote_id);
        
        foreach ($versionHistory as $version) {
            echo "Version {$version['formatted_version']} ({$version['created_at']})\n";
            echo "  Created by: {$version['created_by']['name']}\n";
            echo "  Status: {$version['status']}\n";
            echo "  Total: {$version['formatted_total']}\n";
            echo "  Changes: {$version['change_summary']}\n";
            echo "  Reason: {$version['change_reason']}\n";
            echo "  Items: {$version['items_count']}\n";
            echo "  Current: " . ($version['is_current_version'] ? 'Yes' : 'No') . "\n";
            echo "\n";
        }

        // Get version statistics
        echo "=== Version Statistics ===\n";
        $statistics = $versioningService->getVersionStatistics($quote->quote_id);
        echo "Total versions: {$statistics['total_versions']}\n";
        echo "Latest version: {$statistics['latest_version']}\n";
        echo "First version: {$statistics['first_version']}\n";
        echo "Created date: {$statistics['created_date']}\n";
        echo "Last updated: {$statistics['last_updated']}\n";
        echo "Total changes: {$statistics['total_changes']}\n\n";

        // Compare versions if we have multiple
        if ($statistics['total_versions'] > 1) {
            echo "=== Version Comparison ===\n";
            $comparison = $versioningService->compareVersions(
                $quote->quote_id, 
                $statistics['first_version'], 
                $statistics['latest_version']
            );
            
            echo "Comparing {$statistics['first_version']} vs {$statistics['latest_version']}:\n";
            
            foreach ($comparison as $field => $changes) {
                if ($field === 'items') {
                    echo "  Items:\n";
                    echo "    Added: " . count($changes['added']) . "\n";
                    echo "    Removed: " . count($changes['removed']) . "\n";
                    echo "    Modified: " . count($changes['modified']) . "\n";
                } else {
                    echo "  {$field}: {$changes['from']} → {$changes['to']}\n";
                }
            }
            echo "\n";
        }

        // Get current version
        echo "=== Current Version ===\n";
        $currentVersion = $versioningService->getCurrentVersion($quote->quote_id);
        if ($currentVersion) {
            echo "Current version: {$currentVersion->version_number}\n";
            echo "Version ID: {$currentVersion->id}\n";
            echo "Created: {$currentVersion->created_at->format('Y-m-d H:i:s')}\n";
            echo "Change summary: {$currentVersion->change_summary}\n";
        }
        echo "\n";

    } else {
        echo "No versions found. This quote hasn't been updated yet.\n";
        echo "To test versioning, update the quote through the API or manually.\n\n";
    }

    // Show available API endpoints
    echo "=== Available API Endpoints ===\n";
    echo "GET /api/quotes/{id}/versions - Get version history\n";
    echo "GET /api/quotes/{id}/versions/{versionNumber} - Get specific version\n";
    echo "POST /api/quotes/{id}/versions/compare - Compare two versions\n";
    echo "POST /api/quotes/{id}/versions/restore - Restore to specific version\n";
    echo "GET /api/quotes/{id}/versions/statistics - Get version statistics\n\n";

    echo "=== How It Works ===\n";
    echo "1. When a quote is created, version 1.0 is automatically created\n";
    echo "2. When a quote is updated, a new version is created with the next version number\n";
    echo "3. Each version stores a complete snapshot of the quote at that time\n";
    echo "4. Version numbers follow semantic versioning (1.0, 1.1, 2.0, etc.)\n";
    echo "5. You can compare versions to see what changed\n";
    echo "6. You can restore a quote to any previous version\n";
    echo "7. All changes are tracked with summaries and reasons\n\n";

    echo "=== Benefits ===\n";
    echo "• Complete audit trail of all quote changes\n";
    echo "• Ability to revert to previous versions\n";
    echo "• Track pricing and content evolution\n";
    echo "• Compliance with business requirements\n";
    echo "• Better customer communication with version history\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
