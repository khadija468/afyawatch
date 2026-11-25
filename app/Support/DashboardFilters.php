<?php

namespace App\Support;

use App\Models\Condition;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The filters a dashboard request runs with, after role scoping has been applied.
 * A clinician's region is fixed on the server, whatever the browser sends.
 */
final class DashboardFilters
{
    public const DEFAULT_WEEKS = 12;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly Condition $condition,
        public readonly ?Region $region = null,
        public readonly ?Facility $facility = null,
    ) {
    }

    /**
     * @param  array{from?: ?string, to?: ?string, condition_id?: ?int, region_id?: ?int, facility_id?: ?int}  $input  validated input
     */
    public static function make(array $input, User $user): self
    {
        $latest = self::latestWeek();
        $to = isset($input['to']) ? Week::start($input['to']) : $latest;
        $from = isset($input['from']) ? Week::start($input['from']) : $to->subWeeks(self::DEFAULT_WEEKS - 1);

        $condition = isset($input['condition_id'])
            ? Condition::findOrFail($input['condition_id'])
            : (Condition::where('icd10_code', config('afyawatch.default_condition'))->first() ?? Condition::firstOrFail());

        $facility = isset($input['facility_id']) ? Facility::findOrFail($input['facility_id']) : null;
        $regionId = $input['region_id'] ?? $facility?->region_id;

        if ($user->scopedRegionId() !== null) {
            abort_if($regionId !== null && (int) $regionId !== $user->scopedRegionId(), 403, 'You can only view your own region.');
            $regionId = $user->scopedRegionId();
        }

        abort_if($facility && $regionId && $facility->region_id !== (int) $regionId, 422, 'The facility is not in the selected region.');

        return new self($from, $to, $condition, $regionId ? Region::findOrFail($regionId) : null, $facility);
    }

    public static function latestWeek(): CarbonImmutable
    {
        $latest = DB::table('weekly_case_reports')->max('week_start');

        return Week::start($latest ?? now());
    }

    public function weeks(): array
    {
        return Week::range($this->from, $this->to);
    }

    public function weekCount(): int
    {
        return count($this->weeks());
    }

    public function scopeLabel(): string
    {
        return $this->facility?->name ?? $this->region?->name ?? 'Tanzania Mainland';
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'from_label' => Week::label($this->from),
            'to_label' => Week::label($this->to),
            'weeks' => $this->weekCount(),
            'condition_id' => $this->condition->id,
            'region_id' => $this->region?->id,
            'facility_id' => $this->facility?->id,
            'scope_label' => $this->scopeLabel(),
        ];
    }
}
