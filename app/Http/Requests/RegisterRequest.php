<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            ],
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'middle_name' => [
                'nullable',
                'string',
                'min:1',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\'-]+$/',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,phone',
                'regex:/^(\+?[1-9]\d{1,14})?$/',
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
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'email.regex' => 'Please enter a valid email address format.',
            'email.max' => 'Email address cannot exceed 255 characters.',

            'first_name.required' => 'First name is required.',
            'first_name.min' => 'First name must be at least 2 characters long.',
            'first_name.max' => 'First name cannot exceed 50 characters.',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, and apostrophes.',

            'middle_name.min' => 'Middle name must be at least 1 character long.',
            'middle_name.max' => 'Middle name cannot exceed 50 characters.',
            'middle_name.regex' => 'Middle name can only contain letters, spaces, hyphens, and apostrophes.',

            'last_name.required' => 'Last name is required.',
            'last_name.min' => 'Last name must be at least 2 characters long.',
            'last_name.max' => 'Last name cannot exceed 50 characters.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, and apostrophes.',

            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',

            'phone.unique' => 'This phone number is already registered.',
            'phone.regex' => 'Please enter a valid phone number format.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
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
            'password' => 'password',
            'phone' => 'phone number',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all string inputs
        $this->merge([
            'email' => trim(strtolower($this->email)),
            'first_name' => trim($this->first_name),
            'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
            'last_name' => trim($this->last_name),
            'phone' => $this->phone ? trim($this->phone) : null,
        ]);
    }
}
