<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MinimalLeaveRequest;

class EmployeeLeaveController extends Controller
{
    /**
     * Store a new minimal leave request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,emp_id',
            'leave_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'pay_period_hours' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $leave = MinimalLeaveRequest::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave request created',
            'data' => $leave,
        ], 201);
    }
}


