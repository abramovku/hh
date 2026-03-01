<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactEvent extends Model
{
    protected $fillable = ['response_id', 'type'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }
}
