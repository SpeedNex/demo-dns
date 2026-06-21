<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';

    protected $fillable = [
        'code',
        'name',
        'status',
        'note',
    ];
}
