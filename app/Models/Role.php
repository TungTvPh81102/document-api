<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *   schema="Role",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="admin"),
 *   @OA\Property(property="guard_name", type="string", example="web"),
 *   @OA\Property(property="description", type="string", example="Administrator role"),
 *   @OA\Property(property="is_system", type="boolean", example=false),
 *   @OA\Property(property="level", type="integer", example=1),
 *   @OA\Property(property="enabled", type="boolean", example=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Role extends \Spatie\Permission\Models\Role
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'guard_name',
        'slug',
        'description',
        'is_system',
        'level',
        'enabled',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'enabled'    => 'boolean',
        'level'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
