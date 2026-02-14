<?php

namespace App\Http\Requests\SystemConsoles\Role;

use App\Http\Requests\BaseFormRequest;

class StoreRoleRequest extends BaseFormRequest
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
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,deleted_at,NULL',
            'slug' => 'required|string|max:255|alpha_dash|unique:roles,slug,NULL,id,deleted_at,NULL',
            'description' => 'nullable|string|max:1000',
            'is_system' => 'required|boolean',
            'level' => 'nullable|integer|min:0',
            'enabled' => 'nullable|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required.',
            'name.string' => 'Role name must be a string.',
            'name.max' => 'Role name must not exceed 255 characters.',
            'name.unique' => 'This role name already exists.',

            'slug.required' => 'Slug is required.',
            'slug.string' => 'Slug must be a string.',
            'slug.max' => 'Slug must not exceed 255 characters.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes, and underscores.',
            'slug.unique' => 'This slug already exists.',

            'description.string' => 'Description must be a string.',
            'description.max' => 'Description must not exceed 1000 characters.',

            'level.integer' => 'Level must be an integer.',
            'level.min' => 'Level must be at least 0.',

            'enabled.boolean' => 'Enable status must be a boolean (true/false).',
        ];
    }
}
