<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Condition extends Model
{
    protected $fillable = ['name', 'icd10_code', 'snomed_code', 'case_basis'];

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }
}
