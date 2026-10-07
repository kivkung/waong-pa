<?php

namespace App\Actions;

use App\Models\MeetingRound;
use App\Models\UserBusySlot;
use Illuminate\Support\Facades\DB;

class ProcessMeetingRound
{
    public function handle(int $roundId): void
    {
        // Separate commits: a snapshot failure must not reopen membership.
        DB::transaction(function () use ($roundId) {
            $round = MeetingRound::query()->lockForUpdate()->findOrFail($roundId);
            if ($round->status === 'active' && $round->phase === 'join' && now()->gte($round->review_starts_at)) {
                $round->phase = 'review';
                $round->save();
            }
        });

        DB::transaction(function () use ($roundId) {
            $round = MeetingRound::query()->lockForUpdate()->findOrFail($roundId);
            if ($round->status !== 'active' || $round->phase === 'join'
                || $round->snapshot_taken_at !== null || now()->lt($round->snapshotDueAt())) {
                return;
            }
            foreach ($round->members()->with('roomMember')->get() as $member) {
                $member->busyPeriods()->delete();
                foreach (UserBusySlot::periodsFor($round, $member->roomMember->user_id) as $period) {
                    $member->busyPeriods()->create($period);
                }
            }
            $round->snapshot_taken_at = now();
            $round->save();
        });
    }
}
