<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'site_id'           => $this->site_id,
            'plant_code'        => $this->plant_code,
            'plant_name'        => $this->plant_name,
            'plant_slug'        => $this->plant_slug,
            'status'            => $this->status,
            'plant_description' => $this->plant_description,
            'created_by'        => $this->created_by,
            'site'              => new SiteResource($this->whenLoaded('site')),
            'sub_sites'         => SubSiteResource::collection($this->whenLoaded('subSites')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
