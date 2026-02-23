<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('X-Locale', 'en');

        return [
            'id'             => $this->id,
            'svc_code'       => $this->svc_code,
            'title'          => $this->getTitle($locale) ?? $this->getTitle('en') ?? 'Unknown',
            'status'         => $this->status,
            'current_form'   => $this->whenLoaded('currentVersion', function() use ($locale) {
                return [
                    'version_no'   => $this->currentVersion->version_no,
                    'published_at' => $this->currentVersion->published_at,
                    'fields'       => FormFieldResource::collection($this->currentVersion->fields),
                ];
            }),
            'category'       => new ServiceCategoryResource($this->whenLoaded('businessCategory')),
        ];
    }
}
