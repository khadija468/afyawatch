<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Condition;
use Illuminate\Database\Seeder;

class ConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            // name, ICD-10 (WHO 2019), SNOMED CT, case basis, alert rule
            ['Cholera', 'A00', '63650001', 'suspected', ['fixed', 1, 1, 'critical']],
            ['Measles', 'B05', '14189004', 'suspected', ['fixed', 5, 5, 'warning']],
            ['Malaria', 'B54', '61462000', 'confirmed', ['statistical', 3, 10, 'warning']],
            ['Typhoid fever', 'A01.0', '4834000', 'suspected', ['statistical', 3, 5, 'warning']],
            ['Dengue', 'A97', '38362002', 'suspected', ['statistical', 3, 5, 'warning']],
            ['Bacterial meningitis', 'G00', '95883001', 'suspected', ['fixed', 3, 3, 'warning']],
        ];

        foreach ($conditions as [$name, $icd10, $snomed, $basis, $rule]) {
            $condition = Condition::updateOrCreate(
                ['icd10_code' => $icd10],
                ['name' => $name, 'snomed_code' => $snomed, 'case_basis' => $basis],
            );

            [$type, $threshold, $minCases, $severity] = $rule;
            AlertRule::updateOrCreate(
                ['condition_id' => $condition->id, 'rule_type' => $type, 'severity' => $severity],
                ['threshold' => $threshold, 'min_cases' => $minCases, 'baseline_weeks' => 8, 'active' => true],
            );
        }
    }
}
