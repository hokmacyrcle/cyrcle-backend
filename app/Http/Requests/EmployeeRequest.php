<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Change to actual auth logic if needed
        return true;
    }

    public function rules(): array
    {
        return [
            'emp_first_name'        => 'required|string|max:255',
            'emp_middle_name'       => 'nullable|string|max:255',
            'emp_last_name'         => 'required|string|max:255',
            'emp_gender'            => 'nullable',
            'emp_email_address'     => 'nullable|email|max:255',
            'emp_phone_number'      => 'nullable|string|max:255',
            'emp_mobile_number'     => 'nullable|string|max:255',
            'emp_company_id'        => 'required',
            'emp_status'            => 'nullable',
            'emp_designation_id'    => 'nullable',
            'emp_start_date'        => 'nullable|date',
            'emp_resignation_date'  => 'nullable|date',
            'emp_last_working_date' => 'nullable|date',
            'emp_termination_date'  => 'nullable|date',
            'emp_contract_end_date' => 'nullable|date',
            'emp_tfn'               => 'nullable|string|max:255',
            'emp_work_authorization'=> 'nullable',
            'emp_visa_subclass'     => 'nullable|string|max:255',
            'emp_visa_expiry_date'  => 'nullable|date',
            'emp_employment_type'   => 'nullable',
            'emp_address_line_1'    => 'nullable|string|max:255',
            'emp_address_line_2'    => 'nullable|string|max:255',
            'emp_address_line_3'    => 'nullable|string|max:255',
            'emp_city'              => 'nullable|string|max:255',
            'emp_state'             => 'nullable|string|max:255',
            'emp_post_code'         => 'nullable|string|max:255',
            'emp_country'           => 'nullable|string|max:255',
        ];
    }
}
