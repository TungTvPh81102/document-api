<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'field_key',
        'label_default',
        'field_type',
        'is_required',
        'display_order',
        'options_source',
        'options_json',
        'validation_json',
        'visibility_rule_json',
    ];

    protected $casts = [
        'is_required'          => 'boolean',
        'options_json'         => 'array',
        'validation_json'      => 'array',
        'visibility_rule_json' => 'array',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'form_version_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FormFieldTranslation::class, 'field_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ServiceRequestAnswer::class, 'field_id');
    }

    /**
     * Get label for a specific locale.
     */
    public function getLabel(string $locale = 'en'): string
    {
        return $this->translations()->where('locale', $locale)->first()?->label ?? $this->label_default;
    }
}
