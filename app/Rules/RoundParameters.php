<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoundParameters
{
    /** @return array<string, mixed> */
    public static function validate(Request $request): array
    {
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
            $validated['search_start_date'].' '.$validated['daily_start_time']
        );

        $dailyEnd = CarbonImmutable::parse(
            $validated['search_start_date'].' '.$validated['daily_end_time']
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

        return $validated;
    }
}
