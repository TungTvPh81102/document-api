<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *   schema="SubSite",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="plant_id", type="integer", example=1),
 *   @OA\Property(property="sub_site_code", type="string", example="SS-001"),
 *   @OA\Property(property="sub_site_name", type="string", example="SubSite Alpha"),
 *   @OA\Property(property="sub_site_slug", type="string", example="subsite-alpha"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="sub_site_description", type="string", example="Sub area A"),
 *   @OA\Property(property="plant", ref="#/components/schemas/Plant"),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SubSite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plant_id',
        'sub_site_code',
        'sub_site_name',
        'sub_site_slug',
        'status',
        'sub_site_description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'plant_id'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
