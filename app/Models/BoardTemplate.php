<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'icon',
        'description',
        'columns',
        'status_ids',
        'labels',
        'position',
    ];

    protected $casts = [
        'columns' => 'array',
        'status_ids' => 'array',
        'labels' => 'array',
        'position' => 'integer',
    ];
}
