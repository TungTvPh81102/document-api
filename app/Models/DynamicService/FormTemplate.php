<?php

namespace App\Models\DynamicService;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'template_category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(FormTemplateVersion::class)->latestOfMany('version_no');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
