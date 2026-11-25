<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'details', 'ip_address'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(Request $request, string $action, array $details = []): self
    {
        return static::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'details' => $details ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
}
