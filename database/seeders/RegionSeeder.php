<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use RuntimeException;

class RegionSeeder extends Seeder
{
    /**
     * Position of each region on the dashboard's tile map (column, row).
     * Tiles are equal in size so large, sparsely populated regions do not dominate the picture.
     */
    private const TILES = [
        'Kagera' => [0, 0], 'Mwanza' => [1, 0], 'Mara' => [2, 0], 'Arusha' => [3, 0], 'Kilimanjaro' => [4, 0],
        'Geita' => [0, 1], 'Shinyanga' => [1, 1], 'Simiyu' => [2, 1], 'Manyara' => [3, 1], 'Tanga' => [4, 1],
        'Kigoma' => [0, 2], 'Tabora' => [1, 2], 'Singida' => [2, 2], 'Dodoma' => [3, 2], 'Pwani' => [4, 2], 'Dar es Salaam' => [5, 2],
        'Katavi' => [0, 3], 'Songwe' => [1, 3], 'Iringa' => [2, 3], 'Morogoro' => [3, 3],
        'Rukwa' => [0, 4], 'Mbeya' => [1, 4], 'Njombe' => [2, 4], 'Ruvuma' => [3, 4], 'Lindi' => [4, 4],
        'Mtwara' => [4, 5],
    ];

    public function run(): void
    {
        $path = database_path('data/regions_census_2022.csv');
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $record = array_combine($header, $row);
            $tile = self::TILES[$record['region']] ?? throw new RuntimeException("No tile position for {$record['region']}");

            Region::updateOrCreate(
                ['iso_code' => $record['iso_code']],
                [
                    'name' => $record['region'],
                    'population_2022' => (int) $record['population_2022'],
                    'tile_col' => $tile[0],
                    'tile_row' => $tile[1],
                ],
            );
        }

        fclose($handle);
    }
}
