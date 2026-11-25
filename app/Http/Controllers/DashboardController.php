<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Models\Condition;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use App\Services\SurveillanceStats;
use App\Support\DashboardFilters;
use App\Support\Week;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SurveillanceStats $stats): View
    {
        $user = $request->user();
        $regionId = $user->scopedRegionId();
        $latest = DashboardFilters::latestWeek();

        $weeks = DB::table('weekly_case_reports')->distinct()->orderBy('week_start')->pluck('week_start')
            ->map(fn ($week) => ['value' => substr($week, 0, 10), 'label' => Week::label($week)]);

        $requestedCondition = (int) $request->query('condition_id');
        $requestedRegion = (int) $request->query('region_id');

        $bootstrap = [
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'region_id' => $regionId,
                'region_name' => $user->region?->name,
            ],
            'conditions' => Condition::orderBy('name')->get(['id', 'name', 'icd10_code', 'case_basis']),
            'regions' => Region::when($regionId, fn ($q) => $q->whereKey($regionId))->orderBy('name')->get(['id', 'name']),
            'facilities' => Facility::when($regionId, fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')->get(['id', 'name', 'region_id', 'level']),
            'weeks' => $weeks,
            'defaults' => [
                'to' => $latest->toDateString(),
                'from' => $latest->subWeeks(DashboardFilters::DEFAULT_WEEKS - 1)->toDateString(),
                'condition_id' => Condition::whereKey($requestedCondition)->exists()
                    ? $requestedCondition
                    : Condition::where('icd10_code', config('afyawatch.default_condition'))->value('id'),
                'region_id' => $regionId ?? (Region::whereKey($requestedRegion)->exists() ? $requestedRegion : null),
            ],
            'targets' => [
                'coverage' => config('afyawatch.coverage_target'),
                'completeness' => config('afyawatch.completeness_target'),
            ],
            // The first set of figures travels with the page, saving a round trip on slow connections.
            'initial' => $this->initialData($request, $user, $stats),
        ];

        return view('dashboard', ['bootstrap' => $bootstrap]);
    }

    private function initialData(Request $request, User $user, SurveillanceStats $stats): ?array
    {
        try {
            $validated = Validator::make($request->query(), (new DashboardRequest)->rules())->validate();

            return $stats->dashboard(DashboardFilters::make(DashboardRequest::normalise($validated), $user), $user);
        } catch (ValidationException|HttpException) {
            return null; // the page will ask the API, which reports the problem properly
        }
    }
}
