<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, int|string|null> $template
 * @property CarbonImmutable|null $next_run_at
 */
class RoundRecurrenceSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'template' => 'array', 'is_active' => 'boolean',
            'next_run_at' => 'immutable_datetime',
            'round_count' => 'integer', 'next_index' => 'integer', 'skipped_count' => 'integer',
        ];
    }

    /** @return HasMany<MeetingRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(MeetingRound::class, 'recurrence_schedule_id');
    }

    /** @return array<string, int|string|null> */
    public function parametersFor(int $index): array
    {
        $parameters = $this->template;
        $firstJoin = CarbonImmutable::parse((string) $parameters['join_starts_at']);
        if ($this->frequency === 'weekly') {
            $nextJoin = $firstJoin->addWeeks($index);
        } else {
            // ยึดวันเปิดรับต้นฉบับเสมอ ไม่เลื่อนถาวรหลังเจอเดือนสั้น
            $nextJoin = $firstJoin->addMonthsNoOverflow($index);
        }
        $days = (int) $firstJoin->diffInDays($nextJoin);

        // เลื่อนทั้งกำหนดการด้วยจำนวนวันเดียวกัน เพื่อรักษาระยะเวลาของทุกเฟส
        foreach (['join_starts_at', 'review_starts_at', 'voting_starts_at', 'final_starts_at'] as $field) {
            $parameters[$field] = CarbonImmutable::parse((string) $parameters[$field])->addDays($days)->toDateTimeString();
        }
        foreach (['search_start_date', 'search_end_date'] as $field) {
            $parameters[$field] = CarbonImmutable::parse((string) $parameters[$field])->addDays($days)->toDateString();
        }

        return $parameters;
    }

    public function advance(): void
    {
        $this->next_index++;
        if ($this->next_index >= $this->round_count) {
            $this->is_active = false;
            $this->next_run_at = null;
        } else {
            $parameters = $this->parametersFor($this->next_index);
            $this->next_run_at = CarbonImmutable::parse((string) $parameters['join_starts_at']);
        }
    }
}
