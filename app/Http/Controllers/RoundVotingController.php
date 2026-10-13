<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMeetingRound;
use App\Models\Room;
use App\Models\RoundVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoundVotingController extends Controller
{
    public function store(Request $request, Room $room, int $round): RedirectResponse
    {
        // ต้องเป็นสมาชิกที่ยังอยู่ในห้องนี้
        $roomMember = $room->members()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();
        $meeting = $room->rounds()->findOrFail($round);
        app(ProcessMeetingRound::class)->handle($meeting->id);
        $validated = $request->validate(['candidate_id' => ['required', 'integer']]);

        return DB::transaction(function () use ($roomMember, $room, $round, $validated) {
            $meeting = $room->rounds()->lockForUpdate()->findOrFail($round);
            // สมาชิกห้องต้องเข้าร่วมรอบนี้ด้วย จึงโหวตได้
            $member = $meeting->members()
                ->where('room_member_id', $roomMember->id)
                ->firstOrFail();

            $canVote = true;
            if ($meeting->status !== 'active' || $meeting->phase !== 'voting') {
                $canVote = false;
            }
            if (now() < $meeting->voting_starts_at || now() >= $meeting->final_starts_at) {
                $canVote = false;
            }
            if ($canVote === false) {
                throw ValidationException::withMessages(['candidate_id' => 'ยังไม่เปิดโหวตหรือหมดเวลาโหวตแล้ว']);
            }
            $candidate = $meeting->candidates()->where('id', $validated['candidate_id'])->firstOrFail();
            if (! in_array($member->id, $candidate->available_member_ids, true)) {
                throw ValidationException::withMessages(['candidate_id' => 'เวลานี้ชนกับตารางไม่ว่างของคุณ']);
            }
            // หาคะแนนเดิมก่อน ถ้าไม่เคยโหวตให้สร้างใหม่
            $vote = RoundVote::query()->where('round_member_id', $member->id)->first();
            if ($vote === null) {
                $vote = new RoundVote;
                $vote->round_member_id = $member->id;
            }
            $vote->round_candidate_id = $candidate->id;
            $vote->save();

            return redirect()->route('rooms.rounds.show', [$room, $meeting])->with('success', 'บันทึกโหวตแล้ว คุณเปลี่ยนตัวเลือกได้จนกว่าจะหมดเวลา');
        });
    }
}
