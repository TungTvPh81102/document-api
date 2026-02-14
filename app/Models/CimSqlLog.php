<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CimSqlLog extends Model
{
    use HasUuids;

    protected $table = 'cim_sql_log';

    protected $fillable = [
        'request_id',
        'level',
        'service',
        'method',
        'url',
        'route',
        'status_code',
        'function_name',
        'logic_name',
        'parameters',
        'response_data',
        'fail_result',
        'start_time',
        'end_time',
        'duration_ms',
        'user_id',
        'ip_address',
        'user_agent',
        'is_error',
        'message',
    ];

    protected $casts = [
        'parameters'  => 'array',
        'is_error'    => 'boolean',
        'duration_ms' => 'float',
        'status_code' => 'integer',
        'start_time'  => 'datetime',
        'end_time'    => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    public function scopeSlowRequests($query, int $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByRoute($query, string $route)
    {
        return $query->where('route', 'like', "%{$route}%");
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByTraceId($query, string $traceId)
    {
        return $query->where('request_id', $traceId);
    }
}
