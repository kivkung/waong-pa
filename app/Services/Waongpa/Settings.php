<?php

namespace App\Services\Waongpa;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class Settings
{
    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['workflow' => $message]);
    }

    public static function defaults(): array
    {
        $now = CarbonImmutable::now(config('waongpa.timezone'));

        return [
            'search_start_date' => $now->addDays(2)->toDateString(), 'search_end_date' => $now->addDays(4)->toDateString(),
            'daily_start_time' => '08:00', 'daily_end_time' => '18:00', 'duration_minutes' => 60,
            'professor_rule' => 'at_least', 'min_professors' => 1,
            'join_starts_at' => $now->subMinute()->format('Y-m-d\TH:i'),
            'review_starts_at' => $now->addHours(2)->format('Y-m-d\TH:i'),
            'voting_starts_at' => $now->addHours(3)->format('Y-m-d\TH:i'),
            'final_starts_at' => $now->addHours(4)->format('Y-m-d\TH:i'),
        ];
    }

    public function validate(array $input): array
    {
        $data = Validator::make($input, [
            'search_start_date' => ['required', 'date_format:Y-m-d'],
            'search_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:search_start_date'],
            'daily_start_time' => ['required', 'date_format:H:i'], 'daily_end_time' => ['required', 'date_format:H:i', 'after:daily_start_time'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:600', 'multiple_of:30'],
            'professor_rule' => ['required', Rule::in(['at_least', 'all'])],
            'min_professors' => ['nullable', 'required_if:professor_rule,at_least', 'integer', 'min:1', 'max:100'],
            'join_starts_at' => ['required', 'date'],
            'review_starts_at' => ['required', 'date', 'after:join_starts_at'],
            'voting_starts_at' => ['required', 'date', 'after:review_starts_at'],
            'final_starts_at' => ['required', 'date', 'after:voting_starts_at'],
        ])->validate();
        $tz = config('waongpa.timezone');
        $start = CarbonImmutable::parse($data['search_start_date'], $tz);
        $end = CarbonImmutable::parse($data['search_end_date'], $tz);
        if ($start->diffInDays($end) >= config('waongpa.max_search_days')) {
            self::fail('ค้นหาได้ไม่เกิน 31 วันต่อรอบ');
        }
        $first = CarbonImmutable::parse($data['search_start_date'].' '.$data['daily_start_time'], $tz);
        $last = CarbonImmutable::parse($data['search_start_date'].' '.$data['daily_end_time'], $tz);
        if ($first->diffInMinutes($last) < $data['duration_minutes']) {
            self::fail('ระยะนัดยาวกว่าช่วงเวลาค้นหาต่อวัน');
        }
        if ((int) substr($data['daily_start_time'], 3, 2) % 30 || (int) substr($data['daily_end_time'], 3, 2) % 30) {
            self::fail('กรอบเวลาต้องเริ่มและจบตรงนาที 00 หรือ 30');
        }
        foreach (['join', 'review', 'voting', 'final'] as $phase) {
            $data[$phase.'_starts_at'] = CarbonImmutable::parse($data[$phase.'_starts_at'], $tz)->format('Y-m-d H:i:s');
        }
        if (CarbonImmutable::parse($data['final_starts_at'], $tz)->gte($first)) {
            self::fail('ต้องประกาศผลก่อนเวลานัดแรกที่ค้นหา');
        }
        if (CarbonImmutable::parse($data['review_starts_at'], $tz)->lte(CarbonImmutable::now($tz))) {
            self::fail('เวลาสิ้นสุด Join ต้องอยู่ในอนาคต');
        }
        if ($data['professor_rule'] === 'all') {
            $data['min_professors'] = null;
        }

        return $data;
    }

    public function event(array $input): array
    {
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
        ])->validate();
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = CarbonImmutable::parse($data[$field], config('waongpa.timezone'))->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
