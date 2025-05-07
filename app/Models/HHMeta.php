<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HHMeta extends Model
{
    public const MINIBOX = 'minibox';
    /**
     * @var string
     */
    protected $table = 'hh_meta';

    public $timestamps = false;

    protected $fillable = [
        'response_id',
        'key',
        'value',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }
}
