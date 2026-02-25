<?php

namespace App\Http\Requests\SystemConsoles\Plant;

use App\Http\Requests\BaseFormRequest;

class StorePlantRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'site_id'           => 'required|integer|exists:sites,id',
            'plant_code'        => 'required|string|max:50|unique:plants,plant_code,NULL,id,deleted_at,NULL',
            'plant_name'        => 'required|string|max:255|unique:plants,plant_name,NULL,id,deleted_at,NULL',
            'plant_slug'        => 'nullable|string|max:255|unique:plants,plant_slug,NULL,id,deleted_at,NULL',
            'status'            => 'nullable|string|in:active,inactive,maintenance',
            'plant_description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required'    => 'Site is required.',
            'site_id.exists'      => 'The selected site does not exist.',
            'plant_code.required' => 'Plant code is required.',
            'plant_code.unique'   => 'This plant code already exists.',
            'plant_name.required' => 'Plant name is required.',
            'plant_name.unique'   => 'A plant with this name already exists.',
            'plant_slug.unique'   => 'This slug is already taken.',
            'status.in'           => 'Status must be one of: active, inactive, maintenance.',
        ];
    }
}
