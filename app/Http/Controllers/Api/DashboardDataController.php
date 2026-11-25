<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Services\SurveillanceStats;
use Illuminate\Http\JsonResponse;

class DashboardDataController extends Controller
{
    public function __invoke(DashboardRequest $request, SurveillanceStats $stats): JsonResponse
    {
        return response()->json($stats->dashboard($request->filters(), $request->user()));
    }
}
