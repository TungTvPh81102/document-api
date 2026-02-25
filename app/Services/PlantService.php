<?php

namespace App\Services;

use App\Models\Plant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlantService
{
    /**
     * Get all plants paginated.
     */
    public function getAllPlants(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Plant::query()
            ->with(['site'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search plants.
     */
    public function searchPlants(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Plant::query()
            ->with(['site'])
            ->where('plant_name', 'like', "%{$query}%")
            ->orWhere('plant_code', 'like', "%{$query}%")
            ->orWhere('plant_description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get plant by ID.
     */
    public function getPlantById(int|string $id): ?Plant
    {
        return Plant::query()
            ->with(['site', 'subSites'])
            ->find($id);
    }

    /**
     * Get plants by site.
     */
    public function getPlantsBySite(int $siteId, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Plant::query()
            ->with(['site'])
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create plant.
     */
    public function createPlant(array $data): Plant
    {
        if (empty($data['plant_slug'])) {
            $data['plant_slug'] = Str::slug($data['plant_name']);
        }
        $data['created_by'] = Auth::id() ?? 'system';

        return Plant::query()->create($data);
    }

    /**
     * Update plant.
     */
    public function updatePlant(Plant $plant, array $data): Plant
    {
        if (isset($data['plant_name']) && empty($data['plant_slug'])) {
            $data['plant_slug'] = Str::slug($data['plant_name']);
        }
        $data['updated_by'] = Auth::id() ?? 'system';

        $plant->update($data);
        return $plant->fresh(['site', 'subSites']);
    }

    /**
     * Delete plant (soft delete).
     */
    public function deletePlant(Plant $plant): bool
    {
        $plant->deleted_by = Auth::id() ?? 'system';
        $plant->save();

        return $plant->delete();
    }
}
