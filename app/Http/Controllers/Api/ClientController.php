<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user's company context
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            
            // Apply company filter based on authenticated user's company context
            if ($user && isset($user->company_id)) {
                $query = Client::where('company_id', $user->company_id);
            } else {
                // If no company context, return empty result for security
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'Company context not found. Please select a company first.'
                ], 403);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('vat_number', 'like', "%{$search}%")
                      ->orWhere('gst_number', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && in_array($request->status, ['active', 'inactive'])) {
                $query->where('status', $request->status);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $clients = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Clients retrieved successfully',
                'data' => $clients
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve clients',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientRequest $request): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to create clients'
                ], 403);
            }

            // Add company_id to the validated data
            $data = $request->validated();
            $data['company_id'] = $user->company_id;
            
            $client = Client::create($data);

            // Get the audit log for this creation
            $auditLog = $client->latestAuditLog;

            return response()->json([
                'status' => true,
                'status_code' => 201,
                'message' => 'Client created successfully',
                'data' => $client,
                'audit_log' => $auditLog
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to create client',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access clients'
                ], 403);
            }

            $client = Client::where('company_id', $user->company_id)->findOrFail($id);

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Client retrieved successfully',
                'data' => $client
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Client not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve client',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClientRequest $request, string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to update clients'
                ], 403);
            }

            $client = Client::where('company_id', $user->company_id)->findOrFail($id);
            $client->update($request->validated());

            // Get the audit log for this update
            $auditLog = $client->latestAuditLog;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Client updated successfully',
                'data' => $client,
                'audit_log' => $auditLog
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Client not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to update client',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to delete clients'
                ], 403);
            }

            $client = Client::where('company_id', $user->company_id)->findOrFail($id);
            
            // Get audit log before deletion
            $auditLog = $client->latestAuditLog;
            
            $client->delete();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Client deleted successfully',
                'audit_log' => $auditLog
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Client not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to delete client',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Get active clients only.
     */
    public function active(): JsonResponse
    {
        try {
            // Get the authenticated user's company_id
            $user = auth()->guard('api')->user() ?? auth()->user() ?? request()->user();
            if (!$user || !$user->company_id) {
                return response()->json([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'User must be associated with a company to access clients'
                ], 403);
            }

            $clients = Client::where('company_id', $user->company_id)->active()->orderBy('company_name')->get();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Active clients retrieved successfully',
                'data' => $clients
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve active clients',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Toggle client status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $client = Client::findOrFail($id);
            $client->update([
                'status' => $client->status === 'active' ? 'inactive' : 'active'
            ]);

            // Get the audit log for this status update
            $auditLog = $client->latestAuditLog;

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Client status updated successfully',
                'data' => [
                    'id' => $client->id,
                    'status' => $client->status
                ],
                'audit_log' => $auditLog
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Client not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to update client status',
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Get audit history for a specific client.
     */
    public function auditHistory(string $id): JsonResponse
    {
        try {
            $client = Client::findOrFail($id);
            $auditLogs = $client->auditLogs()
                ->with('performer')
                ->orderBy('performed_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'status_code' => 200,
                'message' => 'Client audit history retrieved successfully',
                'data' => [
                    'client' => $client,
                    'audit_logs' => $auditLogs
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Client not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'status_code' => 500,
                'message' => 'Failed to retrieve client audit history',
                'error' => $e->getMessage()
            ], 200);
        }
    }
}
