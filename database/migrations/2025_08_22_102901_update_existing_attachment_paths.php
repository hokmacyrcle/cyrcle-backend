<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Attachment;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing attachments to include "storage/" prefix
        $attachments = Attachment::all();
        
        foreach ($attachments as $attachment) {
            $currentPath = $attachment->image_url;
            
            // Only update if the path doesn't already start with "storage/"
            if (!str_starts_with($currentPath, 'storage/')) {
                $newPath = 'storage/' . $currentPath;
                $attachment->update(['image_url' => $newPath]);
                echo "Updated: {$currentPath} -> {$newPath}\n";
            } else {
                echo "Already correct: {$currentPath}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove "storage/" prefix from attachments
        $attachments = Attachment::all();
        
        foreach ($attachments as $attachment) {
            $currentPath = $attachment->image_url;
            
            // Only update if the path starts with "storage/"
            if (str_starts_with($currentPath, 'storage/')) {
                $newPath = substr($currentPath, 8); // Remove "storage/" prefix
                $attachment->update(['image_url' => $newPath]);
                echo "Reverted: {$currentPath} -> {$newPath}\n";
            } else {
                echo "Already correct: {$currentPath}\n";
            }
        }
    }
};
