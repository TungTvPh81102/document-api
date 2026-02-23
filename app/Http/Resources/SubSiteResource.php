<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'plant_id'             => $this->plant_id,
            'sub_site_code'        => $this->sub_site_code,
            'sub_site_name'        => $this->sub_site_name,
            'sub_site_slug'        => $this->sub_site_slug,
            'status'               => $this->status,
            'sub_site_description' => $this->sub_site_description,
            'created_by'           => $this->created_by,
            'plant'                => new PlantResource($this->whenLoaded('plant')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
