<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scope',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];
}
