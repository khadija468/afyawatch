<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_MANAGER = 'manager';
    public const ROLE_CLINICIAN = 'clinician';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'region_id',
        'alert_preference',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * The region a user is limited to, or null when they may see the whole country.
     */
    public function scopedRegionId(): ?int
    {
        return $this->isManager() ? null : $this->region_id;
    }

    public function canSeeRegion(int $regionId): bool
    {
        return $this->isManager() || $this->region_id === $regionId;
    }
}
