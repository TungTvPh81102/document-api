<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'resource',
        'action',
        'resource_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Permission belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    /**
     * Scope: Get permissions by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
