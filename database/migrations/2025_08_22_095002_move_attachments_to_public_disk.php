<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Attachment;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all attachments
        $attachments = Attachment::all();
        
        foreach ($attachments as $attachment) {
            $oldPath = $attachment->image_url;
            $newPath = $oldPath; // Will be updated if file exists
            
            // Check if file exists in local disk
            if (Storage::disk('local')->exists($oldPath)) {
                try {
                    // Read file content from local disk
                    $fileContent = Storage::disk('local')->get($oldPath);
                    
                    // Create directory structure in public disk
                    $directory = dirname($oldPath);
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory, 0755, true);
                    }
                    
                    // Store file in public disk
                    $stored = Storage::disk('public')->put($oldPath, $fileContent);
                    
                    if ($stored) {
                        // Update database record
                        $attachment->update(['image_url' => $newPath]);
                        
                        // Delete from local disk
                        Storage::disk('local')->delete($oldPath);
                        
                        echo "Moved: {$oldPath} -> {$newPath}\n";
                    } else {
                        echo "Failed to store: {$oldPath}\n";
                    }
                    
                } catch (\Exception $e) {
                    echo "Error moving {$oldPath}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "File not found: {$oldPath}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all attachments
        $attachments = Attachment::all();
        
        foreach ($attachments as $attachment) {
            $publicPath = $attachment->image_url;
            
            // Check if file exists in public disk
            if (Storage::disk('public')->exists($publicPath)) {
                try {
                    // Read file content from public disk
                    $fileContent = Storage::disk('public')->get($publicPath);
                    
                    // Create directory structure in local disk
                    $directory = dirname($publicPath);
                    if (!Storage::disk('local')->exists($directory)) {
                        Storage::disk('local')->makeDirectory($directory, 0755, true);
                    }
                    
                    // Store file in local disk
                    $stored = Storage::disk('local')->put($publicPath, $fileContent);
                    
                    if ($stored) {
                        // Update database record
                        $attachment->update(['image_url' => $publicPath]);
                        
                        // Delete from public disk
                        Storage::disk('public')->delete($publicPath);
                        
                        echo "Moved back: {$publicPath} -> local disk\n";
                    } else {
                        echo "Failed to store back: {$publicPath}\n";
                    }
                    
                } catch (\Exception $e) {
                    echo "Error moving back {$publicPath}: " . $e->getMessage() . "\n";
                }
            } else {
                echo "File not found in public: {$publicPath}\n";
            }
        }
    }
};
