<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_USER_PASSWORD');

        if (! $password) {
            if (app()->environment('production')) {
                throw new RuntimeException('Set DEMO_USER_PASSWORD before seeding demo accounts in production.');
            }
            $password = 'password';
        }

        $users = [
            ['manager@afyawatch.test', 'Demo Manager (national)', 'manager', null],
            ['kigoma.clinician@afyawatch.test', 'Demo Clinician (Kigoma)', 'clinician', 'Kigoma'],
            ['kagera.clinician@afyawatch.test', 'Demo Clinician (Kagera)', 'clinician', 'Kagera'],
        ];

        foreach ($users as [$email, $name, $role, $regionName]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'region_id' => $regionName ? Region::where('name', $regionName)->value('id') : null,
                'alert_preference' => 'immediate',
            ]);
        }
    }
}
