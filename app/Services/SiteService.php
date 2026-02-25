<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SiteService
{
    /**
     * Get all sites paginated.
     */
    public function getAllSites(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Site::query()
            ->withCount(['plants'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search sites.
     */
    public function searchSites(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Site::query()
            ->withCount(['plants'])
            ->where('name', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get site by ID.
     */
    public function getSiteById(int|string $id): ?Site
    {
        return Site::query()
            ->with(['plants.subSites'])
            ->find($id);
    }

    /**
     * Create site.
     */
    public function createSite(array $data): Site
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $data['created_by'] = Auth::id() ?? 'system';

        return Site::query()->create($data);
    }

    /**
     * Update site.
     */
    public function updateSite(Site $site, array $data): Site
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $data['updated_by'] = Auth::id() ?? 'system';

        $site->update($data);
        return $site->fresh(['plants']);
    }

    /**
     * Delete site (soft delete).
     */
    public function deleteSite(Site $site): bool
    {
        $site->deleted_by = Auth::id() ?? 'system';
        $site->save();

        return $site->delete();
    }
}
