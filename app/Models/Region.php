<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['iso_code', 'name', 'population_2022', 'tile_col', 'tile_row'];

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
