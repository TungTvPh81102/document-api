<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SiteService
{
    public function getAllSites(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Site::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function searchSites(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Site::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getSiteById(string $id): ?Site
    {
        return Site::query()->find($id);
    }

    public function getSiteByCode(string $code): ?Site
    {
        return Site::query()->where($code)->first();
    }

    public function createSite(array $data): Site
    {
        $data['slug']       = $data['slug'] ?? Str::slug($data['name']);
        $data['created_by'] = Auth::id() ?? 'system';

        return Site::query()->create($data);
    }

    public function updateSite(Site $site, array $data): Site
    {
        $data['updated_by'] = Auth::id() ?? 'system';
        $site->update($data);

        return $site;
    }

    public function deleteSite(Site $site): bool
    {
        $site->deleted_by = Auth::id() ?? 'system';
        $site->save();

        return $site->delete();
    }
}
