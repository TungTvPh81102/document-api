<?php

namespace App\Http\Requests\SystemConsoles\Site;

use App\Http\Requests\BaseFormRequest;

class StoreSiteRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255|unique:sites,name,NULL,id,deleted_at,NULL',
            'code'        => 'required|string|max:50|alpha_dash|unique:sites,code,NULL,id,deleted_at,NULL',
            'slug'        => 'required|string|max:255|alpha_dash|unique:sites,slug,NULL,id,deleted_at,NULL',
            'status'      => 'nullable|in:0,1',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Site name is required.',
            'name.string'     => 'Site name must be a string.',
            'name.max'        => 'Site name must not exceed 255 characters.',
            'name.unique'     => 'This site name already exists.',

            'code.required'   => 'Site code is required.',
            'code.string'     => 'Site code must be a string.',
            'code.max'        => 'Site code must not exceed 50 characters.',
            'code.alpha_dash' => 'Site code may only contain letters, numbers, dashes, and underscores.',
            'code.unique'     => 'This site code already exists.',

            'slug.required'   => 'Slug is required.',
            'slug.string'     => 'Slug must be a string.',
            'slug.max'        => 'Slug must not exceed 255 characters.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes, and underscores.',
            'slug.unique'     => 'This slug already exists.',

            'status.in'       => 'Status must be either 0 (inactive) or 1 (active).',

            'description.string' => 'Description must be a string.',
            'description.max'    => 'Description must not exceed 1000 characters.',
        ];
    }
}
