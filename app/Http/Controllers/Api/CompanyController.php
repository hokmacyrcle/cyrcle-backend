<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Rules\ValidAbn;

class CompanyController extends Controller
{
    /**
     * Get all companies for authenticated user
     */
    public function getUserCompanies(Request $request): JsonResponse
    {
        $user = $request->user();
        $companies = Company::where('user_id', $user->id)
            ->select('company_id', 'company_name', 'company_type', 'company_abn', 'company_acn')
            ->get();
            
        if ($companies->isEmpty()) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'No companies found for this user'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Companies information retrieved successfully',
            'data' => [
                'companies' => $companies,
                'companies_count' => $companies->count(),
                'total_companies' => $companies->count()
            ]
        ], 200);
    }

    /**
     * Select a company and set company context in current token
     */
    public function selectCompany(Request $request, $company_id): JsonResponse
    {
        $user = $request->user();
        
        // Validate company_id parameter
        if (!is_numeric($company_id) || !$company_id) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'Invalid company ID provided'
            ], 400);
        }
        
        $companyId = (int) $company_id;
        
        // Check if user has access to this company
        $company = Company::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$company || $company->status === 'archived') {
            return response()->json([
                'status' => false,
                'status_code' => 403,
                'message' => 'You do not have access to this company'
            ], 403);
        }
        
        // Store company context in the user's session/request for this token
        // We'll use a simple approach: store company_id in the user object
        $user->company_id = $companyId;
        
        // Also store in cache for this user's token
        $token = request()->bearerToken();
        if ($token) {
            \Cache::put("user_company_{$token}", $companyId, now()->addDays(30));
        }
        
        // Return success response with company info (no new token needed)
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Company selected successfully',
            'data' => [
                'user' => $user->fresh(),
                'selected_company' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'company_type' => $company->company_type,
                    'company_abn' => $company->company_abn,
                    'company_acn' => $company->company_acn
                ],
                'company_context' => true,
                'message' => 'Use the same token for all subsequent requests'
            ]
        ], 200);
    }

    /**
     * Get currently selected company for authenticated user
     */
    public function getCurrentCompany(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = request()->bearerToken();
        
        if (!$token) {
            return response()->json([
                'status' => false,
                'status_code' => 400,
                'message' => 'No authentication token provided'
            ], 400);
        }
        
        $companyId = \Cache::get("user_company_{$token}");
        
        if (!$companyId) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'No company selected. Please select a company first.'
            ], 404);
        }
        
        $company = Company::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$company) {
            return response()->json([
                'status' => false,
                'status_code' => 404,
                'message' => 'Selected company not found or access denied'
            ], 404);
        }
        
        return response()->json([
            'status' => true,
            'status_code' => 200,
            'message' => 'Current company retrieved successfully',
            'data' => [
                'company' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'company_type' => $company->company_type,
                    'company_abn' => $company->company_abn,
                    'company_acn' => $company->company_acn
                ]
            ]
        ], 200);
    }

    // ------------------------------------------------------------------
    // Client businesses: companies of type Client that belong to the
    // logged-in user's firm (the active Subscriber company they own).
    // ------------------------------------------------------------------

    private function firm(Request $request): ?Company
    {
        return Company::where('user_id', $request->user()->id)
            ->where('company_type', 'Subscriber')
            ->where('status', 'active')
            ->first();
    }

    private function clientsOf(Company $firm)
    {
        return Company::where('company_type', 'Client')->where('subscriber_id', $firm->company_id);
    }

    private function present(Company $c): array
    {
        return [
            'company_id' => $c->company_id,
            'company_name' => $c->company_name,
            'company_type' => $c->company_type,
            'company_abn' => $c->company_abn,
            'company_acn' => $c->company_acn,
            'gst_registered' => (bool) $c->gst_registered,
            'address_line' => $c->address_line,
            'suburb' => $c->suburb,
            'state' => $c->state,
            'postcode' => $c->postcode,
            'status' => $c->status ?? 'active',
            'created_at' => optional($c->created_at)->toDateTimeString(),
        ];
    }

    private function ok(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json(['status' => true, 'status_code' => $code, 'message' => $message, 'data' => $data], 200);
    }

    private function fail(int $code, string $message, $errors = null): JsonResponse
    {
        return response()->json(['status' => false, 'status_code' => $code, 'message' => $message, 'errors' => $errors], 200);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_abn' => ['nullable', new ValidAbn],
            'company_acn' => ['nullable', 'regex:/^\d{9}$/'],
            'gst_registered' => ['boolean'],
            'address_line' => ['required', 'string', 'max:255'],
            'suburb' => ['required', 'string', 'max:100'],
            'state' => ['required', 'in:NSW,VIC,QLD,WA,SA,TAS,ACT,NT'],
            'postcode' => ['required', 'digits:4'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $firm = $this->firm($request);
        if (!$firm) {
            return $this->fail(403, 'Your firm is not active yet.');
        }
        $status = $request->query('status') === 'archived' ? 'archived' : 'active';
        $q = $this->clientsOf($firm)->where('status', $status);
        if ($search = trim((string) $request->query('search', ''))) {
            $q->where('company_name', 'like', '%' . $search . '%');
        }
        $rows = $q->orderBy('company_name')->get()->map(fn ($c) => $this->present($c))->values();
        return $this->ok('Client businesses', ['companies' => $rows, 'count' => $rows->count()]);
    }

    public function show(Request $request, $company): JsonResponse
    {
        $firm = $this->firm($request);
        $c = $firm ? $this->clientsOf($firm)->where('company_id', $company)->first() : null;
        if (!$c) {
            return $this->fail(404, 'Client business not found.');
        }
        return $this->ok('Client business', ['company' => $this->present($c)]);
    }

    public function store(Request $request): JsonResponse
    {
        $firm = $this->firm($request);
        if (!$firm) {
            return $this->fail(403, 'Your firm is not active yet.');
        }
        $v = Validator::make($request->all(), $this->rules(), [
            'company_acn.regex' => 'ACN must be 9 digits.',
            'postcode.digits' => 'Postcode must be 4 digits.',
        ]);
        if ($v->fails()) {
            return $this->fail(400, 'Validation failed', $v->errors());
        }
        $abn = $request->company_abn ? preg_replace('/\s+/', '', $request->company_abn) : null;
        if ($abn && $this->clientsOf($firm)->where('company_abn', $abn)->exists()) {
            return $this->fail(400, 'Validation failed', ['company_abn' => ['You already have a client business with this ABN.']]);
        }

        $c = Company::create([
            'company_name' => $request->company_name,
            'company_abn' => $abn,
            'company_acn' => $request->company_acn ? preg_replace('/\s+/', '', $request->company_acn) : null,
            'gst_registered' => (bool) $request->gst_registered,
            'company_type' => 'Client',
            'subscriber_id' => $firm->company_id,
            'user_id' => $request->user()->id,
        ]);
        $c->forceFill([
            'status' => 'active',
            'address_line' => $request->address_line,
            'suburb' => $request->suburb,
            'state' => $request->state,
            'postcode' => $request->postcode,
        ])->save();

        return $this->ok('Client business added', ['company' => $this->present($c)], 201);
    }

    public function update(Request $request, $company): JsonResponse
    {
        $firm = $this->firm($request);
        $c = $firm ? $this->clientsOf($firm)->where('company_id', $company)->first() : null;
        if (!$c) {
            return $this->fail(404, 'Client business not found.');
        }
        $v = Validator::make($request->all(), $this->rules(), [
            'company_acn.regex' => 'ACN must be 9 digits.',
            'postcode.digits' => 'Postcode must be 4 digits.',
        ]);
        if ($v->fails()) {
            return $this->fail(400, 'Validation failed', $v->errors());
        }
        $abn = $request->company_abn ? preg_replace('/\s+/', '', $request->company_abn) : null;
        if ($abn && $this->clientsOf($firm)->where('company_abn', $abn)->where('company_id', '!=', $c->company_id)->exists()) {
            return $this->fail(400, 'Validation failed', ['company_abn' => ['You already have a client business with this ABN.']]);
        }
        $c->forceFill([
            'company_name' => $request->company_name,
            'company_abn' => $abn,
            'company_acn' => $request->company_acn ? preg_replace('/\s+/', '', $request->company_acn) : null,
            'gst_registered' => (bool) $request->gst_registered,
            'address_line' => $request->address_line,
            'suburb' => $request->suburb,
            'state' => $request->state,
            'postcode' => $request->postcode,
        ])->save();

        return $this->ok('Client business updated', ['company' => $this->present($c)]);
    }

    /** Archive or restore (nothing is ever hard-deleted). */
    public function toggleStatus(Request $request, $company): JsonResponse
    {
        $firm = $this->firm($request);
        $c = $firm ? $this->clientsOf($firm)->where('company_id', $company)->first() : null;
        if (!$c) {
            return $this->fail(404, 'Client business not found.');
        }
        $c->forceFill(['status' => ($c->status === 'archived') ? 'active' : 'archived'])->save();
        return $this->ok($c->status === 'archived' ? 'Client business archived' : 'Client business restored', ['company' => $this->present($c)]);
    }

    public function destroy(Request $request, $company): JsonResponse
    {
        $firm = $this->firm($request);
        $c = $firm ? $this->clientsOf($firm)->where('company_id', $company)->first() : null;
        if (!$c) {
            return $this->fail(404, 'Client business not found.');
        }
        $c->forceFill(['status' => 'archived'])->save();
        return $this->ok('Client business archived', ['company' => $this->present($c)]);
    }
}
