<?php

namespace App\Http\Requests\SystemConsoles\Permission;

use App\Http\Requests\BaseFormRequest;

class UpdatePermissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('id');

        return [
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|unique:permissions,slug,' . $permissionId,
            'action'      => 'sometimes|string|max:50',
            'resource'    => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_system'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'    => 'Permission name must not exceed 255 characters.',
            'slug.unique' => 'This slug is already taken.',
        ];
    }
}
