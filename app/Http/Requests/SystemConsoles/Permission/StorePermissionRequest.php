<?php

namespace App\Http\Requests\SystemConsoles\Permission;

use App\Http\Requests\BaseFormRequest;

class StorePermissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:permissions,slug',
            'action'      => 'required|string|max:50',
            'resource'    => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_system'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Permission name is required.',
            'name.max'          => 'Permission name must not exceed 255 characters.',
            'action.required'   => 'Action is required (e.g. view, create, edit, delete).',
            'resource.required' => 'Resource is required (e.g. users, roles).',
            'slug.unique'       => 'This slug is already taken.',
        ];
    }
}
