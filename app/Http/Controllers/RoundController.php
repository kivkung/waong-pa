<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMeetingRound;
use App\Models\MeetingRound;
use App\Models\Room;
use App\Models\RoundRecurrenceSchedule;
use App\Models\RoundVote;
use App\Rules\RoundParameters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $validated = RoundParameters::validate($request);
        $recurrence = $request->validate([
            'repeat_frequency' => ['nullable', Rule::in(['none', 'weekly', 'monthly'])],
            'repeat_count' => ['exclude_unless:repeat_frequency,weekly,monthly', 'required', 'integer', 'min:2', 'max:52'],
        ]);

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
            DB::transaction(function () use ($room, $round, $validated, $recurrence) {
                // ล็อกห้องเพื่อให้การสร้างด้วยมือและ scheduler ไม่ชนกัน
                Room::query()->lockForUpdate()->findOrFail($room->id);
                $round->round_no = max(1, ((int) $room->rounds()->max('round_no')) + 1);
                $frequency = $recurrence['repeat_frequency'] ?? 'none';
                if ($frequency === 'weekly' || $frequency === 'monthly') {
                    if (RoundRecurrenceSchedule::query()->where('room_id', $room->id)->where('is_active', true)->exists()) {
                        throw ValidationException::withMessages(['repeat_frequency' => 'ห้องนี้มีแผนสร้างรอบอัตโนมัติอยู่แล้ว กรุณาหยุดแผนเดิมก่อน']);
                    }
                    $count = (int) $recurrence['repeat_count'];
                    if ($count < 2 || $count > 52) {
                        throw ValidationException::withMessages(['repeat_count' => 'จำนวนรอบต้องอยู่ระหว่าง 2–52 รอบ']);
                    }
                    $schedule = new RoundRecurrenceSchedule;
                    $schedule->room_id = $room->id;
                    $schedule->frequency = $frequency;
                    $schedule->round_count = $count;
                    $template = $validated;
                    $template['min_professors'] = $round->min_professors;
                    $schedule->setAttribute('template', $template);
                    $schedule->next_index = 1;
                    $schedule->skipped_count = 0;
                    $schedule->is_active = true;

                    // ตรวจทุกคู่รอบ รวมเดือนสั้น เพื่อไม่ให้เวลารับสมาชิกทับเฟสเดิม
                    $previousFinal = $round->final_starts_at;
                    for ($index = 1; $index < $count; $index++) {
                        $parameters = $schedule->parametersFor($index);
                        if (CarbonImmutable::parse((string) $parameters['join_starts_at'])->lte($previousFinal)) {
                            throw ValidationException::withMessages(['repeat_frequency' => 'กำหนดการยาวเกินช่วงการเกิดซ้ำ รอบถัดไปต้องเปิดรับหลังรอบก่อนสรุปผล']);
                        }
                        $previousFinal = CarbonImmutable::parse((string) $parameters['final_starts_at']);
                    }
                    $next = $schedule->parametersFor(1);
                    $schedule->next_run_at = CarbonImmutable::parse((string) $next['join_starts_at']);
                    $schedule->save();
                    $round->recurrence_schedule_id = $schedule->id;
                    $round->recurrence_index = 0;
                }
                $round->save();
            });
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
        app(ProcessMeetingRound::class)->handle($round->id);
        $round->refresh();

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

        $participant = null;
        $selectedCandidateId = null;
        if ($isMember) {
            $participant = $round->members()->whereHas('roomMember', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })->first();
        }
        if ($participant !== null) {
            $selectedCandidateId = RoundVote::query()
                ->where('round_member_id', $participant->id)
                ->value('round_candidate_id');
        }
        $candidates = null;
        $winner = null;
        $participantCount = $round->members()->count();
        if ($isMember && $round->candidates_generated_at !== null) {
            $query = $round->candidates()->withCount('votes');
            // ตอนโหวต แสดงเฉพาะเวลาที่สมาชิกคนนี้ว่างจากสำเนาตาราง
            if ($round->phase === 'voting' && $participant) {
                $query->whereJsonContains('available_member_ids', $participant->id);
            }
            // เรียงจำนวนคนว่างจากมากไปน้อย ถ้าเท่ากันเอาเวลาเร็วขึ้นก่อน
            $candidates = $query->orderByDesc('available_count')
                ->orderBy('start_at')
                ->paginate(12, ['*'], 'candidates_page');
            $winner = $round->candidates()->withCount('votes')->where('is_winner', true)->first();
        }

        return view('rooms.rounds.show', compact('room', 'round', 'hasJoined', 'canJoin', 'roundMembers', 'participant', 'selectedCandidateId', 'candidates', 'winner', 'participantCount'));
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
            if ($round->recurrence_schedule_id !== null) {
                $round->recurrenceSchedule()->update(['is_active' => false, 'next_run_at' => null]);
            }

            return redirect()->route('rooms.show', ['room' => $room])
                ->with('success', 'ยกเลิกรอบสำเร็จ');

        });
    }

    public function stopRecurrence(Request $request, Room $room, int $round): RedirectResponse
    {
        abort_unless((int) $room->owner_id === (int) $request->user()->id, $room->visibility === 'private' ? 404 : 403);
        $meeting = $room->rounds()->findOrFail($round);
        abort_if($meeting->recurrence_schedule_id === null, 404);
        DB::transaction(function () use ($meeting) {
            $schedule = RoundRecurrenceSchedule::query()->lockForUpdate()->findOrFail($meeting->recurrence_schedule_id);
            $schedule->is_active = false;
            $schedule->next_run_at = null;
            $schedule->save();
        });

        return back()->with('success', 'หยุดสร้างรอบถัดไปแล้ว รอบที่สร้างไปแล้วยังดำเนินการตามเดิม');
    }
}
