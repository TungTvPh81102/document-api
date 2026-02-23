<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *   schema="Permission",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="site.view"),
 *   @OA\Property(property="guard_name", type="string", example="web"),
 *   @OA\Property(property="slug", type="string", example="site-view"),
 *   @OA\Property(property="action", type="string", example="view"),
 *   @OA\Property(property="resource", type="string", example="site"),
 *   @OA\Property(property="description", type="string", example="View sites"),
 *   @OA\Property(property="is_system", type="boolean", example=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Permission extends \Spatie\Permission\Models\Permission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'slug',
        'action',
        'resource',
        'description',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: Get permissions by resource
     */
    public function scopeByResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    /**
     * Scope: Get permissions by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
