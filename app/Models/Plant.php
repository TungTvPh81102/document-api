<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *   schema="Plant",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="site_id", type="integer", example=1),
 *   @OA\Property(property="plant_code", type="string", example="PLT-001"),
 *   @OA\Property(property="plant_name", type="string", example="Plant Alpha"),
 *   @OA\Property(property="plant_slug", type="string", example="plant-alpha"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="plant_description", type="string", example="Production plant A"),
 *   @OA\Property(property="site", ref="#/components/schemas/Site"),
 *   @OA\Property(property="sub_sites", type="array", @OA\Items(ref="#/components/schemas/SubSite")),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Plant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'plant_code',
        'plant_name',
        'plant_slug',
        'status',
        'plant_description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'site_id'    => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function subSites(): HasMany
    {
        return $this->hasMany(SubSite::class, 'plant_id');
    }
}
