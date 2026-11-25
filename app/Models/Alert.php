<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'alert_rule_id', 'condition_id', 'region_id', 'week_start', 'observed', 'expected',
        'limit_value', 'severity', 'status', 'acknowledged_by', 'acknowledged_at', 'emailed_at',
        'email_skipped_reason',
    ];

    protected function casts(): array
    {
        return [
            'expected' => 'float',
            'limit_value' => 'float',
            'acknowledged_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
