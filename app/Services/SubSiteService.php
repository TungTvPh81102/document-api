<?php

namespace App\Services;

use App\Models\SubSite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubSiteService
{
    /**
     * Get all sub-sites paginated.
     */
    public function getAllSubSites(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return SubSite::query()
            ->with(['plant.site'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search sub-sites.
     */
    public function searchSubSites(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return SubSite::query()
            ->with(['plant.site'])
            ->where('sub_site_name', 'like', "%{$query}%")
            ->orWhere('sub_site_code', 'like', "%{$query}%")
            ->orWhere('sub_site_description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get sub-site by ID.
     */
    public function getSubSiteById(int|string $id): ?SubSite
    {
        return SubSite::query()
            ->with(['plant.site'])
            ->find($id);
    }

    /**
     * Get sub-sites by plant.
     */
    public function getSubSitesByPlant(int $plantId, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return SubSite::query()
            ->with(['plant.site'])
            ->where('plant_id', $plantId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create sub-site.
     */
    public function createSubSite(array $data): SubSite
    {
        if (empty($data['sub_site_slug'])) {
            $data['sub_site_slug'] = Str::slug($data['sub_site_name']);
        }
        $data['created_by'] = Auth::id() ?? 'system';

        return SubSite::query()->create($data);
    }

    /**
     * Update sub-site.
     */
    public function updateSubSite(SubSite $subSite, array $data): SubSite
    {
        if (isset($data['sub_site_name']) && empty($data['sub_site_slug'])) {
            $data['sub_site_slug'] = Str::slug($data['sub_site_name']);
        }
        $data['updated_by'] = Auth::id() ?? 'system';

        $subSite->update($data);
        return $subSite->fresh(['plant.site']);
    }

    /**
     * Delete sub-site (soft delete).
     */
    public function deleteSubSite(SubSite $subSite): bool
    {
        $subSite->deleted_by = Auth::id() ?? 'system';
        $subSite->save();

        return $subSite->delete();
    }
}
