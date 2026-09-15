<?php

namespace App\Services\Waongpa;

use App\Models\User;
use App\Models\Waongpa\MeetingRound;
use App\Models\Waongpa\Room;
use App\Models\Waongpa\RoundParticipant;

final class Access
{
    public function isMember(?User $user, Room $room): bool
    {
        return $user && $room->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }

    public function view(?User $user, Room $room): void
    {
        abort_unless($room->visibility === 'public' || $this->isMember($user, $room), 404);
    }

    public function owner(User $user, Room $room): void
    {
        abort_unless((int) $room->owner_id === (int) $user->id, 403);
    }

    public function participant(User $user, MeetingRound $round): RoundParticipant
    {
        return $round->participants()->whereHas('member', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->firstOrFail();
    }
}
