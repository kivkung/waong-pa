<?php

namespace App\Services\Waongpa;

use App\Models\Waongpa\MeetingRound;
use Carbon\CarbonImmutable;

final class SlotRanker
{
    public function rank(MeetingRound $round): array
    {
        $people = $round->participants()->with('busyPeriods')->get();
        if ($people->isEmpty() || $people->contains(fn ($p) => ! $p->schedule_confirmed_at)) {
            Settings::fail('สมาชิกทุกคนต้องยืนยันตารางก่อนคำนวณ');
        }
        $professors = $people->where('role', 'professor')->count();
        $required = $round->professor_rule === 'all' ? $professors : (int) $round->min_professors;
        if ($professors === 0 || $required > $professors) {
            Settings::fail('จำนวนอาจารย์ในรอบยังไม่ครบตามเงื่อนไข');
        }
        $tz = config('waongpa.timezone');
        $slots = [];
        for ($day = $round->search_start_date; $day->lte($round->search_end_date); $day = $day->addDay()) {
            $open = CarbonImmutable::parse($day->toDateString().' '.$round->daily_start_time, $tz);
            $close = CarbonImmutable::parse($day->toDateString().' '.$round->daily_end_time, $tz);
            for ($start = $open; $start->addMinutes($round->duration_minutes)->lte($close); $start = $start->addMinutes(30)) {
                $end = $start->addMinutes($round->duration_minutes);
                $score = 0;
                $availableProf = 0;
                if ($start->lte(CarbonImmutable::now($tz))) {
                    continue;
                }
                foreach ($people as $person) {
                    $busy = $person->busyPeriods->contains(fn ($event) => $event->starts_at->lt($end) && $event->ends_at->gt($start));
                    if (! $busy) {
                        $score += (int) round((float) $person->weight * 100);
                        $availableProf += (int) ($person->role === 'professor');
                    }
                }
                if ($availableProf >= $required) {
                    $slots[] = ['starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'), 'weighted_score' => $score / 100];
                }
            }
        }
        usort($slots, fn ($a, $b) => ($b['weighted_score'] <=> $a['weighted_score']) ?: strcmp($a['starts_at'], $b['starts_at']));
        if (! $slots) {
            Settings::fail('ไม่พบเวลาที่ผ่านเงื่อนไข กรุณาปรับช่วงค้นหา ระยะนัด หรือจำนวนอาจารย์');
        }

        return array_slice($slots, 0, max(1, (int) config('waongpa.top_slots', 3)));
    }
}
