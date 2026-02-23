<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *   schema="Site",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Main Site"),
 *   @OA\Property(property="code", type="string", example="SITE-001"),
 *   @OA\Property(property="slug", type="string", example="main-site"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="description", type="string", example="Primary production site"),
 *   @OA\Property(property="location", type="string", example="Ho Chi Minh City"),
 *   @OA\Property(property="timezone", type="string", example="Asia/Ho_Chi_Minh"),
 *   @OA\Property(property="plants", type="array", @OA\Items(ref="#/components/schemas/Plant")),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'status',
        'description',
        'location',
        'timezone',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function plants(): HasMany
    {
        return $this->hasMany(Plant::class, 'site_id');
    }
}
