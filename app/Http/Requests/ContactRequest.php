<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
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
        $contactId = $this->route('contact');
        
        return [
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_type' => 'required|in:Individual,Company,Sole Trader',
            
            // Individual / Sole Trader fields
            'contact_first_name' => 'nullable|string|max:255',
            'contact_middle_name' => 'nullable|string|max:255',
            'contact_last_name' => 'nullable|string|max:255',
            
            // Company field
            'contact_company_name' => 'nullable|string|max:255',
            
            // Contact information
            'contact_phone_number' => 'nullable|string|max:20',
            'contact_mobile_number' => 'nullable|string|max:20',
            'contact_email_address' => 'nullable|email|max:255',
            
            // Address fields
            'contact_address_line_1' => 'nullable|string|max:255',
            'contact_address_line_2' => 'nullable|string|max:255',
            'contact_address_line_3' => 'nullable|string|max:255',
            'contact_city' => 'nullable|string|max:100',
            'contact_state' => 'nullable|string|max:100',
            'contact_post_code' => 'nullable|string|max:20',
            'contact_country' => 'nullable|string|max:100',
            
            // Status and category
            'contact_category' => 'nullable|in:Supplier,Vendor,Other',
            'contact_status' => 'nullable|in:Active,Inactive',
            
            // Subscriber fields
            'subscriber_client' => 'nullable|boolean',
            'subscriber_id' => 'nullable|integer|exists:subscribers,id',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'contact_type.required' => 'Contact type is required.',
            'contact_type.in' => 'Contact type must be Individual, Company, or Sole Trader.',
            'contact_category.in' => 'Contact category must be Supplier, Vendor, or Other.',
            'contact_status.in' => 'Contact status must be Active or Inactive.',
            'contact_email_address.email' => 'Please provide a valid email address.',
            'company_id.exists' => 'The selected company does not exist.',
            'subscriber_id.exists' => 'The selected subscriber does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            // Validate required fields based on contact type
            if ($data['contact_type'] === 'Company') {
                if (empty($data['contact_company_name'])) {
                    $validator->errors()->add('contact_company_name', 'Company name is required for Company type contacts.');
                }
            } else {
                // Individual or Sole Trader
                if (empty($data['contact_first_name']) && empty($data['contact_last_name'])) {
                    $validator->errors()->add('contact_first_name', 'First name or last name is required for Individual/Sole Trader contacts.');
                }
            }
            
            // Validate subscriber_id when subscriber_client is true
            if (!empty($data['subscriber_client']) && $data['subscriber_client'] && empty($data['subscriber_id'])) {
                $validator->errors()->add('subscriber_id', 'Subscriber ID is required when subscriber client is enabled.');
            }
        });
    }
}
