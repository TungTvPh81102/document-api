<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CimSqlLog extends Model
{
    use HasUuids;

    protected $table = 'cim_sql_log';

    protected $fillable = [
        'sql_text',
        'sql_params',
        'operation',
        'duration_ms',
        'executed_by',
        'user_id',
        'module',
        'ip_address',
        'user_agent',
        'is_error',
        'error_message',
    ];

    protected $casts = [
        'sql_params' => 'array',
        'is_error' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    public function scopeSlowQueries($query, int $threshold = 1000)
    {
        return $query->where('duration_ms', '>', $threshold);
    }

    public function scopeByOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}