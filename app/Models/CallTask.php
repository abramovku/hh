<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallTask extends Model
{
    /**
     * @var string
     */
    protected $table = 'call_tasks';
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        'twin_id',
        'type',
        'date'
    ];
}
