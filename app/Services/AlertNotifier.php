<?php

namespace App\Services;

use App\Mail\OutbreakAlertMail;
use App\Models\Alert;
use App\Models\User;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Decides who gets an email for a new alert, and whether anyone should get one at all.
 *
 * Controls against alert fatigue:
 *  - routing: clinicians only hear about their own region; managers hear about every region;
 *  - preferences: "immediate" users get warnings straight away, "digest" users get warnings
 *    once a day (critical alerts still go out at once), "off" users get no email;
 *  - cooldown: once a condition and region has been emailed, it is not emailed again for
 *    the cooldown period unless the alert escalates to critical or the count doubles.
 */
class AlertNotifier
{
    public function handle(Alert $alert, bool $send = true): void
    {
        $reason = $this->reasonToSkip($alert);

        if ($reason === null && ! $send) {
            $reason = 'Historical evaluation: no email sent';
        }

        $recipients = $reason === null ? $this->recipients($alert) : collect();

        if ($reason === null && $recipients->isEmpty()) {
            $reason = 'No users subscribed to immediate alerts for this region';
        }

        if ($reason !== null) {
            $alert->update(['email_skipped_reason' => $reason]);

            return;
        }

        // One message per person, so recipients never see each other's addresses.
        foreach ($recipients as $user) {
            Mail::to($user)->send(new OutbreakAlertMail($alert));
        }

        $alert->update(['emailed_at' => now(), 'email_skipped_reason' => null]);
    }

    public function reasonToSkip(Alert $alert): ?string
    {
        $week = CarbonImmutable::parse($alert->week_start);
        $cooldownStart = $week->subDays(config('afyawatch.alert_cooldown_days'));

        $previous = Alert::query()
            ->where('condition_id', $alert->condition_id)
            ->where('region_id', $alert->region_id)
            ->whereNotNull('emailed_at')
            ->where('week_start', '<', $week->toDateString())
            ->where('week_start', '>=', $cooldownStart->toDateString())
            ->orderByDesc('week_start')
            ->first();

        if ($previous === null) {
            return null;
        }

        if ($alert->isCritical() && ! $previous->isCritical()) {
            return null; // escalated from warning to critical
        }

        if ($alert->observed >= 2 * $previous->observed) {
            return null; // count at least doubled since the last email
        }

        return sprintf(
            'Cooldown: already emailed for %s (%d cases); not escalated',
            Week::label($previous->week_start),
            $previous->observed,
        );
    }

    /** @return Collection<int, User> */
    public function recipients(Alert $alert): Collection
    {
        $preferences = $alert->isCritical() ? ['immediate', 'digest'] : ['immediate'];

        return User::query()
            ->whereIn('alert_preference', $preferences)
            ->where(fn ($q) => $q->where('role', User::ROLE_MANAGER)->orWhere('region_id', $alert->region_id))
            ->orderBy('id')
            ->get();
    }
}
