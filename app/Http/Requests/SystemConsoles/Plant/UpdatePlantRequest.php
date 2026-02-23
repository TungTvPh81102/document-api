<?php

namespace App\Http\Requests\SystemConsoles\Plant;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'site_id'           => 'sometimes|integer|exists:sites,id',
            'plant_code'        => ['sometimes', 'string', 'max:50', Rule::unique('plants', 'plant_code')->ignore($id)->whereNull('deleted_at')],
            'plant_name'        => ['sometimes', 'string', 'max:255', Rule::unique('plants', 'plant_name')->ignore($id)->whereNull('deleted_at')],
            'plant_slug'        => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('plants', 'plant_slug')->ignore($id)->whereNull('deleted_at')],
            'status'            => 'sometimes|nullable|string|in:active,inactive,maintenance',
            'plant_description' => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.exists'    => 'The selected site does not exist.',
            'plant_code.unique' => 'This plant code already exists.',
            'plant_name.unique' => 'A plant with this name already exists.',
            'status.in'         => 'Status must be one of: active, inactive, maintenance.',
        ];
    }
}
