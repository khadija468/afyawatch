<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    /** Every region gets the same synthetic mix: one hospital, two health centres, three dispensaries. */
    private const MIX = [
        ['hospital', 'Hospital', 'H', 1],
        ['health_centre', 'Health Centre', 'C', 2],
        ['dispensary', 'Dispensary', 'D', 3],
    ];

    public function run(): void
    {
        foreach (Region::orderBy('name')->get() as $region) {
            $prefix = Str::upper(Str::substr(Str::before($region->name, ' '), 0, 3));

            foreach (self::MIX as [$level, $label, $letter, $count]) {
                for ($i = 1; $i <= $count; $i++) {
                    Facility::updateOrCreate(
                        ['code' => sprintf('%s-%s%02d', $prefix, $letter, $i)],
                        ['region_id' => $region->id, 'name' => "{$region->name} {$label} {$i}", 'level' => $level],
                    );
                }
            }
        }
    }
}
