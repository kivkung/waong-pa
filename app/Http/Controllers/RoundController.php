<?php

namespace App\Http\Controllers;

use App\Models\MeetingRound;
use App\Models\Room;
use App\Rules\RoundParameters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoundController extends Controller
{
    public function create(Request $request, Room $room): View|RedirectResponse
    {
        abort_unless(((int) $room->owner_id === (int) $request->user()->id), $room->visibility === 'private' ? 404 : 403);

        $hasActiveRound = $room->rounds()
            ->where('status', 'active')
            ->exists();

        return $hasActiveRound
            ? redirect()->route('rooms.show', compact('room'))->with('warning', 'มีรอบที่ยังดำเนินการอยู่ กรุณาดำเนินการให้เสร็จสิ้น')
            : view('rooms.rounds.create', compact('room', 'hasActiveRound'));
    }

    public function store(Request $request, Room $room): RedirectResponse
    {
        abort_unless(
            (int) $room->owner_id === (int) $request->user()->id,
            $room->visibility === 'private' ? 404 : 403
        );

        if ($room->rounds()->where('status', 'active')->exists()) {
            return redirect()->route('rooms.show', $room)
                ->with('warning', 'ห้องนี้มีรอบที่กำลังดำเนินการอยู่แล้ว กรุณาดำเนินการให้เสร็จสิ้น');
        }

        $validated = RoundParameters::validate($request);

        // {{ save data }}
        $round = new MeetingRound;

        $round->room_id = $room->id;
        $round->round_no = ($room->rounds()->orderByDesc('round_no')->first()->round_no ?? 0) + 1;

        $round->status = 'active';
        $round->phase = 'join';
        $round->active_marker = 1;

        $round->search_start_date = $validated['search_start_date'];
        $round->search_end_date = $validated['search_end_date'];
        $round->daily_start_time = $validated['daily_start_time'];
        $round->daily_end_time = $validated['daily_end_time'];
        $round->duration_minutes = $validated['duration_minutes'];

        $round->professor_rule = $validated['professor_rule'];
        $round->min_professors = $validated['professor_rule'] === 'all'
            ? null
            : $validated['min_professors'];

        $round->join_starts_at = CarbonImmutable::parse($validated['join_starts_at']);
        $round->review_starts_at = CarbonImmutable::parse($validated['review_starts_at']);
        $round->voting_starts_at = CarbonImmutable::parse($validated['voting_starts_at']);
        $round->final_starts_at = CarbonImmutable::parse($validated['final_starts_at']);

        try {
            $round->save();
        } catch (UniqueConstraintViolationException $exception) {
            // อีกคำขออาจสร้างรอบสำเร็จระหว่างที่คำขอนี้กำลังทำงาน
            throw ValidationException::withMessages([
                'round' => 'มีการสร้างรอบอื่นในห้องนี้แล้ว กรุณากลับหน้าห้องและตรวจสอบอีกครั้ง',
            ]);
        }

        return redirect()->route('rooms.show', $room)
            ->with('success', 'สร้างรอบที่ '.$round->round_no.' เรียบร้อยแล้ว');
    }

    public function show(Request $request, Room $room, int $round): View
    {
        $isMember = false;

        if ($request->user()) {
            $isMember = $room->members()
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->exists();
        }

        abort_unless($room->visibility === 'public' || $isMember, 404);

        $round = $room->rounds()->findOrFail($round);

        $hasJoined = false;

        if ($isMember) {
            $hasJoined = $round->members()
                ->whereHas('roomMember', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                })->exists();
        }

        $now = now();

        $canJoin = $isMember
            && ! $hasJoined
            && $round->status === 'active'
            && $round->phase === 'join'
            && $now->gte($round->join_starts_at)
            && $now->lt($round->review_starts_at);

        $roundMembers = null;

        if ($isMember) {
            $roundMembers = $round->members()
                ->with('roomMember.user')
                ->orderBy('id')
                ->paginate(15, ['*'], 'members_page');
        }

        return view('rooms.rounds.show', compact('room', 'round', 'hasJoined', 'canJoin', 'roundMembers'));
    }

    public function cancel(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless(
            (int) $room->owner_id === (int) $request->user()->id,
            $room->visibility === 'private' ? 404 : 403
        );

        return DB::transaction(function () use ($room, $round) {
            $round = $room->rounds()->lockForUpdate()->findOrFail($round);

            if ($round->status !== 'active') {
                return back()->with('warning', 'ยกเลิกได้เฉพาะรอบที่กำลังดำเนินการอยู่');
            }

            $round->status = 'cancelled';
            $round->active_marker = null;
            $round->cancelled_at = now();
            $round->save();

            return redirect()->route('rooms.show', ['room' => $room])
                ->with('success', 'ยกเลิกรอบสำเร็จ');

        });
    }
}
