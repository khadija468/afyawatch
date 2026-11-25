<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    public const FIXED = 'fixed';
    public const STATISTICAL = 'statistical';

    protected $fillable = [
        'condition_id', 'rule_type', 'threshold', 'min_cases', 'baseline_weeks', 'severity', 'active',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'active' => 'boolean',
        ];
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
