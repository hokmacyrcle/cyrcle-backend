<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Services\CommonAttachmentService;
use App\Models\Attachment;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BillController extends Controller
{
    /**
     * Upload attachments for a bill
     */
    public function uploadAttachments(Request $request, $id): JsonResponse
    {
        // Get the authenticated user's company context
        $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
        
        if (!$user || !isset($user->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found. Please select a company first.'
            ], 403);
        }

        $bill = Bill::where('company_id', $user->company_id)->findOrFail($id);
        
        $request->validate([
            'attachments.*' => 'required|file|max:20480', // 20MB max
        ]);

        $attachmentService = new CommonAttachmentService();
        $uploadedFilesInfo = [];

        $uploadedFilesInfo = $attachmentService->storeMultipleAttachments(
            'Bill', 
            $bill->company_id, 
            $bill->bill_id, 
            $request->file('attachments')
        );

        // Log audit for each uploaded attachment
        foreach ($uploadedFilesInfo as $fileInfo) {
            $attachment = Attachment::find($fileInfo['id']);
            if ($attachment) {
                AuditLogService::logAction(
                    $bill,
                    'attachment_created',
                    [],
                    [
                        'attachment_id' => $attachment->id,
                        'path' => $attachment->image_url,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $uploadedFilesInfo,
            'message' => 'Attachments uploaded successfully'
        ]);
    }

    /**
     * Get bill attachments
     */
    public function getAttachments($id): JsonResponse
    {
        // Get the authenticated user's company context
        $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
        
        if (!$user || !isset($user->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found. Please select a company first.'
            ], 403);
        }

        $bill = Bill::where('company_id', $user->company_id)->findOrFail($id);
        $attachmentService = new CommonAttachmentService();
        
        $attachments = $attachmentService->getAttachments('Bill', $bill->bill_id);
        
        return response()->json([
            'success' => true,
            'data' => $attachments,
            'message' => 'Bill attachments retrieved successfully'
        ]);
    }

    /**
     * Delete bill attachment
     */
    public function deleteAttachment(Request $request, $id): JsonResponse
    {
        // Get the authenticated user's company context
        $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
        
        if (!$user || !isset($user->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found. Please select a company first.'
            ], 403);
        }

        $bill = Bill::where('company_id', $user->company_id)->findOrFail($id);
        $attachmentService = new CommonAttachmentService();
        $filename = $request->filename;

        // Find the attachment record in the database
        $attachment = $bill->attachments()->where('filename', basename($attachment->image_url))->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found in database'
            ], 404);
        }

        // Delete file from storage and database using common service
        $deleted = $attachmentService->deleteAttachment('Bill', $bill->bill_id, $filename);

        if ($deleted) {
            AuditLogService::logAction(
                $bill,
                'attachment_deleted',
                [
                    'attachment_id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'filename' => $attachment->filename,
                ],
                []
            );
            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete attachment'
        ], 500);
    }

    /**
     * Download bill attachment
     */
    public function downloadAttachment(Request $request, $id): JsonResponse
    {
        // Get the authenticated user's company context
        $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
        
        if (!$user || !isset($user->company_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found. Please select a company first.'
            ], 403);
        }

        $bill = Bill::where('company_id', $user->company_id)->findOrFail($id);
        $attachmentService = new CommonAttachmentService();
        $filename = $request->filename;

        // Find the attachment in the attachments table
        $attachment = $bill->attachments()->where('filename', basename($attachment->image_url))->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }

        $downloadUrl = $attachmentService->getDownloadUrl('Bill', $bill->bill_id, $filename);
        
        if (!$downloadUrl) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in storage'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'download_url' => $downloadUrl,
                'file_info' => $attachment->toArray()
            ],
            'message' => 'Download URL generated successfully'
        ]);
    }
}
