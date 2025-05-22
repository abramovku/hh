<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Response extends Model
{
    /**
     * @var string
     */
    protected $table = 'responses';

    /**
     * @var array
     */
    protected $fillable = [
        'response_id',
        'vacancy_id',
        'manager_id',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function meta(): HasMany
    {
        return $this->hasMany(HHMeta::class, 'response_id');
    }
}
