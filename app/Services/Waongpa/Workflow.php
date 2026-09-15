<?php

namespace App\Services\Waongpa;

use App\Models\User;
use App\Models\Waongpa\MeetingRound;
use App\Models\Waongpa\PersonalEvent;
use App\Models\Waongpa\Room;
use App\Models\Waongpa\RoomMember;
use App\Models\Waongpa\RoundParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class Workflow
{
    public function __construct(private Access $access, private Settings $settings, private SlotRanker $ranker) {}

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('waongpa.timezone'));
    }

    // First statement in each transaction reserves SQLite's writer lock. lockForUpdate alone is not enough in SQLite.
    private function lockedRound(int $id, ?int $version = null): MeetingRound
    {
        MeetingRound::whereKey($id)->update(['updated_at' => now()]);
        $round = MeetingRound::findOrFail($id);
        if ($version !== null && $round->version !== $version) {
            Settings::fail('รอบถูกรีเซ็ตแล้ว กรุณาโหลดหน้าใหม่');
        }

        return $round;
    }

    private function joinPhase(MeetingRound $round): void
    {
        if ($round->phase !== 'join' || $this->now()->lt($round->join_starts_at)) {
            Settings::fail('ทำรายการได้เฉพาะเฟส Join ที่เปิดแล้ว');
        }
    }

    public function createRoom(User $user, array $input, array $roundInput): Room
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'owner_role' => ['required', Rule::in(['student', 'professor'])],
        ])->validate();
        $settings = $this->settings->validate($roundInput);

        return DB::transaction(function () use ($user, $data, $settings) {
            $room = Room::create(['owner_id' => $user->id, 'name' => $data['name'], 'description' => $data['description'] ?? '', 'visibility' => $data['visibility'], 'join_code' => Str::upper(Str::random(12))]);
            RoomMember::create(['room_id' => $room->id, 'user_id' => $user->id, 'role' => $data['owner_role'], 'weight' => 1, 'status' => 'active', 'joined_at' => $this->now()]);
            $this->newRound($room, $settings);

            return $room;
        }, 3);
    }

    private function newRound(Room $room, array $settings): MeetingRound
    {
        if ($room->rounds()->whereNotNull('active_marker')->exists()) {
            Settings::fail('ห้องมีรอบที่ยังไม่จบอยู่แล้ว');
        }
        $round = $room->rounds()->create([...$settings, 'round_no' => ($room->rounds()->max('round_no') ?? 0) + 1, 'phase' => 'join', 'active_marker' => 1]);
        foreach ($room->members()->where('status', 'active')->get() as $member) {
            $this->addParticipant($round, $member);
        }

        return $round;
    }

    private function addParticipant(MeetingRound $round, RoomMember $member): RoundParticipant
    {
        $person = $round->participants()->firstOrCreate(['room_member_id' => $member->id], ['role' => $member->role, 'weight' => $member->weight]);
        $this->copySchedule($person, $round);

        return $person;
    }

    private function copySchedule(RoundParticipant $person, MeetingRound $round): void
    {
        $person->busyPeriods()->delete();
        // Copy every event intersecting the search dates, including events crossing midnight.
        $events = PersonalEvent::where('user_id', $person->member->user_id)
            ->where('starts_at', '<', $round->search_end_date->addDay()->startOfDay())
            ->where('ends_at', '>', $round->search_start_date->startOfDay())->get();
        foreach ($events as $event) {
            $person->busyPeriods()->create(['source_event_id' => $event->id, 'title' => $event->title, 'starts_at' => $event->starts_at, 'ends_at' => $event->ends_at]);
        }
        $person->update(['schedule_copied_at' => $this->now(), 'schedule_confirmed_at' => null]);
    }

    public function joinRoom(User $user, string $code): Room
    {
        $code = Str::upper(trim($code));

        return DB::transaction(function () use ($user, $code) {
            Room::where('join_code', $code)->update(['updated_at' => now()]);
            $room = Room::where('join_code', $code)->whereNull('archived_at')->first();
            if (! $room) {
                Settings::fail('ไม่พบรหัสห้อง');
            }
            $round = $room->rounds()->whereNotNull('active_marker')->first();
            if (! $round) {
                Settings::fail('ห้องยังไม่มีรอบที่เปิดรับสมาชิก');
            }
            $this->joinPhase($round);
            $member = $room->members()->where('user_id', $user->id)->first();
            if ($member && $member->status === 'active') {
                Settings::fail('คุณอยู่ในห้องนี้แล้ว');
            }
            $member = $room->members()->updateOrCreate(['user_id' => $user->id], ['role' => 'student', 'weight' => 1, 'status' => 'active', 'joined_at' => $this->now(), 'left_at' => null]);
            $this->addParticipant($round, $member);

            return $room;
        }, 3);
    }

    public function updateRoom(User $user, int $roomId, array $input): void
    {
        $room = Room::findOrFail($roomId);
        $this->access->owner($user, $room);
        $data = Validator::make($input, ['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'visibility' => ['required', Rule::in(['public', 'private'])]])->validate();
        $room->update($data);
    }

    public function setMember(User $user, int $roomId, int $memberId, string $role, mixed $weight): void
    {
        $data = Validator::make(['role' => $role, 'weight' => $weight], ['role' => ['required', Rule::in(['student', 'professor'])], 'weight' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2']])->validate();
        DB::transaction(function () use ($user, $roomId, $memberId, $data) {
            Room::whereKey($roomId)->update(['updated_at' => now()]);
            $room = Room::findOrFail($roomId);
            $this->access->owner($user, $room);
            $round = $room->rounds()->whereNotNull('active_marker')->first();
            if ($round) {
                $this->joinPhase($round);
            }
            $member = $room->members()->where('status', 'active')->findOrFail($memberId);
            $member->update($data);
            if ($round) {
                $round->participants()->where('room_member_id', $member->id)->update($data);
            }
        }, 3);
    }

    public function removeMember(User $user, int $roomId, int $memberId): void
    {
        DB::transaction(function () use ($user, $roomId, $memberId) {
            Room::whereKey($roomId)->update(['updated_at' => now()]);
            $room = Room::findOrFail($roomId);
            $member = $room->members()->findOrFail($memberId);
            abort_unless((int) $member->user_id === (int) $user->id || (int) $room->owner_id === (int) $user->id, 403);
            if ((int) $member->user_id === (int) $room->owner_id) {
                Settings::fail('เจ้าของห้องออกจากห้องไม่ได้');
            }
            $round = $room->rounds()->whereNotNull('active_marker')->first();
            if ($round) {
                $this->joinPhase($round);
                $round->participants()->where('room_member_id', $member->id)->delete();
            }
            $member->update(['status' => (int) $user->id === (int) $member->user_id ? 'left' : 'removed', 'left_at' => $this->now()]);
        }, 3);
    }

    public function startAgain(User $user, int $roomId, array $input): MeetingRound
    {
        $settings = $this->settings->validate($input);

        return DB::transaction(function () use ($user, $roomId, $settings) {
            Room::whereKey($roomId)->update(['updated_at' => now()]);
            $room = Room::findOrFail($roomId);
            $this->access->owner($user, $room);

            return $this->newRound($room, $settings);
        }, 3);
    }

    public function reset(User $user, int $roundId, int $version, array $input): void
    {
        $settings = $this->settings->validate($input);
        DB::transaction(function () use ($user, $roundId, $version, $settings) {
            $round = $this->lockedRound($roundId, $version);
            $this->access->owner($user, $round->room);
            if ($round->phase === 'final') {
                Settings::fail('รอบจบแล้ว ให้ใช้นัดอีกครั้งเพื่อเก็บประวัติ');
            }
            // Order matters: result references the winning slot; votes cascade when slots are removed.
            $round->result()->delete();
            $round->slots()->delete();
            $round->participants()->update(['schedule_confirmed_at' => null]);
            $round->update([...$settings, 'phase' => 'join', 'version' => $round->version + 1, 'reset_count' => $round->reset_count + 1]);
        }, 3);
    }

    public function syncSchedule(User $user, int $roundId, int $version): void
    {
        DB::transaction(function () use ($user, $roundId, $version) {
            $round = $this->lockedRound($roundId, $version);
            $this->joinPhase($round);
            $person = $this->access->participant($user, $round);
            $this->copySchedule($person, $round);
        }, 3);
    }

    public function confirmSchedule(User $user, int $roundId, int $version): void
    {
        DB::transaction(function () use ($user, $roundId, $version) {
            $round = $this->lockedRound($roundId, $version);
            $this->joinPhase($round);
            $this->access->participant($user, $round)->update(['schedule_confirmed_at' => $this->now()]);
        }, 3);
    }

    public function saveBusy(User $user, int $roundId, int $version, array $input, ?int $eventId = null): void
    {
        $data = $this->settings->event($input);
        DB::transaction(function () use ($user, $roundId, $version, $data, $eventId) {
            $round = $this->lockedRound($roundId, $version);
            $this->joinPhase($round);
            $person = $this->access->participant($user, $round);
            if ($eventId) {
                $person->busyPeriods()->findOrFail($eventId)->update($data);
            } else {
                $person->busyPeriods()->create($data);
            }
            $person->update(['schedule_confirmed_at' => null]);
        }, 3);
    }

    public function deleteBusy(User $user, int $roundId, int $version, int $eventId): void
    {
        DB::transaction(function () use ($user, $roundId, $version, $eventId) {
            $round = $this->lockedRound($roundId, $version);
            $this->joinPhase($round);
            $person = $this->access->participant($user, $round);
            $person->busyPeriods()->findOrFail($eventId)->delete();
            $person->update(['schedule_confirmed_at' => null]);
        }, 3);
    }

    public function vote(User $user, int $roundId, int $version, int $slotId): void
    {
        DB::transaction(function () use ($user, $roundId, $version, $slotId) {
            $round = $this->lockedRound($roundId, $version);
            if ($round->phase !== 'voting' || $this->now()->lt($round->voting_starts_at) || $this->now()->gte($round->final_starts_at)) {
                Settings::fail('ยังไม่เปิดโหวตหรือปิดโหวตแล้ว');
            }
            $person = $this->access->participant($user, $round);
            $slot = $round->slots()->findOrFail($slotId);
            $person->vote()->updateOrCreate([], ['time_slot_id' => $slot->id]);
        }, 3);
    }

    public function advance(User $user, int $roundId, int $version): void
    {
        DB::transaction(function () use ($user, $roundId, $version) {
            $round = $this->lockedRound($roundId, $version);
            $this->access->owner($user, $round->room);
            $this->transition($round, true);
        }, 3);
    }

    public function syncDue(int $roundId): void
    {
        DB::transaction(function () use ($roundId) {
            $round = $this->lockedRound($roundId);
            for ($i = 0; $i < 3 && $round->phase !== 'final'; $i++) {
                $next = match ($round->phase) {
                    'join' => 'review','review' => 'voting','voting' => 'final'
                };
                if ($this->now()->lt($round->{$next.'_starts_at'})) {
                    break;
                }
                $this->transition($round, false);
                $round->refresh();
            }
        }, 3);
    }

    private function transition(MeetingRound $round, bool $manual): void
    {
        $now = $this->now();
        if ($round->phase === 'final') {
            Settings::fail('รอบนี้จบแล้ว');
        }
        if ($round->phase === 'join') {
            $this->joinPhase($round);
            $slots = $this->ranker->rank($round);
            $round->slots()->delete();
            foreach ($slots as $i => $slot) {
                $round->slots()->create([...$slot, 'rank_no' => $i + 1, 'calculated_at' => $now]);
            }
            // A late manual transition keeps its scheduled boundary; moving it later could overlap Voting.
            $round->update(['phase' => 'review', ...($manual ? ['review_starts_at' => $now->min($round->review_starts_at)] : [])]);
        } elseif ($round->phase === 'review') {
            if (! $round->slots()->exists()) {
                Settings::fail('ยังไม่มีตัวเลือกเวลา');
            }
            if ($manual && $now->gte($round->final_starts_at)) {
                Settings::fail('เลยเวลาปิดโหวตแล้ว ให้ประมวลผลตามเวลาหรือ Reset');
            }
            $round->update(['phase' => 'voting', ...($manual ? ['voting_starts_at' => $now->min($round->voting_starts_at)] : [])]);
        } else {
            $slots = $round->slots()->withCount('votes')->get();
            if ($slots->isEmpty()) {
                Settings::fail('ไม่มีตัวเลือกสำหรับสรุปผล');
            }
            $maximum = $slots->max('votes_count');
            $leaders = $slots->where('votes_count', $maximum);
            $winner = $maximum === 0 ? $slots->sortBy('rank_no')->first() : $leaders->sortBy('starts_at')->first();
            $reason = $maximum === 0 ? 'no_votes_top_rank' : ($leaders->count() > 1 ? 'tie_earliest' : 'most_votes');
            $round->result()->create(['time_slot_id' => $winner->id, 'decision_reason' => $reason, 'finalized_at' => $now]);
            $round->update(['phase' => 'final', 'active_marker' => null, ...($manual ? ['final_starts_at' => $now] : [])]);
        }
    }
}
