<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
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
    public function rules(): array
    {
        $clientId = $this->route('client');
        
        return [
            'company_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\.\,\&\'\(\)]+$/',
                $clientId ? Rule::unique('clients')->ignore($clientId) : Rule::unique('clients')
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/',
                $clientId ? Rule::unique('clients')->ignore($clientId) : Rule::unique('clients')
            ],
            'website' => [
                'nullable',
                'url',
                'max:255'
            ],
            'vat_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                $clientId ? Rule::unique('clients')->ignore($clientId) : Rule::unique('clients')
            ],
            'gst_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                $clientId ? Rule::unique('clients')->ignore($clientId) : Rule::unique('clients')
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                $clientId ? Rule::unique('clients')->ignore($clientId) : Rule::unique('clients')
            ],
            'status' => [
                'nullable',
                'in:active,inactive'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'company_name.regex' => 'Company name can only contain letters, numbers, spaces, hyphens, dots, commas, ampersands, and parentheses.',
            'company_name.unique' => 'This company name is already registered.',
            'phone.regex' => 'Phone number format is invalid.',
            'phone.unique' => 'This phone number is already registered.',
            'website.url' => 'Please enter a valid website URL.',
            'vat_number.regex' => 'VAT number can only contain uppercase letters, numbers, and hyphens.',
            'vat_number.unique' => 'This VAT number is already registered.',
            'gst_number.regex' => 'GST number can only contain uppercase letters, numbers, and hyphens.',
            'gst_number.unique' => 'This GST number is already registered.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'status.in' => 'Status must be either active or inactive.'
        ];
    }
}
