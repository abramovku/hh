<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwinTask extends Model
{
    /**
     * @var string
     */
    protected $table = 'twin_tasks';

    /**
     * @var array
     */
    protected $fillable = [
        'chat_id',
        'job_id',
        'candidate_id',
    ];

}
