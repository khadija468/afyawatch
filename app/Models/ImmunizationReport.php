<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmunizationReport extends Model
{
    protected $fillable = ['facility_id', 'month', 'vaccine', 'doses'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
