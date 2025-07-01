<?php

namespace App\Models;

use Carbon\Carbon;
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
        'sent_at',
        'vacancy_estaff',
        'candidate_estaff',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function meta(): HasMany
    {
        return $this->hasMany(HHMeta::class, 'response_id');
    }

    public function setSend(): void
    {
        $this->sent_at = Carbon::now();
    }
}
