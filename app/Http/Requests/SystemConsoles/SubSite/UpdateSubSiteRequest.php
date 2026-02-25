<?php

namespace App\Http\Requests\SystemConsoles\SubSite;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateSubSiteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'plant_id'             => 'sometimes|integer|exists:plants,id',
            'sub_site_code'        => ['sometimes', 'string', 'max:50', Rule::unique('sub_sites', 'sub_site_code')->ignore($id)->whereNull('deleted_at')],
            'sub_site_name'        => ['sometimes', 'string', 'max:255', Rule::unique('sub_sites', 'sub_site_name')->ignore($id)->whereNull('deleted_at')],
            'sub_site_slug'        => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('sub_sites', 'sub_site_slug')->ignore($id)->whereNull('deleted_at')],
            'status'               => 'sometimes|nullable|string|in:active,inactive,maintenance',
            'sub_site_description' => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'plant_id.exists'      => 'The selected plant does not exist.',
            'sub_site_code.unique' => 'This sub-site code already exists.',
            'sub_site_name.unique' => 'A sub-site with this name already exists.',
            'status.in'            => 'Status must be one of: active, inactive, maintenance.',
        ];
    }
}
