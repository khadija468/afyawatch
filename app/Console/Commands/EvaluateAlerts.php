<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Services\AlertEvaluator;
use App\Support\DashboardFilters;
use App\Support\Week;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    protected $signature = 'alerts:evaluate
        {--week= : ISO week to check, e.g. 2026-W47 (defaults to the latest reported week)}
        {--no-email : Create alerts without sending any email}';

    protected $description = 'Check weekly case totals against the alert rules and email the people who need to know';

    public function handle(AlertEvaluator $evaluator): int
    {
        $week = $this->option('week') ? Week::parse($this->option('week')) : DashboardFilters::latestWeek();
        $alerts = $evaluator->evaluate($week, ! $this->option('no-email'));

        $this->info(sprintf('%s: %d new alert(s).', Week::label($week), $alerts->count()));

        if ($alerts->isNotEmpty()) {
            $this->table(
                ['Condition', 'Region', 'Cases', 'Limit', 'Severity', 'Email'],
                $alerts->map(fn (Alert $a) => [
                    $a->condition->name,
                    $a->region->name,
                    $a->observed,
                    $a->limit_value,
                    $a->severity,
                    $a->emailed_at ? 'sent' : $a->email_skipped_reason,
                ]),
            );
        }

        return self::SUCCESS;
    }
}
