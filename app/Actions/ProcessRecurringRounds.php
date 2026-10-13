<?php

namespace App\Actions;

use App\Models\MeetingRound;
use App\Models\Room;
use App\Models\RoundRecurrenceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProcessRecurringRounds
{
    public function handle(int $scheduleId): void
    {
        DB::transaction(function () use ($scheduleId) {
            $schedule = RoundRecurrenceSchedule::query()->lockForUpdate()->findOrFail($scheduleId);
            if (! $schedule->is_active) {
                return;
            }
            $room = Room::query()->lockForUpdate()->find($schedule->room_id);
            if ($room === null) {
                $schedule->is_active = false;
                $schedule->next_run_at = null;
                $schedule->save();

                return;
            }

            while ($schedule->is_active && $schedule->next_run_at !== null && now()->gte($schedule->next_run_at)) {
                $parameters = $schedule->parametersFor($schedule->next_index);
                // หากหยุดเครื่องจนพ้นช่วงรับสมาชิก ให้ข้ามรอบนั้น ไม่สร้างย้อนหลัง
                if (now()->gte(CarbonImmutable::parse((string) $parameters['review_starts_at']))) {
                    $schedule->skipped_count++;
                    $schedule->advance();

                    continue;
                }

                // ห้องหนึ่งมีรอบ active ได้เพียงรอบเดียว รวมรอบที่สร้างด้วยมือ
                if ($room->rounds()->where('status', 'active')->exists()) {
                    break;
                }
                $round = new MeetingRound;
                foreach ($parameters as $field => $value) {
                    $round->setAttribute($field, $value);
                }
                $round->room_id = $room->id;
                $round->round_no = max(1, ((int) $room->rounds()->max('round_no')) + 1);
                $round->status = 'active';
                $round->phase = 'join';
                $round->active_marker = 1;
                $round->recurrence_schedule_id = $schedule->id;
                $round->recurrence_index = $schedule->next_index;
                $round->save();
                // สมาชิกต้องเข้าร่วมหรือถูกเลือกใหม่ คะแนนและ snapshot ไม่คัดลอกข้ามรอบ
                $schedule->advance();
                break;
            }
            $schedule->save();
        });
    }
}
