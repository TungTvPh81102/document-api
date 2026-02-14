<?php

namespace App\Http\Requests\SystemConsoles\User;

use App\Http\Requests\BaseFormRequest;

class StoreUserRequest extends BaseFormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'enabled' => 'nullable|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {

        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.max' => 'Name must not exceed 255 characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Email format is invalid.',
            'email.unique' => 'This email is already in use.',
            'email.max' => 'Email must not exceed 255 characters.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',

            'password_confirmation.required' => 'Please confirm your password.',
            'password_confirmation.string' => 'Password confirmation must be a string.',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters.',

            'phone.string' => 'Phone number must be a string.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'phone.unique' => 'This phone number is already in use.',

            'date_of_birth.date' => 'Date of birth must be a valid date format (YYYY-MM-DD).',

            'gender.in' => 'Gender must be one of: male, female, other.',

            'avatar.file' => 'Avatar file must be a valid file.',
            'avatar.image' => 'Avatar must be an image.',
            'avatar.mimes' => 'Avatar accepted formats: jpeg, png, jpg, gif, svg.',
            'avatar.max' => 'Avatar exceeds allowed capacity (max 2MB).',

            'enabled.boolean' => 'Enable status must be a boolean (true/false).',
        ];
    }
}
