<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'minimum_version',
        'latest_version',
        'force_update',
        'update_url',
        'update_message',
    ];

    protected $casts = [
        'force_update' => 'boolean',
    ];
}