<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
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
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|email|exists:users,email',
            'employee_id' => 'nullable|string|max:50|exists:users,employee_id',
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Email format is invalid.',
            'email.exists' => 'This email is not registered.',

            'employee_id.string' => 'Employee ID must be a string.',
            'employee_id.exists' => 'This Employee ID does not exist.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }

    protected function prepareForValidation()
    {
        if (!$this->email && !$this->employee_id) {
            $this->merge(['email' => null]);
        }
    }
}
