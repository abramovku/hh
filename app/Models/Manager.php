<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'managers';

    protected $fillable = [
        'hh_id',
        'email',
    ];
}
