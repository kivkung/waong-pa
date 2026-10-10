<?php

namespace App\Actions;

use App\Models\MeetingRound;
use App\Models\UserBusySlot;

class GenerateRoundCandidates
{
    // ใช้สำเนาตารางของรอบนี้ เพื่อให้การแก้ตารางส่วนตัวไม่เปลี่ยนผลระหว่างโหวต
    public function handle(MeetingRound $round): void
    {
        $members = $round->members()->with('busyPeriods')->get();
        $requiredProfessors = (int) $round->min_professors;
        if ($round->professor_rule === 'all') {
            $requiredProfessors = 0;
            foreach ($members as $member) {
                if ($member->role === 'professor') {
                    $requiredProfessors++;
                }
            }
        }

        foreach (UserBusySlot::windows($round) as $window) {
            // ลองเวลาเริ่มทีละ 30 นาที และต้องมีเวลาพอสำหรับนัดทั้งช่วง
            for ($start = $window['start_at']; $start->addMinutes($round->duration_minutes)->lte($window['end_at']); $start = $start->addMinutes(30)) {
                $end = $start->addMinutes($round->duration_minutes);
                $availableMemberIds = [];
                $availableProfessors = 0;

                foreach ($members as $member) {
                    $isBusy = false;
                    foreach ($member->busyPeriods as $period) {
                        // ชนกันเมื่อเวลาไม่ว่างเริ่มก่อนจบนัด และจบหลังเริ่มนัด
                        // ถ้าจบพอดีกับเริ่มนัด จะถือว่าว่าง
                        if ($period->start_at < $end && $period->end_at > $start) {
                            $isBusy = true;
                            break;
                        }
                    }

                    if (! $isBusy) {
                        $availableMemberIds[] = $member->id;
                        if ($member->role === 'professor') {
                            $availableProfessors++;
                        }
                    }
                }

                // คะแนนความพร้อม = จำนวนสมาชิกที่ว่างตลอดช่วงนัด
                $availableCount = count($availableMemberIds);
                if ($availableCount === 0 || $availableProfessors < $requiredProfessors) {
                    continue;
                }
                $round->candidates()->create([
                    'start_at' => $start,
                    'end_at' => $end,
                    'available_count' => $availableCount,
                    'professor_count' => $availableProfessors,
                    'available_member_ids' => $availableMemberIds,
                ]);
            }
        }
        $round->candidates_generated_at = now();
        $round->save();
    }
}
