<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
        /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->route('company') ? $this->route('company') : null;

        return [
            'company_name' => ['required', 'string', 'max:255', Rule::unique('companies', 'company_name')->ignore($companyId, 'company_id')],
            'company_abn' => ['nullable', 'string', 'max:11', Rule::unique('companies', 'company_abn')->ignore($companyId, 'company_id')],
            'company_acn' => ['nullable', 'string', 'max:9', Rule::unique('companies', 'company_acn')->ignore($companyId, 'company_id')],
            'company_primary_location_id' => ['nullable', 'integer', 'exists:locations,location_id'],
            'gst_registered' => ['required', 'boolean'],
            'gst_number' => ['nullable', 'string', 'max:15', Rule::unique('companies', 'gst_number')->ignore($companyId, 'company_id')],
            'gst_registration_start_date' => ['nullable', 'date', 'required_if:gst_registered,true'],
            'company_registration_date' => ['nullable', 'date'],
            'primary_contact_person' => ['nullable'],
            'company_type' => ['required', 'string'], // Adjust options as needed
            'subscriber_id' => ['nullable', 'integer', 'exists:companies,company_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'The company name is required.',
            'company_name.unique' => 'This company name is already taken.',
            'company_abn.unique' => 'This ABN is already registered.',
            'company_acn.unique' => 'This ACN is already registered.',
            'gst_number.unique' => 'This GST number is already registered.',
            'gst_registration_start_date.required_if' => 'The GST registration start date is required when GST is registered.',
            'company_type.in' => 'The company type must be required',
            'company_primary_location_id.exists' => 'The selected primary location is invalid.',
            'primary_contact_person.exists' => 'The selected primary contact person is invalid.',
            'subscriber_id.exists' => 'The selected subscriber is invalid.',
            'user_id.exists' => 'The selected user is invalid.',
        ];
    }
}
