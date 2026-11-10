<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Quote;
use App\Models\InvoiceTransaction;
use App\Models\Bill;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

class CommonAttachmentService
{
    /**
     * Create directory for any entity type
     */
    public function createDirectory(string $entityType, int $companyId, int $entityId, ?int $subEntityId = null): string
    {
        $basePath = "attachments/company_{$companyId}";
        
        switch ($entityType) {
            case 'Company':
                $directory = "{$basePath}/company";
                break;
            case 'Employee':
                $directory = "{$basePath}/employees/employee_{$entityId}";
                break;
            case 'Invoice':
                $directory = "{$basePath}/invoices/invoice_{$entityId}";
                break;
            case 'Quote':
                $directory = "{$basePath}/quotes/quote_{$entityId}";
                break;
            case 'Bill':
                $directory = "{$basePath}/bills/bill_{$entityId}";
                break;
            case 'Supplier':
                $directory = "{$basePath}/suppliers/supplier_{$entityId}";
                break;
            case 'Transaction':
                $directory = "{$basePath}/invoices/invoice_{$entityId}/transactions/transaction_{$subEntityId}";
                break;
            default:
                throw new \InvalidArgumentException("Unsupported entity type: {$entityType}");
        }
        
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory, 0755, true);
        }
        
        return $directory;
    }
    
    /**
     * Store attachment file and create database record
     */
    public function storeAttachment(string $entityType, int $companyId, int $entityId, UploadedFile $file, ?int $subEntityId = null): ?array
    {
        try {
            $directory = $this->createDirectory($entityType, $companyId, $entityId, $subEntityId);
            
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(40) . '.' . $extension;
            
            // Store file
            $path = $file->storeAs($directory, $filename, 'public');
            
            if (!$path) {
                \Log::error('Failed to store file to disk', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'directory' => $directory,
                    'filename' => $filename
                ]);
                return null;
            }
            
            // Add "storage/" prefix to make the path web-accessible
            $webPath = 'storage/' . $path;
            
            // Debug: Log the data being inserted
            $attachmentData = [
                'type' => $entityType,
                'type_id' => $subEntityId ?? $entityId,
                'image_url' => $webPath,
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];
            
            \Log::info('Creating attachment record', $attachmentData);
            
            // Create attachment record
            $attachment = Attachment::create($attachmentData);
            
            \Log::info('Attachment created successfully', [
                'attachment_id' => $attachment->id,
                'path' => $attachment->image_url
            ]);
            
            return [
                'id' => $attachment->id,
                'path' => $webPath,
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => $attachment->created_at,
            ];
            
        } catch (\Exception $e) {
            \Log::error('Failed to store attachment', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Store multiple attachments for any entity
     */
    public function storeMultipleAttachments(string $entityType, int $companyId, int $entityId, array $files, ?int $subEntityId = null): array
    {
        $uploaded = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $result = $this->storeAttachment($entityType, $companyId, $entityId, $file, $subEntityId);
                if ($result) {
                    $uploaded[] = $result;
                }
            }
        }
        
        return $uploaded;
    }
    
    /**
     * Delete attachment by filename
     */
    public function deleteAttachment(string $entityType, int $entityId, int $attachmentId): bool
    {
        try {
            $attachment = Attachment::where('type', $entityType)
                ->where('type_id', $entityId)
                ->where('id', $attachmentId)
                ->first();
            
            if (!$attachment) {
                return false;
            }
            
            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->image_url)) {
                Storage::disk('public')->delete($attachment->image_url);
            }
            
            // Delete database record
            $attachment->delete();
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Failed to delete attachment', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get attachments for any entity
     */
    public function getAttachments(string $entityType, int $entityId): array
    {
        $attachments = Attachment::where('type', $entityType)
            ->where('type_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'path' => $attachment->image_url,
                'uploaded_at' => $attachment->created_at,
                'uploaded_by' => $attachment->created_by,
            ];
        })->toArray();
    }
    
    /**
     * Get attachment download URL
     */
    public function getDownloadUrl(string $entityType, int $entityId, int $attachmentId): ?string
    {
        $attachment = Attachment::where('type', $entityType)
            ->where('type_id', $entityId)
            ->where('id', $attachmentId)
            ->first();
        
        if (!$attachment || !Storage::disk('public')->exists($attachment->image_url)) {
            return null;
        }
        
        return Storage::disk('public')->url($attachment->image_url);
    }
    
    /**
     * Validate file
     */
    public function validateFile(UploadedFile $file): bool
    {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv'
        ];
        
        $maxSize = 20 * 1024 * 1024; // 20MB
        
        return $file->isValid() && 
               in_array($file->getMimeType(), $allowedMimes) && 
               $file->getSize() <= $maxSize;
    }
    
    /**
     * Get attachment count for entity
     */
    public function getAttachmentCount(string $entityType, int $entityId): int
    {
        return Attachment::where('type', $entityType)
            ->where('type_id', $entityId)
            ->count();
    }
}
