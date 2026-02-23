<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubSite extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'site_id',
        'sub_site_code',
        'sub_site_name',
        'sub_site_slug',
        'status',
        'sub_site_description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
}
