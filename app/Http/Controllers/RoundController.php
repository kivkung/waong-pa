<?php

namespace App\Http\Controllers;

use App\Models\MeetingRound;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
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

        $validated = $request->validate([
            'search_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'search_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:search_start_date'],
            'daily_start_time' => ['required', 'date_format:H:i'],
            'daily_end_time' => ['required', 'date_format:H:i', 'after_or_equal:daily_start_time'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:600', 'multiple_of:30'],
            'professor_rule' => ['required', Rule::in(['at_least', 'all'])],
            'min_professors' => ['exclude_if:professor_rule,all', 'required', 'integer', 'min:1', 'max:100'],
            'join_starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'review_starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:join_starts_at', 'after:now'],
            'voting_starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:review_starts_at'],
            'final_starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:voting_starts_at'],
        ]);

        $searchStart = CarbonImmutable::parse($validated['search_start_date']);
        $searchEnd = CarbonImmutable::parse($validated['search_end_date']);

        $dailyStart = CarbonImmutable::parse(
            $validated['search_start_date'] . ' ' . $validated['daily_start_time']
        );

        $dailyEnd = CarbonImmutable::parse(
            $validated['search_start_date'] . ' ' . $validated['daily_end_time']
        );

        // รวมวันแรกและวันสุดท้ายแล้วค้นหาได้ไม่เกิน 31 วัน
        if ($searchStart->diffInDays($searchEnd) >= 31) {
            throw ValidationException::withMessages([
                'search_end_date' => 'ค้นหาได้ไม่เกิน 31 วันต่อรอบ',
            ]);
        }

        // กรอบเวลาต้องตรงนาที 00 หรือ 30
        if ($dailyStart->minute % 30 !== 0 || $dailyEnd->minute % 30 !== 0) {
            throw ValidationException::withMessages([
                'daily_start_time' => 'เวลาเริ่มและสิ้นสุดต้องตรงนาที 00 หรือ 30',
            ]);
        }

        // เช่น เปิดให้ค้นหา 08:00–09:00 จะนัดยาว 120 นาทีไม่ได้
        if ($dailyStart->diffInMinutes($dailyEnd) < $validated['duration_minutes']) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'ระยะเวลานัดยาวกว่ากรอบเวลาในแต่ละวัน',
            ]);
        }

        // ต้องสรุปผลก่อนเวลานัดแรกที่ระบบจะนำมาค้นหา
        if (CarbonImmutable::parse($validated['final_starts_at'])->gte($dailyStart)) {
            throw ValidationException::withMessages([
                'final_starts_at' => 'ต้องสรุปผลก่อนเวลาแรกที่ระบบนำมาค้นหานัด',
            ]);
        }

        // {{ save data }}
        $round = new MeetingRound;

        $round->room_id = $room->id;
        $round->round_no = ($room->rounds()->max('round_no') ?? 0) + 1;

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
            ->with('success', 'สร้างรอบที่ ' . $round->round_no . ' เรียบร้อยแล้ว');
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
            && !$hasJoined
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

        $round = $room->rounds()->findOrFail($round);

        if ($round->status !== 'active') {
            return back()->with('warning', 'ยกเลิกได้เฉพาะรอบที่กำลังดำเนินการอยู่');
        }

        $round->status = 'cancelled';
        $round->active_marker = null;
        $round->cancelled_at = now();
        $round->save();

        return redirect()->route('rooms.show', ['room' => $room])
            ->with('success', 'ยกเลิกรอบสำเร็จ');

    }
}
