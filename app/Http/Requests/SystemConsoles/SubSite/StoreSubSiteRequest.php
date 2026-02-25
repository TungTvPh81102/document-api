<?php

namespace App\Http\Requests\SystemConsoles\SubSite;

use App\Http\Requests\BaseFormRequest;

class StoreSubSiteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plant_id'             => 'required|integer|exists:plants,id',
            'sub_site_code'        => 'required|string|max:50|unique:sub_sites,sub_site_code,NULL,id,deleted_at,NULL',
            'sub_site_name'        => 'required|string|max:255|unique:sub_sites,sub_site_name,NULL,id,deleted_at,NULL',
            'sub_site_slug'        => 'nullable|string|max:255|unique:sub_sites,sub_site_slug,NULL,id,deleted_at,NULL',
            'status'               => 'nullable|string|in:active,inactive,maintenance',
            'sub_site_description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'plant_id.required'       => 'Plant is required.',
            'plant_id.exists'         => 'The selected plant does not exist.',
            'sub_site_code.required'  => 'Sub-site code is required.',
            'sub_site_code.unique'    => 'This sub-site code already exists.',
            'sub_site_name.required'  => 'Sub-site name is required.',
            'sub_site_name.unique'    => 'A sub-site with this name already exists.',
            'sub_site_slug.unique'    => 'This slug is already taken.',
            'status.in'               => 'Status must be one of: active, inactive, maintenance.',
        ];
    }
}
