<?php

namespace App\Models\DynamicService;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'service_id',
        'form_version_id',
        'requester_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'form_version_id');
    }

    // Assuming requester_id links to a User model (or similar)
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ServiceRequestAnswer::class, 'request_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceRequestAttachment::class, 'request_id');
    }
}
