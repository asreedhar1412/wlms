<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    //
    protected $fillable=[
        'date',
        'time',
        'schedule',
        'coolers_shipped'
    ];

    protected $casts = [
        'schedule' => 'array',
    ];
}
