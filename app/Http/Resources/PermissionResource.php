<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'guard_name'  => $this->guard_name,
            'slug'        => $this->slug,
            'action'      => $this->action,
            'resource'    => $this->resource,
            'description' => $this->description,
            'is_system'   => $this->is_system,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
