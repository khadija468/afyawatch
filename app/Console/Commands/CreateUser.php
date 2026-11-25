<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'afyawatch:create-user
        {email}
        {--name= : Full name}
        {--role=clinician : manager or clinician}
        {--region= : Region name or ISO code (required for clinicians)}';

    protected $description = 'Create a dashboard account';

    public function handle(): int
    {
        $role = $this->option('role');
        $region = null;

        if ($this->option('region')) {
            $region = Region::where('iso_code', $this->option('region'))->orWhere('name', $this->option('region'))->first();
        }

        $password = $this->secret('Password (at least 12 characters)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'role' => $role, 'region' => $region?->id, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'role' => ['required', 'in:manager,clinician'],
                'region' => [$role === 'clinician' ? 'required' : 'nullable'],
                'password' => ['required', Password::min(12)],
            ],
            ['region.required' => 'Clinicians need a valid --region.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->option('name') ?: $this->argument('email'),
            'email' => $this->argument('email'),
            'password' => $password,
            'role' => $role,
            'region_id' => $role === 'manager' ? null : $region->id,
        ]);

        $this->info("Created {$user->role} account for {$user->email}".($region && $role === 'clinician' ? " ({$region->name})" : '').'.');

        return self::SUCCESS;
    }
}
