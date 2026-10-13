<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 */
class UserBusySlot extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'start_at', 'end_at'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BusySlotRecurrenceRule, $this> */
    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(BusySlotRecurrenceRule::class);
    }

    /** @param Builder<UserBusySlot> $query
     * @return Builder<UserBusySlot>
     */
    public function scopeOverlappingRange(Builder $query, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Builder
    {
        // กิจกรรมซ้ำอาจเริ่มก่อนช่วงที่ดู จึงต้องนำต้นฉบับมาขยายกฎด้วย
        return $query->where('start_at', '<', $rangeEnd)
            ->where(function (Builder $query) use ($rangeStart) {
                $query->where('end_at', '>', $rangeStart)
                    ->orWhereNotNull('recurrence_rule_id');
            });
    }

    /** @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}> */
    public function occurrencesBetween(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        if ($rangeStart >= $rangeEnd || $this->start_at >= $rangeEnd) {
            return [];
        }

        $rule = $this->recurrenceRule;
        if ($rule === null) {
            if ($this->end_at > $rangeStart) {
                return [['start_at' => $this->start_at, 'end_at' => $this->end_at]];
            }

            return [];
        }

        $durationSeconds = (int) $this->start_at->diffInSeconds($this->end_at);
        // เริ่มใกล้ช่วงที่ร้องขอ ไม่วนตั้งแต่ครั้งแรก และเผื่อกิจกรรมที่คร่อมช่วงค้นหา
        $earliestStart = $rangeStart->subSeconds($durationSeconds);
        if ($rule->frequency === 'weekly') {
            $firstIndex = (int) floor($this->start_at->diffInDays($earliestStart, false) / 7) - 1;
        } else {
            $firstIndex = ($earliestStart->year - $this->start_at->year) * 12
                + $earliestStart->month - $this->start_at->month - 1;
        }
        $firstIndex = max(0, $firstIndex);
        $periods = [];

        for ($index = $firstIndex; $index < $rule->occurrence_count; $index++) {
            if ($rule->frequency === 'weekly') {
                $start = $this->start_at->addWeeks($index);
            } else {
                // คำนวณจากต้นฉบับเสมอ: 31 ม.ค. → 28 ก.พ. → 31 มี.ค.
                $start = $this->start_at->addMonthsNoOverflow($index);
            }
            if ($start >= $rangeEnd) {
                break;
            }

            $end = $start->addSeconds($durationSeconds);
            if ($end > $rangeStart) {
                $periods[] = ['start_at' => $start, 'end_at' => $end];
            }
        }

        return $periods;
    }

    /** @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}> */
    public static function windows(MeetingRound $round): array
    {
        $windows = [];
        for ($day = $round->search_start_date; $day->lte($round->search_end_date); $day = $day->addDay()) {
            $windows[] = [
                'start_at' => $day->setTimeFromTimeString($round->daily_start_time),
                'end_at' => $day->setTimeFromTimeString($round->daily_end_time),
            ];
        }

        return $windows;
    }

    /** @param Builder<UserBusySlot> $query
     * @return Builder<UserBusySlot>
     */
    public function scopeOverlappingRound(Builder $query, MeetingRound $round): Builder
    {
        return $query->overlappingRange(
            $round->search_start_date->setTimeFromTimeString($round->daily_start_time),
            $round->search_end_date->setTimeFromTimeString($round->daily_end_time)
        );
    }

    /** Both preview and snapshot use this same overlap query and clipping.
     * @return list<array{start_at: CarbonImmutable, end_at: CarbonImmutable}>
     */
    public static function periodsFor(MeetingRound $round, int $userId): array
    {
        $periods = [];
        $windows = self::windows($round);
        if ($windows === []) {
            return [];
        }
        $rangeStart = $windows[0]['start_at'];
        $rangeEnd = $windows[count($windows) - 1]['end_at'];
        $activities = self::query()->where('user_id', $userId)
            ->overlappingRound($round)->with('recurrenceRule')->get();
        foreach ($activities as $activity) {
            foreach ($activity->occurrencesBetween($rangeStart, $rangeEnd) as $occurrence) {
                foreach ($windows as $window) {
                    $start = $occurrence['start_at']->max($window['start_at']);
                    $end = $occurrence['end_at']->min($window['end_at']);
                    if ($start < $end) {
                        $periods[] = ['start_at' => $start, 'end_at' => $end];
                    }
                }
            }
        }
        usort($periods, function (array $first, array $second): int {
            return $first['start_at'] <=> $second['start_at'];
        });

        return $periods;
    }
}
