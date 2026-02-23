<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'sub_site_id',
        'plant_code',
        'plant_name',
        'plant_slug',
        'status',
        'plant_description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
}
