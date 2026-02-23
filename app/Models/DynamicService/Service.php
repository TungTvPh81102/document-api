<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'svc_code',
        'business_category_id',
        'form_template_id',
        'current_form_version_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'business_category_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'current_form_version_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ServiceTranslation::class);
    }

    /**
     * Get title for a specific locale.
     */
    public function getTitle(string $locale = 'en'): ?string
    {
        return $this->translations()->where('locale', $locale)->first()?->title;
    }
}
