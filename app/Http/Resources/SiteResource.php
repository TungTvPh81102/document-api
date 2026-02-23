<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'code'        => $this->code,
            'slug'        => $this->slug,
            'status'      => $this->status,
            'description' => $this->description,
            'location'    => $this->location,
            'timezone'    => $this->timezone,
            'created_by'  => $this->created_by,
            'plants'      => PlantResource::collection($this->whenLoaded('plants')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
