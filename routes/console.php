<?php

use App\Actions\ProcessMeetingRound;
use App\Models\MeetingRound;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rounds:process', function () {
    $failed = false;
    foreach (MeetingRound::query()->where('status', 'active')->whereHas('room')->cursor() as $round) {
        try {
            app(ProcessMeetingRound::class)->handle($round->id);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Round '.$round->id.': '.$exception->getMessage());
            $failed = true;
        }
    }

    return $failed ? 1 : 0;
})->purpose('Close Join and capture due round snapshots');

Schedule::command('rounds:process')->everyMinute()->withoutOverlapping();
