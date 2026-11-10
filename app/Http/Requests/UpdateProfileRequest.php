<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $userId = auth()->id();

        return [
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            ],
            'first_name' => [
                'sometimes',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'middle_name' => [
                'sometimes',
                'nullable',
                'string',
                'min:1',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'last_name' => [
                'sometimes',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'integer',
                'digits_between:7,15',
            ],
            'country_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:5',
                'regex:/^\+?[1-9]\d{0,4}$/',
            ],
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
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'email.regex' => 'Please enter a valid email address format.',
            'email.max' => 'Email address cannot exceed 255 characters.',

            'first_name.min' => 'First name must be at least 2 characters long.',
            'first_name.max' => 'First name cannot exceed 50 characters.',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, and apostrophes.',

            'middle_name.min' => 'Middle name must be at least 1 character long.',
            'middle_name.max' => 'Middle name cannot exceed 50 characters.',
            'middle_name.regex' => 'Middle name can only contain letters, spaces, hyphens, and apostrophes.',

            'last_name.min' => 'Last name must be at least 2 characters long.',
            'last_name.max' => 'Last name cannot exceed 50 characters.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, and apostrophes.',

            'phone.integer' => 'Phone number must be a number.',
            'phone.digits_between' => 'Phone number must be between 7 and 15 digits.',
            'country_code.regex' => 'Please enter a valid country code format.',
            'country_code.max' => 'Country code cannot exceed 5 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'last_name' => 'last name',
            'phone' => 'phone number',
            'country_code' => 'country code',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all string inputs
        $this->merge([
            'email' => $this->email ? trim(strtolower($this->email)) : null,
            'first_name' => $this->first_name ? trim($this->first_name) : null,
            'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
            'last_name' => $this->last_name ? trim($this->last_name) : null,
            'phone' => $this->phone ? (int) $this->phone : null,
            'country_code' => $this->country_code ? trim($this->country_code) : null,
        ]);
    }
}
