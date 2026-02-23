<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('X-Locale', 'en');

        return [
            'field_key'            => $this->field_key,
            'label'                => $this->getLabel($locale),
            'field_type'           => $this->field_type,
            'is_required'          => $this->is_required,
            'display_order'        => $this->display_order,
            'options'              => $this->options_json,
            'validation'           => $this->validation_json,
            'visibility_rules'     => $this->visibility_rule_json,
        ];
    }
}
