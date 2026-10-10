<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMeetingRound;
use App\Models\Room;
use App\Models\RoundVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RoundVotingController extends Controller
{
    public function store(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless($room->members()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
        $meeting = $room->rounds()->findOrFail($round);
        app(ProcessMeetingRound::class)->handle($meeting->id);
        $validated = $request->validate(['candidate_id' => ['required', 'integer']]);

        return DB::transaction(function () use ($request, $room, $round, $validated) {
            $meeting = $room->rounds()->lockForUpdate()->findOrFail($round);
            $member = $meeting->members()->whereHas('roomMember', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })->firstOrFail();
            if ($meeting->status !== 'active' || $meeting->phase !== 'voting'
                || now()->lt($meeting->voting_starts_at) || now()->gte($meeting->final_starts_at)) {
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

    public function calendar(Request $request, Room $room, int $round): Response
    {
        abort_unless($room->members()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
        $meeting = $room->rounds()->findOrFail($round);
        app(ProcessMeetingRound::class)->handle($meeting->id);
        $meeting->refresh();
        abort_unless($meeting->status === 'completed' && $meeting->phase === 'final', 404);
        $winner = $meeting->candidates()->where('is_winner', true)->firstOrFail();
        $summary = str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $room->name);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Waongpa//Meeting//TH', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
            'UID:round-'.$meeting->id.'-candidate-'.$winner->id.'@waongpa',
            'DTSTAMP:'.$meeting->updated_at->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$winner->start_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$winner->end_at->utc()->format('Ymd\THis\Z'), 'SUMMARY:'.$summary,
            'END:VEVENT', 'END:VCALENDAR'];
        // RFC 5545 folds lines at 75 bytes, preserving UTF-8 characters.
        $folded = [];
        foreach ($lines as $line) {
            $parts = [];
            while (strlen($line) > 75) {
                $part = mb_strcut($line, 0, 75, 'UTF-8');
                $parts[] = $part;
                $line = ' '.substr($line, strlen($part));
            }
            $parts[] = $line;

            $folded[] = implode("\r\n", $parts);
        }

        return response(implode("\r\n", $folded)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="round-'.$meeting->id.'.ics"',
        ]);
    }
}
