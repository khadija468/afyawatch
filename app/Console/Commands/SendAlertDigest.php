<?php

namespace App\Console\Commands;

use App\Mail\AlertDigestMail;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAlertDigest extends Command
{
    protected $signature = 'alerts:digest';

    protected $description = 'Email one daily summary of open alerts to users who chose the digest';

    public function handle(): int
    {
        $sent = 0;
        $since = now()->subDay();

        User::where('alert_preference', 'digest')->each(function (User $user) use ($since, &$sent) {
            $alerts = Alert::with(['condition', 'region'])
                ->where('status', 'open')
                ->where('created_at', '>=', $since)
                ->when($user->scopedRegionId(), fn ($q, $regionId) => $q->where('region_id', $regionId))
                ->orderByDesc('week_start')
                ->get();

            if ($alerts->isNotEmpty()) {
                Mail::to($user)->send(new AlertDigestMail($alerts));
                $sent++;
            }
        });

        $this->info("Digest sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
