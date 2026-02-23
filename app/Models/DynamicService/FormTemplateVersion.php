<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_template_id',
        'version_no',
        'schema_json',
        'status',
        'published_at',
    ];

    protected $casts = [
        'schema_json'  => 'array',
        'published_at' => 'datetime',
    ];

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_version_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'form_version_id');
    }
}
