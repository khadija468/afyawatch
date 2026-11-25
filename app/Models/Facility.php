<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $fillable = ['region_id', 'code', 'name', 'level'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function weeklyReports(): HasMany
    {
        return $this->hasMany(WeeklyCaseReport::class);
    }
}
