<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'field_id',
        'value_text',
        'value_number',
        'value_date',
        'value_json',
    ];

    protected $casts = [
        'value_number' => 'decimal:2',
        'value_date'   => 'date',
        'value_json'   => 'array',
    ];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }
}
