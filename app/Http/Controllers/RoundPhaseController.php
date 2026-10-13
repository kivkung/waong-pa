<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMeetingRound;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoundPhaseController extends Controller
{
    public function skip(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless(
            (int) $room->owner_id === (int) $request->user()->id,
            $room->visibility === 'private' ? 404 : 403
        );

        $validated = $request->validate([
            'expected_phase' => ['required', Rule::in(['join', 'review', 'voting'])],
        ]);

        $expectedPhase = $validated['expected_phase'];
        $problem = DB::transaction(function () use ($room, $round, $expectedPhase): ?string {
            $round = $room->rounds()->lockForUpdate()->findOrFail($round);

            if ($round->status !== 'active') {
                return 'ข้ามเฟสได้เฉพาะรอบที่กำลังดำเนินการ';
            }

            if ($round->phase !== $expectedPhase) {
                return 'เฟสของรอบเปลี่ยนไปแล้ว กรุณารีเฟรชหน้าแล้วลองอีกครั้ง';
            }

            $now = now();

            if ($expectedPhase === 'join') {
                $reviewDuration = max(60, (int) $round->review_starts_at->diffInSeconds($round->voting_starts_at));
                $votingDuration = max(60, (int) $round->voting_starts_at->diffInSeconds($round->final_starts_at));

                $round->review_starts_at = $now;
                if ($round->join_starts_at->greaterThanOrEqualTo($now)) {
                    $round->join_starts_at = $now->copy()->subSecond();
                }

                // Keep the planned review duration, with a minimum buffer after snapshot collection.
                $earliestVoting = $round->snapshotDueAt()->addMinute();
                $plannedVoting = $round->review_starts_at->addSeconds($reviewDuration);
                if ($plannedVoting->greaterThan($earliestVoting)) {
                    $earliestVoting = $plannedVoting;
                }
                if ($round->voting_starts_at->lessThan($earliestVoting)) {
                    $round->voting_starts_at = $earliestVoting;
                }

                $earliestFinal = $round->voting_starts_at->addSeconds($votingDuration);
                if ($round->final_starts_at->lessThan($earliestFinal)) {
                    $round->final_starts_at = $earliestFinal;
                }

            } elseif ($expectedPhase === 'review') {
                $votingDuration = max(60, (int) $round->voting_starts_at->diffInSeconds($round->final_starts_at));
                if ($round->review_starts_at->greaterThanOrEqualTo($now)) {
                    $round->review_starts_at = $now->copy()->subSecond();
                }
                $round->voting_starts_at = $now;
                if ($round->final_starts_at->lessThanOrEqualTo($now)) {
                    $round->final_starts_at = $now->copy()->addSeconds($votingDuration);
                }
            } else {
                if ($round->voting_starts_at->greaterThanOrEqualTo($now)) {
                    $round->voting_starts_at = $now->copy()->subSecond();
                }
                $round->final_starts_at = $now;
            }

            $round->save();

            return null;
        });

        if ($problem !== null) {
            return redirect()->route('rooms.rounds.show', [$room, $round])->with('warning', $problem);
        }

        // Process the phase change after releasing the row lock; the processor takes the same lock itself.
        app(ProcessMeetingRound::class)->handle($round, $expectedPhase === 'review');

        if ($expectedPhase === 'review') {
            $freshRound = $room->rounds()->findOrFail($round);
            if ($freshRound->phase !== 'voting') {
                return redirect()->route('rooms.rounds.show', [$room, $round])
                    ->with('warning', 'เก็บ Snapshot จากข้อมูลปัจจุบันแล้ว แต่ไม่พบช่วงเวลาที่ผ่านเงื่อนไข จึงยังเปิดโหวตไม่ได้');
            }
        }

        $message = match ($expectedPhase) {
            'join' => 'ข้ามช่วงรับสมาชิกแล้ว ระบบเริ่มตรวจข้อมูลและรวบรวม snapshot',
            'review' => 'เก็บ Snapshot จากข้อมูลปัจจุบันและเปิดโหวตเรียบร้อย',
            'voting' => 'ปิดโหวตและสรุปผลเรียบร้อย',
        };

        return redirect()->route('rooms.rounds.show', [$room, $round])->with('success', $message);
    }
}
