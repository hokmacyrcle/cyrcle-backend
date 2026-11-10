<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service endpoint for the logged-in user. Employees only ever see
 * their own employee record; employers get their user + companies.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $employee = null;
        if (($user->role ?? 'employer') === 'employee' && $user->employee_id) {
            $employee = Employee::where('emp_id', $user->employee_id)
                ->select('emp_id', 'emp_first_name', 'emp_last_name', 'emp_job_title',
                         'emp_email_address', 'emp_company_id', 'emp_status', 'emp_start_date')
                ->first();
        }

        return response()->json([
            'status' => true,
            'data' => [
                'user' => $user,
                'role' => $user->role ?? 'employer',
                'employee' => $employee,
            ],
        ]);
    }
}
