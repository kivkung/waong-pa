<?php

namespace App\Actions;

use App\Models\MeetingRound;
use App\Models\UserBusySlot;
use Illuminate\Support\Facades\DB;

class ProcessMeetingRound
{
    public function handle(int $roundId): void
    {
        // transaction บันทึกงานเป็นชุด และ lockForUpdate กันการประมวลผลรอบเดียวกันพร้อมกัน
        // ขั้นที่ 1: ปิดรับสมาชิกก่อน แม้การเก็บสำเนาในขั้นถัดไปล้มเหลว
        DB::transaction(function () use ($roundId) {
            $round = MeetingRound::query()->lockForUpdate()->findOrFail($roundId);
            if ($round->status === 'active' && $round->phase === 'join' && now()->gte($round->review_starts_at)) {
                $round->phase = 'review';
                $round->save();
            }
        });

        // ขั้นที่ 2: เก็บสำเนาเวลาไม่ว่างเพียงครั้งเดียว เมื่อพ้นเวลารอ
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

        // ขั้นที่ 3: สร้างตัวเลือก เปิดโหวต หรือสรุปผลตามเวลา
        DB::transaction(function () use ($roundId) {
            $round = MeetingRound::query()->lockForUpdate()->findOrFail($roundId);
            if ($round->status !== 'active' || $round->snapshot_taken_at === null) {
                return;
            }
            if ($round->candidates_generated_at === null) {
                app(GenerateRoundCandidates::class)->handle($round);
            }
            if (now()->gte($round->final_starts_at)) {
                // เลือกคะแนนโหวตสูงสุด ถ้าเท่ากันใช้จำนวนคนว่าง แล้วใช้เวลาเร็วกว่า
                // has('votes') ทำให้ไม่มีผู้ชนะเมื่อไม่มีใครโหวต
                $winner = $round->candidates()->withCount('votes')->has('votes')
                    ->orderByDesc('votes_count')
                    ->orderByDesc('available_count')
                    ->orderBy('start_at')
                    ->first();
                if ($winner) {
                    $winner->is_winner = true;
                    $winner->save();
                }
                $round->phase = 'final';
                $round->status = 'completed';
                $round->active_marker = null;
            } elseif (now()->gte($round->voting_starts_at)) {
                $round->phase = 'voting';
            }
            $round->save();
        });
    }
}
