<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'field_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
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
