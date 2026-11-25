<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function acknowledge(Request $request, Alert $alert): JsonResponse
    {
        abort_unless($request->user()->canSeeRegion($alert->region_id), 403);

        if ($alert->status !== 'acknowledged') {
            $alert->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $request->user()->id,
                'acknowledged_at' => now(),
            ]);
            AuditLog::record($request, 'alert_acknowledged', ['alert_id' => $alert->id]);
        }

        return response()->json([
            'id' => $alert->id,
            'status' => $alert->status,
            'acknowledged_by' => $alert->acknowledgedBy?->name,
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
        ]);
    }
}
